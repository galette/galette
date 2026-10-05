<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Interfaces\PermissionResolverInterface;

/**
 * Resolves permissions from legacy access levels and preferences
 *
 * This is how permissions are granted when the "acls" feature flag is off;
 * it must not change any existing behavior.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LegacyResolver implements PermissionResolverInterface
{
    /**
     * Constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Preferences $preferences
    ) {
    }

    /**
     * Is permission granted?
     *
     * @param Login      $login      Login to check
     * @param Permission $permission Permission
     * @param mixed      $subject    Object the permission applies to, if any
     */
    public function isGranted(Login $login, Permission $permission, mixed $subject = null): bool
    {
        $rule = $this->getRule($permission->name);
        if ($rule !== null) {
            return $rule($login, $subject);
        }

        if ($login->getAccessLevel() >= $permission->level) {
            return true;
        }

        if ($login->isLogged() && $this->isOn($permission->members)) {
            return true;
        }

        return $login->isGroupManager() && $this->isOn($permission->managers);
    }

    /**
     * Get the specific rule of a permission, if any
     *
     * @param string $name Permission name
     *
     * @return ?\Closure(Login, mixed): bool
     */
    private function getRule(string $name): ?\Closure
    {
        return match ($name) {
            'member:create' => $this->canCreateMember(...),
            'member:read' => $this->canReadMember(...),
            //FIXME: deleting is granted as widely as editing, too large.
            'member:edit', 'member:delete' => $this->canEditMember(...),
            'contribution:read' => $this->canReadContribution(...),
            'transaction:read' => $this->canReadTransaction(...),
            'transaction:attach' => $this->canAttachToTransaction(...),
            'group:edit' => $this->canEditGroup(...),
            default => null,
        };
    }

    /**
     * Can login create a member?
     *
     * @param Login     $login  Login to check
     * @param ?Adherent $member Member
     */
    private function canCreateMember(Login $login, ?Adherent $member): bool
    {
        if ($member?->id !== null && $login->id == $member->id || $login->isAdmin() || $login->isStaff()) {
            return true;
        }

        if ($this->preferences->pref_bool_groupsmanagers_create_member && $login->isGroupManager()) {
            return true;
        }
        return $this->preferences->pref_bool_create_member && $login->isLogged();
    }

    /**
     * Can login display a member?
     *
     * @param Login     $login  Login to check
     * @param ?Adherent $member Member
     */
    private function canReadMember(Login $login, ?Adherent $member): bool
    {
        //group managers can show members of groups they manage
        if ($member !== null && $this->managesGroupOf($login, $member)) {
            return true;
        }

        return $this->canEditMember($login, $member);
    }

    /**
     * Can login edit or delete a member?
     *
     * @param Login     $login  Login to check
     * @param ?Adherent $member Member
     */
    private function canEditMember(Login $login, ?Adherent $member): bool
    {
        //admin and staff users can edit, as well as member itself
        if ($member?->id !== null && $login->id == $member->id || $login->isAdmin() || $login->isStaff()) {
            return true;
        }

        if ($member === null) {
            return false;
        }

        //parent can edit their child cards
        if ($member->hasParent() && $member->parent_id === $login->id) {
            return true;
        }

        //group managers can edit members of groups they manage when pref is on
        return $this->preferences->pref_bool_groupsmanagers_edit_member && $this->managesGroupOf($login, $member);
    }

    /**
     * Can login display a contribution?
     *
     * @param Login         $login        Login to check
     * @param ?Contribution $contribution Contribution, a new one if null
     */
    private function canReadContribution(Login $login, ?Contribution $contribution): bool
    {
        //non-logged-in members cannot show contributions
        if (!$login->isLogged()) {
            return false;
        }

        //admin and staff users can edit, as well as member itself
        if (
            $contribution?->id === null
            || $login->id == $contribution->member
            || $login->isAdmin()
            || $login->isStaff()
        ) {
            return true;
        }

        //groups managers can see contributions of their group members - if preferences is enabled
        if ($this->preferences->pref_bool_groupsmanagers_see_contributions && $login->isGroupManager()) {
            $member = new Adherent($this->zdb, (int)$contribution->member, deps: false);
            return $login->isGroupManager(array_keys($member->getGroups()));
        }

        //parent can see their children contributions
        return $this->isParentOf($login, $contribution->member);
    }

    /**
     * Can login display a transaction?
     *
     * @param Login        $login       Login to check
     * @param ?Transaction $transaction Transaction, a new one if null
     */
    private function canReadTransaction(Login $login, ?Transaction $transaction): bool
    {
        //non-logged-in members cannot show transactions
        if (!$login->isLogged()) {
            return false;
        }

        //admin and staff users can edit, as well as member itself
        if (
            $transaction?->id === null
            || $login->id == $transaction->member
            || $login->isAdmin()
            || $login->isStaff()
        ) {
            return true;
        }

        //group managers can see transactions - if enabled in preferences
        //FIXME: whatever group the member belongs to, unlike contributions
        if ($login->isGroupManager() && $this->preferences->pref_bool_groupsmanagers_see_transactions) {
            return true;
        }

        //parent can see their children transactions
        return $this->isParentOf($login, $transaction->member);
    }

    /**
     * Can login attach contributions to, or detach them from, a transaction?
     *
     * Specific right for groups managers on transaction edit page.
     *
     * @param Login        $login       Login to check
     * @param ?Transaction $transaction Transaction, a new one if null
     */
    private function canAttachToTransaction(Login $login, ?Transaction $transaction): bool
    {
        if ($login->isAdmin() || $login->isStaff()) {
            return true;
        }

        return $transaction?->id !== null
            && $login->isGroupManager()
            && (
                $this->preferences->pref_bool_groupsmanagers_create_contributions
                || $this->preferences->pref_bool_groupsmanagers_see_contributions
            );
    }

    /**
     * Can login edit a group?
     *
     * @param Login  $login Login to check
     * @param ?Group $group Group
     */
    private function canEditGroup(Login $login, ?Group $group): bool
    {
        //admin and staff users can edit
        if ($login->isAdmin() || $login->isStaff()) {
            return true;
        }
        //group managers can edit groups they manage when pref is on
        return $this->preferences->pref_bool_groupsmanagers_edit_groups && $group?->isManager($login) === true;
    }

    /**
     * Is login the parent of a member?
     *
     * @param Login $login     Login to check
     * @param ?int  $member_id Member identifier
     */
    private function isParentOf(Login $login, ?int $member_id): bool
    {
        $parent = new Adherent($this->zdb);
        $parent
            ->disableAllDeps()
            ->enableDep('children')
            ->load($login->id);
        if ($parent->hasChildren()) {
            foreach ($parent->children as $child) {
                if ($child->id === $member_id) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Does login manage a group the member belongs to?
     *
     * @param Login    $login  Login to check
     * @param Adherent $member Member
     */
    private function managesGroupOf(Login $login, Adherent $member): bool
    {
        if (!$login->isGroupManager()) {
            return false;
        }

        foreach ($member->getGroups() as $group) {
            if ($login->isGroupManager($group->getId())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is a grant on: always (true), or when its preference - or any of them - is on
     *
     * @param string|array<string>|bool|null $grant Grant
     */
    private function isOn(string|array|bool|null $grant): bool
    {
        if (is_bool($grant) || $grant === null) {
            return $grant === true;
        }

        foreach ((array)$grant as $preference) {
            if ($this->preferences->$preference) {
                return true;
            }
        }
        return false;
    }
}
