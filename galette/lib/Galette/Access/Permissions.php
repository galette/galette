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
                'member:create',
                _T('Create members'),
                $staff,
                'pref_bool_groupsmanagers_create_member',
                'pref_bool_create_member'
            ),
            new Permission('member:read', _T('Display members'), $staff, true, owner: true),
            new Permission(
                'member:edit',
                _T('Edit members'),
                $staff,
                'pref_bool_groupsmanagers_edit_member',
                owner: true
            ),
            //FIXME: members can delete their own card, as they can edit it
            new Permission(
                'member:delete',
                _T('Delete members'),
                $staff,
                'pref_bool_groupsmanagers_edit_member',
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
                'member:export',
                _T('Export members as CSV'),
                $staff,
                'pref_bool_groupsmanagers_exports'
            ),
            new Permission(
                'member:print',
                _T('Print members cards, labels and attendance sheets'),
                $staff,
                'pref_bool_groupsmanagers_exports'
            ),

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
                'pref_bool_groupsmanagers_see_contributions',
                owner: true
            ),
            new Permission('contribution:mass-create', _T('Add contributions in mass'), $staff),
            new Permission('contribution:edit', _T('Edit contributions'), $staff),
            new Permission('contribution:delete', _T('Delete contributions'), $staff),
            new Permission('contribution:export', _T('Export contributions as CSV'), $staff),
            new Permission('contribution:schedule', _T('Manage scheduled payments'), $staff),
            new Permission('contribution:remind', _T('Send reminders to late members'), $staff),

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
                'pref_bool_groupsmanagers_see_transactions',
                owner: true
            ),
            new Permission('transaction:edit', _T('Edit transactions'), $staff),
            new Permission('transaction:delete', _T('Delete transactions'), $staff),
            new Permission('transaction:export', _T('Export transactions as CSV'), $staff),
            new Permission(
                'transaction:attach',
                _T('Attach contributions to transactions'),
                $staff,
                ['pref_bool_groupsmanagers_create_contributions', 'pref_bool_groupsmanagers_see_contributions']
            ),

            new Permission('group:read', _T('Display groups'), $staff, true),
            new Permission('group:create', _T('Create groups'), $staff),
            new Permission('group:delete', _T('Delete groups'), $staff),
            new Permission('group:edit', _T('Edit groups'), $staff, 'pref_bool_groupsmanagers_edit_groups'),
            new Permission('group:export', _T('Export groups as PDF'), $staff, 'pref_bool_groupsmanagers_exports'),

            new Permission('mailing:send', _T('Send mailings'), $staff, 'pref_bool_groupsmanagers_mailings'),
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
