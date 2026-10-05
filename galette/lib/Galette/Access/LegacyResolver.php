<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
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
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(
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

        return $this->isGrantedToManagers($login, $permission);
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
     * Are groups managers granted, whatever group they manage?
     *
     * @param Login      $login      Login to check
     * @param Permission $permission Permission
     */
    private function isGrantedToManagers(Login $login, Permission $permission): bool
    {
        if ($permission->managers === null || $permission->managers === false || !$login->isGroupManager()) {
            return false;
        }

        return $permission->managers === true || (bool)$this->preferences->{$permission->managers};
    }
}
