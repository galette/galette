<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Authentication;

/**
 * Permissions catalog
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Permissions
{
    /** @var array<string, Permission> */
    private array $permissions;

    /**
     * Get a permission
     *
     * @param string $name Permission name
     *
     * @throws \InvalidArgumentException when permission does not exist
     */
    public function get(string $name): Permission
    {
        $permissions = $this->getAll();
        if (!isset($permissions[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown permission "%s"', $name));
        }
        return $permissions[$name];
    }

    /**
     * Does permission exist?
     *
     * @param string $name Permission name
     */
    public function has(string $name): bool
    {
        return isset($this->getAll()[$name]);
    }

    /**
     * Get all permissions
     *
     * @return array<string, Permission>
     */
    public function getAll(): array
    {
        if (!isset($this->permissions)) {
            $this->permissions = [];
            foreach ($this->getCorePermissions() as $permission) {
                $this->permissions[$permission->name] = $permission;
            }
        }
        return $this->permissions;
    }

    /**
     * Core permissions
     *
     * Built on first use, so labels are translated in the current language.
     *
     * @return array<Permission>
     */
    protected function getCorePermissions(): array
    {
        $staff = Authentication::ACCESS_STAFF;

        return [
            new Permission(
                'member:create',
                _T('Create members'),
                $staff,
                'pref_bool_groupsmanagers_create_member',
                'pref_bool_create_member'
            ),
            new Permission('member:read', _T('Display members'), $staff, true),
            new Permission('member:edit', _T('Edit members'), $staff, 'pref_bool_groupsmanagers_edit_member'),
            new Permission('member:delete', _T('Delete members'), $staff, 'pref_bool_groupsmanagers_edit_member'),

            new Permission(
                'contribution:create',
                _T('Create contributions'),
                $staff,
                'pref_bool_groupsmanagers_create_contributions'
            ),
            new Permission(
                'contribution:read',
                _T('Display contributions'),
                $staff,
                'pref_bool_groupsmanagers_see_contributions'
            ),
            new Permission('contribution:edit', _T('Edit contributions'), $staff),
            new Permission('contribution:delete', _T('Delete contributions'), $staff),

            new Permission(
                'transaction:create',
                _T('Create transactions'),
                $staff,
                'pref_bool_groupsmanagers_create_transactions'
            ),
            new Permission(
                'transaction:read',
                _T('Display transactions'),
                $staff,
                'pref_bool_groupsmanagers_see_transactions'
            ),
            new Permission('transaction:edit', _T('Edit transactions'), $staff),
            new Permission('transaction:delete', _T('Delete transactions'), $staff),
            new Permission(
                'transaction:attach',
                _T('Attach contributions to transactions'),
                $staff,
                ['pref_bool_groupsmanagers_create_contributions', 'pref_bool_groupsmanagers_see_contributions']
            ),

            new Permission('group:edit', _T('Edit groups'), $staff, 'pref_bool_groupsmanagers_edit_groups'),
        ];
    }
}
