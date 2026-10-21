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
use Galette\Core\Logs;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Interfaces\AccessSubjectInterface;
use Galette\Interfaces\PermissionResolverInterface;

/**
 * Resolves permissions from roles
 *
 * A member gets permissions:
 * - from system roles, given from their data (logged-in, up to date, staff,
 *   administrator), and from roles explicitly given to them;
 * - from the Group manager role and roles given for a group, on that group
 *   only: on subjects in that group, or when there is no subject. Permissions
 *   that have no meaning on a group are not granted that way;
 * - on what they own, or their children own, for permissions marked so.
 *
 * Roles inherit their parents permissions. The super administrator is
 * granted everything.
 *
 * System roles get default permissions of permissions not known yet - from
 * plugins activated since roles were set up - when roles are first loaded.
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

    private bool $synced = false;

    /**
     * Constructor
     *
     * @param Db     $zdb        Database instance
     * @param ?Roles $repository Roles, to give system roles default permissions of new permissions
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly ?Roles $repository = null
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

        if (!$permission->hasGroupScope()) {
            return false;
        }

        $subject_groups = false;
        foreach ($grants['groups'] as $group_id => $permissions) {
            if (!isset($permissions[$permission->name])) {
                continue;
            }
            if ($subject === null) {
                return true;
            }
            if ($subject_groups === false) {
                $subject_groups = $this->getGroupsOf($subject);
            }
            if ($subject_groups === null) {
                //a new object, not attached to a member yet, is checked as no object when creating it
                if (str_ends_with($permission->name, ':create')) {
                    return true;
                }
                continue;
            }
            if (in_array($group_id, $subject_groups, strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the groups a permission is granted on
     *
     * @param Login      $login      Login to check
     * @param Permission $permission Permission
     *
     * @return ?array<int>
     */
    public function getGroupScope(Login $login, Permission $permission): ?array
    {
        if (!$login->isLogged()) {
            return [];
        }

        if ($login->isSuperAdmin()) {
            return null;
        }

        $grants = $this->getGrants($login);
        if (isset($grants['global'][$permission->name])) {
            return null;
        }

        if (!$permission->hasGroupScope()) {
            return [];
        }

        $groups = [];
        foreach ($grants['groups'] as $group_id => $permissions) {
            if (isset($permissions[$permission->name])) {
                $groups[] = $group_id;
            }
        }
        return $groups;
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

        $this->syncPermissions();

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
     * Give system roles default permissions of new permissions, once
     */
    private function syncPermissions(): void
    {
        if ($this->synced || $this->repository === null) {
            return;
        }
        $this->synced = true;

        try {
            $this->repository->syncPermissions();
        } catch (\Throwable $e) {
            Logs::exception($e, 'Unable to give system roles default permissions of new permissions');
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

        $member_id = $this->getOwnerIdOf($subject);
        if ($member_id === null) {
            return false;
        }
        return $member_id === (int)$login->id || $this->isParentOf($login, $member_id);
    }

    /**
     * Is login the parent of a member?
     *
     * @param Login $login     Login
     * @param int   $member_id Member identifier
     */
    private function isParentOf(Login $login, int $member_id): bool
    {
        $parent = new Adherent($this->zdb);
        $parent
            ->disableAllDeps()
            ->enableDep('children')
            ->load((int)$login->id);
        if ($parent->hasChildren()) {
            foreach ($parent->children as $child) {
                if ((int)$child->id === $member_id) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get identifier of the member a subject belongs to, if any
     *
     * @param mixed $subject Subject
     */
    private function getOwnerIdOf(mixed $subject): ?int
    {
        $member_id = match (true) {
            $subject instanceof Contribution, $subject instanceof Transaction => $subject->member,
            $subject instanceof AccessSubjectInterface => $subject->getOwnerId(),
            default => null,
        };
        return $member_id === null ? null : (int)$member_id;
    }

    /**
     * Get identifiers of the groups a subject belongs to
     *
     * @param mixed $subject Subject
     *
     * @return ?array<int> Groups identifiers; null for a new member, contribution
     *                     or transaction, not attached to a member yet
     */
    private function getGroupsOf(mixed $subject): ?array
    {
        if ($subject instanceof Group) {
            return [(int)$subject->getId()];
        }

        if ($subject instanceof AccessSubjectInterface && $subject->getGroupsIds() !== null) {
            return array_values(array_map(intval(...), $subject->getGroupsIds()));
        }

        if ($subject instanceof Adherent && $subject->id === null) {
            return null;
        }

        if (!$subject instanceof Adherent) {
            $member_id = $this->getOwnerIdOf($subject);
            if ($member_id === null) {
                return $subject instanceof Contribution || $subject instanceof Transaction ? null : [];
            }
            $subject = new Adherent($this->zdb, $member_id, deps: false);
        }

        return array_values(array_map(
            fn(Group $group): int => (int)$group->getId(),
            $subject->getGroups()
        ));
    }
}
