<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Analog\Analog;
use Galette\Core\Logs;
use Galette\Core\Authentication;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Throwable;

/**
 * Roles
 *
 * System roles are given from members data rather than stored assignments:
 * any logged-in member, members up to date, groups managers (on their
 * groups), staff members and administrators.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Roles
{
    public const string TABLE = Role::TABLE;
    public const string PK = Role::PK;
    public const string PERMISSIONS_TABLE = 'roles_permissions';
    public const string MEMBERS_TABLE = 'members_roles';

    public const string MEMBER = 'member';
    public const string UPTODATE = 'uptodate';
    public const string MANAGER = 'groupmanager';
    public const string STAFF = 'staff';
    public const string ADMIN = 'admin';

    /** @var array<string, array{id: int, name: string, parent: ?string}> */
    private const array SYSTEM = [
        self::MEMBER => ['id' => 1, 'name' => 'Member', 'parent' => null],
        self::UPTODATE => ['id' => 2, 'name' => 'Up to date member', 'parent' => self::MEMBER],
        self::MANAGER => ['id' => 3, 'name' => 'Group manager', 'parent' => self::MEMBER],
        self::STAFF => ['id' => 4, 'name' => 'Staff member', 'parent' => self::MEMBER],
        self::ADMIN => ['id' => 5, 'name' => 'Administrator', 'parent' => self::STAFF],
    ];

    /**
     * Constructor
     *
     * @param Db          $zdb         Database instance
     * @param Permissions $permissions Permissions catalog
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Permissions $permissions
    ) {
    }

    /**
     * Get translated label of a system role
     *
     * @param string $key System role key
     */
    public static function getSystemLabel(string $key): string
    {
        return match ($key) {
            self::MEMBER => _T('Member'),
            self::UPTODATE => _T('Up to date member'),
            self::MANAGER => _T('Group manager'),
            self::STAFF => _T('Staff member'),
            self::ADMIN => _T('Administrator'),
            default => throw new \InvalidArgumentException(sprintf('Unknown system role "%s"', $key)),
        };
    }

    /**
     * Default permissions of system roles
     *
     * What legacy access levels and preferences grant; inherited
     * permissions are not repeated.
     *
     * @param Preferences $preferences Preferences instance
     *
     * @return array<string, array<string>>
     */
    public function getDefaultPermissions(Preferences $preferences): array
    {
        $defaults = array_fill_keys(array_keys(self::SYSTEM), []);

        foreach ($this->permissions->getAll() as $name => $permission) {
            $role = match (true) {
                $permission->level <= Authentication::ACCESS_USER => self::MEMBER,
                $permission->level <= Authentication::ACCESS_STAFF => self::STAFF,
                $permission->level <= Authentication::ACCESS_ADMIN => self::ADMIN,
                default => null, //super administrator only
            };

            if ($role !== self::MEMBER && $permission->isGrantedToMembers($preferences)) {
                $role = self::MEMBER;
            }

            if ($role !== null) {
                $defaults[$role][] = $name;
            }

            if ($role !== self::MEMBER && $permission->isGrantedToManagers($preferences)) {
                $defaults[self::MANAGER][] = $name;
            }
        }

        return $defaults;
    }

    /**
     * Create system roles and their default permissions
     *
     * Existing roles are dropped.
     *
     * @param Preferences $preferences Preferences instance
     *
     * @throws Throwable
     */
    public function installInit(Preferences $preferences): bool
    {
        try {
            $this->zdb->execute($this->zdb->delete(self::TABLE));

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values([
                self::PK => ':id',
                'role_key' => ':key',
                'name' => ':name',
                'id_parent' => ':parent'
            ]);
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            $this->zdb->handleSequence(self::TABLE, self::PK, count(self::SYSTEM));

            //parents are declared first
            foreach (self::SYSTEM as $key => $role) {
                $stmt->execute([
                    ':id' => $role['id'],
                    ':key' => $key,
                    ':name' => $role['name'],
                    ':parent' => $role['parent'] !== null ? self::SYSTEM[$role['parent']]['id'] : null
                ]);
            }

            $insert = $this->zdb->insert(self::PERMISSIONS_TABLE);
            $insert->values([
                self::PK => ':id',
                'permission' => ':permission'
            ]);
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($this->getDefaultPermissions($preferences) as $key => $permissions) {
                foreach ($permissions as $permission) {
                    $stmt->execute([
                        ':id' => self::SYSTEM[$key]['id'],
                        ':permission' => $permission
                    ]);
                }
            }

            Analog::log('System roles were successfully stored into database.', Analog::INFO);
            return true;
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to initialize system roles', Analog::WARNING);
            throw $e;
        }
    }

    /**
     * Get all roles, system ones first
     *
     * @return array<int, Role>
     */
    public function getList(): array
    {
        $select = $this->zdb->select(self::TABLE);
        $select->order(self::PK);
        $roles = [];
        foreach ($this->zdb->execute($select) as $row) {
            $role = new Role($row);
            $roles[$role->getId()] = $role;
        }
        return $roles;
    }

    /**
     * Get permissions a role inherits from its parents
     *
     * @param Role $role Role
     *
     * @return array<string>
     */
    public function getInheritedPermissions(Role $role): array
    {
        $roles = $this->getList();
        $permissions = [];
        $seen = $role->isLoaded() ? [$role->getId() => true] : [];
        $parent = $role->getParentId();
        while ($parent !== null && isset($roles[$parent]) && !isset($seen[$parent])) {
            $seen[$parent] = true;
            $permissions = array_merge($permissions, $roles[$parent]->getPermissions());
            $parent = $roles[$parent]->getParentId();
        }
        $permissions = array_values(array_unique($permissions));
        sort($permissions);
        return $permissions;
    }

    /**
     * Get roles a role can inherit from: neither itself nor one inheriting from it
     *
     * @param Role $role Role
     *
     * @return array<int, Role>
     */
    public function getPossibleParents(Role $role): array
    {
        $roles = $this->getList();
        if (!$role->isLoaded()) {
            return $roles;
        }

        $possible = [];
        foreach ($roles as $id => $candidate) {
            $ancestor = $candidate;
            $seen = [];
            $descendant = false;
            while (!isset($seen[$ancestor->getId()])) {
                $seen[$ancestor->getId()] = true;
                if ($ancestor->getId() === $role->getId()) {
                    $descendant = true;
                    break;
                }
                $parent = $ancestor->getParentId();
                if ($parent === null || !isset($roles[$parent])) {
                    break;
                }
                $ancestor = $roles[$parent];
            }
            if (!$descendant) {
                $possible[$id] = $candidate;
            }
        }
        return $possible;
    }

    /**
     * Get members a role has been given to
     *
     * @param int $role Role identifier
     *
     * @return array<int, array{id: int, id_adh: int, member: string, id_group: ?int, group: ?string}>
     */
    public function getMembersOf(int $role): array
    {
        $select = $this->zdb->select(self::MEMBERS_TABLE, 'mr');
        $select->join(
            ['a' => PREFIX_DB . \Galette\Entity\Adherent::TABLE],
            'a.' . \Galette\Entity\Adherent::PK . ' = mr.id_adh',
            ['nom_adh', 'prenom_adh']
        )->join(
            ['g' => PREFIX_DB . \Galette\Entity\Group::TABLE],
            'g.' . \Galette\Entity\Group::PK . ' = mr.id_group',
            ['group_name'],
            $select::JOIN_LEFT
        )->where(['mr.' . self::PK => $role]);

        $members = [];
        foreach ($this->zdb->execute($select) as $row) {
            $members[(int)$row->id_member_role] = [
                'id' => (int)$row->id_member_role,
                'id_adh' => (int)$row->id_adh,
                'member' => trim($row->nom_adh . ' ' . $row->prenom_adh),
                'id_group' => $row->id_group === null ? null : (int)$row->id_group,
                'group' => $row->group_name
            ];
        }

        //by member, then all groups first; NULL ordering differs between databases
        uasort(
            $members,
            fn(array $a, array $b): int => [$a['member'], $a['group'] !== null, $a['group']]
                <=> [$b['member'], $b['group'] !== null, $b['group']]
        );
        return $members;
    }

    /**
     * Give a role to a member, for a group or globally
     *
     * @param int  $member Member identifier
     * @param int  $role   Role identifier
     * @param ?int $group  Group identifier, null for all groups
     *
     * @return bool false if member already has that role
     */
    public function give(int $member, int $role, ?int $group = null): bool
    {
        $select = $this->zdb->select(self::MEMBERS_TABLE);
        $select->where([
            'id_adh' => $member,
            self::PK => $role,
            'id_group' => $group
        ]);
        if ($this->zdb->execute($select)->count() > 0) {
            return false;
        }

        $insert = $this->zdb->insert(self::MEMBERS_TABLE);
        $insert->values([
            'id_adh' => $member,
            self::PK => $role,
            'id_group' => $group
        ]);
        $this->zdb->execute($insert);
        return true;
    }

    /**
     * Take a given role back
     *
     * @param int $role       Role identifier
     * @param int $assignment Assignment identifier
     */
    public function take(int $role, int $assignment): bool
    {
        $delete = $this->zdb->delete(self::MEMBERS_TABLE);
        $delete->where([
            'id_member_role' => $assignment,
            self::PK => $role
        ]);
        return $this->zdb->execute($delete)->getAffectedRows() > 0;
    }
}
