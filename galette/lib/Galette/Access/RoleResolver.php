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
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Interfaces\PermissionResolverInterface;

/**
 * Resolves permissions from roles
 *
 * This is how permissions are granted when the "acls" feature flag is on.
 *
 * A member gets permissions:
 * - from system roles, given from their data (logged-in, up to date, staff,
 *   administrator), and from roles explicitly given to them;
 * - from the Group manager role and roles given for a group, on that group
 *   only: on subjects in that group, or when there is no subject;
 * - on what they own, or their children own, for permissions marked so.
 *
 * Roles inherit their parents permissions. The super administrator is
 * granted everything.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RoleResolver implements PermissionResolverInterface
{
    /** @var ?array<int, array{key: ?string, parent: ?int}> */
    private ?array $roles = null;

    /** @var ?array<int, array<string>> */
    private ?array $roles_permissions = null;

    /** @var array<string, array{global: array<string, true>, groups: array<int, array<string, true>>}> */
    private array $grants = [];

    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(
        private readonly Db $zdb
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
        if (!$login->isLogged()) {
            return false;
        }

        if ($login->isSuperAdmin()) {
            return true;
        }

        $grants = $this->getGrants($login);
        if (isset($grants['global'][$permission->name])) {
            return true;
        }

        if ($permission->owner && $subject !== null && $this->owns($login, $subject)) {
            return true;
        }

        $subject_groups = null;
        foreach ($grants['groups'] as $group_id => $permissions) {
            if (!isset($permissions[$permission->name])) {
                continue;
            }
            if ($subject === null) {
                return true;
            }
            $subject_groups ??= $this->getGroupsOf($subject);
            if (in_array($group_id, $subject_groups, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Forget loaded roles and grants, after they have been changed
     */
    public function reset(): void
    {
        $this->roles = null;
        $this->roles_permissions = null;
        $this->grants = [];
    }

    /**
     * Get permissions granted to login, globally and per group
     *
     * @param Login $login Login
     *
     * @return array{global: array<string, true>, groups: array<int, array<string, true>>}
     */
    private function getGrants(Login $login): array
    {
        $managed_groups = array_map(
            fn(Group|int $group): int => $group instanceof Group ? (int)$group->getId() : (int)$group,
            $login->getManagedGroups()
        );
        $key = implode('|', [
            (int)$login->id,
            (int)$login->isAdmin(),
            (int)$login->isStaff(),
            (int)$login->isUp2Date(),
            implode(',', $managed_groups)
        ]);

        if (isset($this->grants[$key])) {
            return $this->grants[$key];
        }

        $keys = $this->getRolesKeys();

        $global = [$keys[Roles::MEMBER] ?? null];
        if ($login->isUp2Date()) {
            $global[] = $keys[Roles::UPTODATE] ?? null;
        }
        if ($login->isStaff()) {
            $global[] = $keys[Roles::STAFF] ?? null;
        }
        if ($login->isAdmin()) {
            $global[] = $keys[Roles::ADMIN] ?? null;
        }

        $groups = [];
        foreach ($managed_groups as $group_id) {
            $groups[$group_id][] = $keys[Roles::MANAGER] ?? null;
        }

        $select = $this->zdb->select(Roles::MEMBERS_TABLE);
        $select->where(['id_adh' => (int)$login->id]);
        foreach ($this->zdb->execute($select) as $row) {
            if ($row->id_group === null) {
                $global[] = (int)$row->id_role;
            } else {
                $groups[(int)$row->id_group][] = (int)$row->id_role;
            }
        }

        $grants = [
            'global' => $this->getPermissionsOf($global),
            'groups' => array_map($this->getPermissionsOf(...), $groups)
        ];
        $this->grants[$key] = $grants;
        return $grants;
    }

    /**
     * Get permissions of roles, and of their parents
     *
     * @param array<?int> $roles Roles identifiers
     *
     * @return array<string, true>
     */
    private function getPermissionsOf(array $roles): array
    {
        $this->loadRoles();
        $permissions = [];
        $seen = [];
        foreach ($roles as $role) {
            while ($role !== null && !isset($seen[$role])) {
                $seen[$role] = true;
                foreach ($this->roles_permissions[$role] ?? [] as $permission) {
                    $permissions[$permission] = true;
                }
                $role = $this->roles[$role]['parent'] ?? null;
            }
        }
        return $permissions;
    }

    /**
     * Get system roles identifiers
     *
     * @return array<string, int>
     */
    private function getRolesKeys(): array
    {
        $this->loadRoles();
        $keys = [];
        foreach ($this->roles as $id => $role) {
            if ($role['key'] !== null) {
                $keys[$role['key']] = $id;
            }
        }
        return $keys;
    }

    /**
     * Load roles and their permissions
     *
     * @phpstan-assert !null $this->roles
     * @phpstan-assert !null $this->roles_permissions
     */
    private function loadRoles(): void
    {
        if ($this->roles !== null && $this->roles_permissions !== null) {
            return;
        }

        $this->roles = [];
        foreach ($this->zdb->execute($this->zdb->select(Roles::TABLE)) as $row) {
            $this->roles[(int)$row->id_role] = [
                'key' => $row->role_key,
                'parent' => $row->id_parent === null ? null : (int)$row->id_parent
            ];
        }

        $this->roles_permissions = [];
        foreach ($this->zdb->execute($this->zdb->select(Roles::PERMISSIONS_TABLE)) as $row) {
            $this->roles_permissions[(int)$row->id_role][] = $row->permission;
        }
    }

    /**
     * Does login own the subject, or one of their children?
     *
     * @param Login $login   Login
     * @param mixed $subject Subject
     */
    private function owns(Login $login, mixed $subject): bool
    {
        if ($subject instanceof Adherent) {
            if ($subject->id !== null && (int)$subject->id === (int)$login->id) {
                return true;
            }
            return $subject->hasParent() && (int)$subject->parent_id === (int)$login->id;
        }

        if ($subject instanceof Contribution || $subject instanceof Transaction) {
            $member_id = $subject->member;
            if ($member_id === null) {
                return false;
            }
            if ((int)$member_id === (int)$login->id) {
                return true;
            }

            $parent = new Adherent($this->zdb);
            $parent
                ->disableAllDeps()
                ->enableDep('children')
                ->load((int)$login->id);
            if ($parent->hasChildren()) {
                foreach ($parent->children as $child) {
                    if ((int)$child->id === (int)$member_id) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get identifiers of the groups a subject belongs to
     *
     * @param mixed $subject Subject
     *
     * @return array<int>
     */
    private function getGroupsOf(mixed $subject): array
    {
        if ($subject instanceof Group) {
            return [(int)$subject->getId()];
        }

        if ($subject instanceof Contribution || $subject instanceof Transaction) {
            if ($subject->member === null) {
                return [];
            }
            $subject = new Adherent($this->zdb, (int)$subject->member, deps: false);
        }

        if ($subject instanceof Adherent) {
            return array_values(array_map(
                fn(Group $group): int => (int)$group->getId(),
                $subject->getGroups()
            ));
        }

        return [];
    }
}
