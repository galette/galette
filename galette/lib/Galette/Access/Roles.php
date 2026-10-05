<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Analog\Analog;
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
    public const string TABLE = 'roles';
    public const string PK = 'id_role';
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
            Analog::log('Unable to initialize system roles: ' . $e->getMessage(), Analog::WARNING);
            throw $e;
        }
    }
}
