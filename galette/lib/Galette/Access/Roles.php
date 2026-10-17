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
    public const string KNOWN_TABLE = 'known_permissions';

    public const string MEMBER = 'member';
    public const string UPTODATE = 'uptodate';
    public const string MANAGER = 'groupmanager';
    public const string STAFF = 'staff';
    public const string ADMIN = 'admin';

    /**
     * Rights settings of Galette before 1.4, with their default values
     *
     * They have been replaced by system roles permissions: their values are
     * read once, when upgrading.
     *
     * @var array<string, bool>
     */
    public const array LEGACY_RIGHTS = [
        'pref_bool_create_member' => false,
        'pref_bool_groupsmanagers_create_member' => false,
        'pref_bool_groupsmanagers_edit_member' => false,
        'pref_bool_groupsmanagers_edit_groups' => false,
        'pref_bool_groupsmanagers_mailings' => false,
        'pref_bool_groupsmanagers_exports' => true,
        'pref_bool_groupsmanagers_create_contributions' => false,
        'pref_bool_groupsmanagers_create_transactions' => false,
        'pref_bool_groupsmanagers_see_contributions' => false,
        'pref_bool_groupsmanagers_see_transactions' => false,
    ];

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
     * What access levels and rights settings of Galette before 1.4 granted;
     * inherited permissions are not repeated.
     *
     * @param array<string, bool> $rights Former rights settings values
     *
     * @return array<string, array<string>>
     */
    public function getDefaultPermissions(array $rights = self::LEGACY_RIGHTS): array
    {
        $defaults = array_fill_keys(array_keys(self::SYSTEM), []);

        foreach ($this->permissions->getAll() as $name => $permission) {
            $role = match (true) {
                $permission->level <= Authentication::ACCESS_USER => self::MEMBER,
                $permission->level <= Authentication::ACCESS_STAFF => self::STAFF,
                $permission->level <= Authentication::ACCESS_ADMIN => self::ADMIN,
                default => null, //super administrator only
            };

            if ($role !== self::MEMBER && $permission->isGrantedToMembers($rights)) {
                $role = self::MEMBER;
            }

            if ($role !== null) {
                $defaults[$role][] = $name;
            }

            if ($role !== self::MEMBER && $permission->isGrantedToManagers($rights)) {
                $defaults[self::MANAGER][] = $name;
            }
        }

        return $defaults;
    }

    /**
     * Create system roles and their default permissions
     *
     * Existing roles are dropped. Permissions of the catalog are recorded as
     * known.
     *
     * @param array<string, bool> $rights Former rights settings values, defaults if not upgrading
     *
     * @throws Throwable
     */
    public function installInit(array $rights = self::LEGACY_RIGHTS): bool
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

            foreach ($this->getDefaultPermissions($rights) as $key => $permissions) {
                foreach ($permissions as $permission) {
                    $stmt->execute([
                        ':id' => self::SYSTEM[$key]['id'],
                        ':permission' => $permission
                    ]);
                }
            }

            $this->zdb->execute($this->zdb->delete(self::KNOWN_TABLE));
            $this->storeKnown(array_keys($this->permissions->getAll()));

            Analog::log('System roles were successfully stored into database.', Analog::INFO);
            return true;
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to initialize system roles', Analog::WARNING);
            throw $e;
        }
    }

    /**
     * Give system roles the default permissions of new permissions
     *
     * Permissions the catalog did not have yet - those of a plugin activated
     * after roles were set up, or of a newer version - are granted as their
     * level and default rights settings grant them, once: removing them from a role
     * afterwards is not undone.
     *
     * @return array<string> New permissions
     */
    public function syncPermissions(): array
    {
        $known = [];
        $select = $this->zdb->select(self::KNOWN_TABLE);
        foreach ($this->zdb->execute($select) as $row) {
            $known[] = (string)$row->permission;
        }

        $new = array_values(array_diff(array_keys($this->permissions->getAll()), $known));
        if ($new === []) {
            return [];
        }

        $roles_keys = [];
        $select = $this->zdb->select(self::TABLE);
        $select->where->isNotNull('role_key');
        foreach ($this->zdb->execute($select) as $row) {
            $roles_keys[(string)$row->role_key] = (int)$row->{self::PK};
        }

        $transaction = !$this->zdb->inTransaction();
        if ($transaction) {
            $this->zdb->beginTransaction();
        }
        try {
            foreach ($this->getDefaultPermissions() as $key => $permissions) {
                if (!isset($roles_keys[$key])) {
                    continue;
                }
                foreach (array_intersect($permissions, $new) as $permission) {
                    $insert = $this->zdb->insert(self::PERMISSIONS_TABLE);
                    $insert->values([self::PK => $roles_keys[$key], 'permission' => $permission]);
                    $this->zdb->execute($insert);
                }
            }
            $this->storeKnown($new);
            if ($transaction) {
                $this->zdb->commit();
            }
        } catch (Throwable $e) {
            if ($transaction) {
                $this->zdb->rollback();
            }
            throw $e;
        }

        Analog::log(
            sprintf('System roles got default permissions of %1$s.', implode(', ', $new)),
            Analog::INFO
        );
        return $new;
    }

    /**
     * Record permissions as known
     *
     * @param array<string> $permissions Permissions names
     */
    private function storeKnown(array $permissions): void
    {
        $insert = $this->zdb->insert(self::KNOWN_TABLE);
        $insert->values(['permission' => ':permission']);
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);
        foreach ($permissions as $permission) {
            $stmt->execute([':permission' => $permission]);
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
        $permissions = array_keys($this->getInheritedPermissionsOrigins($role));
        sort($permissions);
        return $permissions;
    }

    /**
     * Get permissions a role inherits from its parents, with the nearest
     * parent granting each of them
     *
     * @param Role $role Role
     *
     * @return array<string, Role>
     */
    public function getInheritedPermissionsOrigins(Role $role): array
    {
        $roles = $this->getList();
        $origins = [];
        $seen = $role->isLoaded() ? [$role->getId() => true] : [];
        $parent = $role->getParentId();
        while ($parent !== null && isset($roles[$parent]) && !isset($seen[$parent])) {
            $seen[$parent] = true;
            foreach ($roles[$parent]->getPermissions() as $permission) {
                $origins[$permission] ??= $roles[$parent];
            }
            $parent = $roles[$parent]->getParentId();
        }
        return $origins;
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
            name: ['g' => PREFIX_DB . \Galette\Entity\Group::TABLE],
            on: 'g.' . \Galette\Entity\Group::PK . ' = mr.id_group',
            columns: ['group_name'],
            type: $select::JOIN_LEFT
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
     * Get roles of a member: system roles computed from the member card, and given roles
     *
     * @param \Galette\Entity\Adherent $member Member
     *
     * @return array<array{role: Role, group: ?string, id_group: ?int, given: bool, assignment: ?int}>
     */
    public function getRolesOf(\Galette\Entity\Adherent $member): array
    {
        $system = [];
        foreach ($this->getList() as $role) {
            if ($role->isSystem()) {
                $system[$role->getKey()] = $role;
            }
        }

        $computed = [self::MEMBER => true];
        $computed[self::UPTODATE] = $member->isUp2Date();
        $computed[self::STAFF] = $member->isStaff();
        $computed[self::ADMIN] = $member->isAdmin();

        $roles = [];
        foreach (array_keys(array_filter($computed)) as $key) {
            if (isset($system[$key])) {
                $roles[] = [
                    'role' => $system[$key],
                    'group' => null,
                    'id_group' => null,
                    'given' => false,
                    'assignment' => null
                ];
            }
        }
        if (isset($system[self::MANAGER])) {
            foreach ($member->getManagedGroups() as $group) {
                $roles[] = [
                    'role' => $system[self::MANAGER],
                    'group' => $group->getName(),
                    'id_group' => $group->getId(),
                    'given' => false,
                    'assignment' => null
                ];
            }
        }

        $select = $this->zdb->select(self::MEMBERS_TABLE, 'mr');
        $select->join(
            ['r' => PREFIX_DB . self::TABLE],
            'r.' . self::PK . ' = mr.' . self::PK,
            ['*']
        )->join(
            name: ['g' => PREFIX_DB . \Galette\Entity\Group::TABLE],
            on: 'g.' . \Galette\Entity\Group::PK . ' = mr.id_group',
            columns: ['group_name'],
            type: $select::JOIN_LEFT
        )->where(['mr.id_adh' => $member->id]);

        $given = [];
        foreach ($this->zdb->execute($select) as $row) {
            $given[] = [
                'role' => new Role($row),
                'group' => $row->group_name,
                'id_group' => $row->id_group === null ? null : (int)$row->id_group,
                'given' => true,
                'assignment' => (int)$row->id_member_role
            ];
        }
        //by role name, then all groups first; NULL ordering differs between databases
        usort(
            $given,
            fn(array $a, array $b): int => [$a['role']->getLabel(), $a['group'] !== null, $a['group']]
                <=> [$b['role']->getLabel(), $b['group'] !== null, $b['group']]
        );

        return array_merge($roles, $given);
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

    /**
     * Take a given role back from a member
     *
     * @param int $member     Member identifier
     * @param int $role       Role identifier
     * @param int $assignment Assignment identifier
     */
    public function takeFrom(int $member, int $role, int $assignment): bool
    {
        $delete = $this->zdb->delete(self::MEMBERS_TABLE);
        $delete->where([
            'id_member_role' => $assignment,
            'id_adh' => $member,
            self::PK => $role
        ]);
        return $this->zdb->execute($delete)->getAffectedRows() > 0;
    }

    /**
     * Set the roles given to a member, replacing the ones they had
     *
     * System roles, unknown roles and unknown groups are ignored.
     *
     * @param int                     $member Member identifier
     * @param array<array{int, ?int}> $wanted Role and group identifiers, null group for all groups
     *
     * @return array{added: int, removed: int}
     */
    public function setGiven(int $member, array $wanted): array
    {
        $roles = array_filter($this->getList(), fn(Role $role): bool => !$role->isSystem());
        $groups = \Galette\Repository\Groups::getSimpleList();

        $keep = [];
        foreach ($wanted as [$role, $group]) {
            if (isset($roles[$role]) && ($group === null || isset($groups[$group]))) {
                $keep[$role . '|' . $group] = [$role, $group];
            }
        }

        $removed = 0;
        $select = $this->zdb->select(self::MEMBERS_TABLE);
        $select->where(['id_adh' => $member]);
        foreach ($this->zdb->execute($select) as $row) {
            $key = $row->{self::PK} . '|' . $row->id_group;
            if (isset($keep[$key])) {
                unset($keep[$key]);
            } elseif ($this->takeFrom($member, (int)$row->{self::PK}, (int)$row->id_member_role)) {
                ++$removed;
            }
        }

        $added = 0;
        foreach ($keep as [$role, $group]) {
            if ($this->give($member, $role, $group)) {
                ++$added;
            }
        }

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * Count members a system role is computed for, including roles that are not listed
     *
     * @param Role $role System role
     *
     * @return ?int null for a role that is not a system one
     */
    public function countComputedMembersOf(Role $role): ?int
    {
        $computed = $this->getComputedMembersOf($role);
        if ($computed !== null) {
            return count($computed);
        }

        //the ones who can log in, see Login
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->columns(['count' => new \Laminas\Db\Sql\Expression('COUNT(*)')]);
        $select->where(['a.activite_adh' => true]);
        switch ($role->getKey()) {
            case self::MEMBER:
                break;
            case self::UPTODATE:
                //see Adherent::isUp2Date()
                $select->where->nest()
                    ->equalTo('a.bool_exempt_adh', true)
                    ->or->greaterThanOrEqualTo('a.date_echeance', date('Y-m-d'))
                    ->unnest();
                break;
            default:
                return null;
        }

        return (int)$this->zdb->execute($select)->current()->count;
    }

    /**
     * Get members a system role is computed for
     *
     * Member and up to date member roles are not listed: nearly everyone has them.
     *
     * @param Role $role System role
     *
     * @return ?array<array{id_adh: int, member: string, group: ?string}> null when not listed
     */
    public function getComputedMembersOf(Role $role): ?array
    {
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->columns([\Galette\Entity\Adherent::PK, 'nom_adh', 'prenom_adh']);
        switch ($role->getKey()) {
            case self::ADMIN:
                $select->where(['a.bool_admin_adh' => true]);
                break;
            case self::STAFF:
                $select->join(
                    name: ['s' => PREFIX_DB . \Galette\Entity\Status::TABLE],
                    on: 's.' . \Galette\Entity\Status::PK . ' = a.' . \Galette\Entity\Status::PK,
                    columns: []
                )->where->lessThan('s.priorite_statut', \Galette\Repository\Members::NON_STAFF_MEMBERS);
                break;
            case self::MANAGER:
                $select->join(
                    name: ['gm' => PREFIX_DB . \Galette\Entity\Group::GROUPSMANAGERS_TABLE],
                    on: 'gm.' . \Galette\Entity\Adherent::PK . ' = a.' . \Galette\Entity\Adherent::PK,
                    columns: []
                )->join(
                    name: ['g' => PREFIX_DB . \Galette\Entity\Group::TABLE],
                    on: 'g.' . \Galette\Entity\Group::PK . ' = gm.' . \Galette\Entity\Group::PK,
                    columns: ['group_name']
                );
                break;
            default:
                return null;
        }

        $members = [];
        foreach ($this->zdb->execute($select) as $row) {
            $members[] = [
                'id_adh' => (int)$row->id_adh,
                'member' => trim($row->nom_adh . ' ' . $row->prenom_adh),
                'group' => $row->group_name ?? null
            ];
        }

        //by member, then group
        usort(
            $members,
            fn(array $a, array $b): int => [$a['member'], $a['group']] <=> [$b['member'], $b['group']]
        );
        return $members;
    }
}
