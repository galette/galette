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
     * Get all permissions, by domain
     *
     * @return array<string, array<string, Permission>>
     */
    public function getByDomain(): array
    {
        $domains = [];
        foreach ($this->getAll() as $name => $permission) {
            $domains[$permission->getDomain()][$name] = $permission;
        }
        return $domains;
    }

    /**
     * Get translated label of a domain
     *
     * @param string $domain Domain
     */
    public function getDomainLabel(string $domain): string
    {
        return match ($domain) {
            'member' => _T('Members'),
            'contribution' => _T('Contributions'),
            'transaction' => _T('Transactions'),
            'group' => _T('Groups'),
            'mailing' => _T('Mailings'),
            'document' => _T('Documents'),
            'config' => _T('Configuration'),
            'data' => _T('Exports'),
            'history' => _T('Logs'),
            'system' => _T('System information'),
            'security' => _T('Security'),
            'plugin' => _T('Plugins'),
            'role' => _T('Roles'),
            default => ucfirst($domain),
        };
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
        $admin = Authentication::ACCESS_ADMIN;

        return [
            new Permission(
                name: 'member:create',
                label: _T('Create members'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_create_member',
                members: 'pref_bool_create_member'
            ),
            new Permission(
                name: 'member:read',
                label: _T('Display members'),
                level: $staff,
                managers: true,
                owner: true
            ),
            new Permission(
                name: 'member:edit',
                label: _T('Edit members'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_edit_member',
                owner: true
            ),
            //FIXME: members can delete their own card, as they can edit it
            new Permission(
                name: 'member:delete',
                label: _T('Delete members'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_edit_member',
                owner: true
            ),
            new Permission(
                'member:manage',
                _T('Manage members administrative information (status, exemption, account, parent)'),
                $staff
            ),
            new Permission('member:grant-admin', _T('Grant administrator rights to members'), $admin),
            new Permission('member:mass-edit', _T('Change or delete members in mass'), $staff),
            new Permission(
                'member:credentials',
                _T('Send members new password links, reset their two-factor authentication'),
                $staff
            ),
            new Permission('member:import', _T('Import members'), $staff),
            new Permission(
                name: 'member:export',
                label: _T('Export members as CSV'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_exports'
            ),
            new Permission(
                name: 'member:print',
                label: _T('Print members cards, labels and attendance sheets'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_exports'
            ),

            new Permission(
                name: 'contribution:create',
                label: _T('Create contributions'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_create_contributions'
            ),
            new Permission(
                name: 'contribution:read',
                label: _T('Display contributions'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_see_contributions',
                owner: true
            ),
            new Permission('contribution:mass-create', _T('Add contributions in mass'), $staff),
            new Permission('contribution:edit', _T('Edit contributions'), $staff),
            new Permission('contribution:delete', _T('Delete contributions'), $staff),
            new Permission('contribution:export', _T('Export contributions as CSV'), $staff),
            new Permission('contribution:schedule', _T('Manage scheduled payments'), $staff),
            new Permission('contribution:remind', _T('Send reminders to late members'), $staff),

            new Permission(
                name: 'transaction:create',
                label: _T('Create transactions'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_create_transactions'
            ),
            new Permission(
                name: 'transaction:read',
                label: _T('Display transactions'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_see_transactions',
                owner: true
            ),
            new Permission('transaction:edit', _T('Edit transactions'), $staff),
            new Permission('transaction:delete', _T('Delete transactions'), $staff),
            new Permission('transaction:export', _T('Export transactions as CSV'), $staff),
            new Permission(
                name: 'transaction:attach',
                label: _T('Attach contributions to transactions'),
                level: $staff,
                managers: ['pref_bool_groupsmanagers_create_contributions', 'pref_bool_groupsmanagers_see_contributions']
            ),

            new Permission(
                name: 'group:read',
                label: _T('Display groups'),
                level: $staff,
                managers: true
            ),
            new Permission('group:create', _T('Create groups'), $staff),
            new Permission('group:delete', _T('Delete groups'), $staff),
            new Permission(
                name: 'group:edit',
                label: _T('Edit groups'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_edit_groups'
            ),
            new Permission(
                name: 'group:export',
                label: _T('Export groups as PDF'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_exports'
            ),

            new Permission(
                name: 'mailing:send',
                label: _T('Send mailings'),
                level: $staff,
                managers: 'pref_bool_groupsmanagers_mailings'
            ),
            new Permission('mailing:history', _T('Browse and delete sent mailings'), $staff),

            new Permission('document:manage', _T('Manage documents'), $staff),

            new Permission(
                'config:references',
                _T('Manage statuses, titles, contributions and payment types, labels translations'),
                $staff
            ),
            new Permission('config:templates', _T('Manage emails contents and PDF models'), $staff),
            new Permission('config:preferences', _T('Change settings'), $admin),
            new Permission('config:fields', _T('Configure core and dynamic fields, and lists'), $admin),
            new Permission('data:export', _T('Export data'), $staff),
            new Permission('history:manage', _T('Browse and flush logs'), $staff),
            new Permission('system:info', _T('Display system information and charts'), $staff),
            new Permission('security:manage', _T('Lift authentication restrictions'), $admin),
            new Permission('plugin:manage', _T('Manage plugins'), $admin),
            new Permission('role:manage', _T('Manage roles and their permissions'), $admin),
        ];
    }
}
