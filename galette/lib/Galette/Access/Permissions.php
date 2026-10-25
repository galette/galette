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
use Galette\Core\Plugins;

/**
 * Permissions catalog
 *
 * Core permissions, and those active plugins declare in their own domain.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Permissions
{
    /** @var array<string, Permission> */
    private array $permissions;

    /** @var array<string, string> Plugins domains labels */
    private array $plugins_domains = [];

    /**
     * Constructor
     *
     * Plugins can be given lazily, as plugins classes may need the catalog
     * while plugins are being loaded.
     *
     * @param Plugins|(\Closure(): Plugins)|null $plugins Plugins instance, to add permissions they declare
     */
    public function __construct(
        private readonly Plugins|\Closure|null $plugins = null
    ) {
    }

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
            $this->addPluginsPermissions();
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
        $this->getAll();
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
            default => $this->plugins_domains[$domain] ?? ucfirst($domain),
        };
    }

    /**
     * Add permissions active plugins declare
     *
     * Plugins permissions must be in their own domain, which cannot be a
     * core one.
     */
    private function addPluginsPermissions(): void
    {
        if ($this->plugins === null) {
            return;
        }
        $plugins = $this->plugins instanceof \Closure ? ($this->plugins)() : $this->plugins;

        $core_domains = [];
        foreach ($this->permissions as $permission) {
            $core_domains[$permission->getDomain()] = true;
        }

        foreach ($plugins->getPermissions() as $domain => $plugin) {
            if (isset($core_domains[$domain])) {
                Analog::log(
                    sprintf(
                        'Plugin "%1$s" permissions are ignored: "%2$s" is a core domain.',
                        $plugin['label'],
                        $domain
                    ),
                    Analog::WARNING
                );
                continue;
            }

            $this->plugins_domains[$domain] = $plugin['label'];
            foreach ($plugin['permissions'] as $permission) {
                if ($permission->getDomain() !== $domain) {
                    Analog::log(
                        sprintf(
                            'Plugin "%1$s" permission "%2$s" is ignored: it must be in the "%3$s" domain.',
                            $plugin['label'],
                            $permission->name,
                            $domain
                        ),
                        Analog::WARNING
                    );
                    continue;
                }
                $this->permissions[$permission->name] = $permission;
            }
        }
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
                managers: 'pref_bool_groupsmanagers_create_member'
            ),
            new Permission(
                name: 'member:create-child',
                label: _T('Create child members'),
                level: Authentication::ACCESS_SUPERADMIN,
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
            new Permission('member:delete', _T('Delete members'), $staff),
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
            new Permission(
                name: 'member:import',
                label: _T('Import members'),
                level: $staff,
                group: false
            ),
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
            new Permission(
                name: 'group:create',
                label: _T('Create groups'),
                level: $staff,
                group: false
            ),
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
            new Permission(
                name: 'mailing:history',
                label: _T('Browse and delete sent mailings'),
                level: $staff,
                group: false
            ),

            new Permission(
                name: 'document:manage',
                label: _T('Manage documents'),
                level: $staff,
                group: false
            ),

            new Permission(
                name: 'config:references',
                label: _T('Manage statuses, titles, contributions and payment types, labels translations'),
                level: $staff,
                group: false
            ),
            new Permission(
                name: 'config:templates',
                label: _T('Manage emails contents and PDF models'),
                level: $staff,
                group: false
            ),
            new Permission(
                name: 'config:preferences',
                label: _T('Change settings'),
                level: $admin,
                group: false
            ),
            new Permission(
                name: 'config:fields',
                label: _T('Configure core and dynamic fields, and lists'),
                level: $admin,
                group: false
            ),
            new Permission(
                name: 'data:export',
                label: _T('Export data'),
                level: $staff,
                group: false
            ),
            new Permission(
                name: 'history:manage',
                label: _T('Browse and flush logs'),
                level: $staff,
                group: false
            ),
            new Permission(
                name: 'system:info',
                label: _T('Display system information and charts'),
                level: $staff,
                group: false
            ),
            new Permission(
                name: 'security:manage',
                label: _T('Lift authentication restrictions'),
                level: $admin,
                group: false
            ),
            new Permission(
                name: 'plugin:manage',
                label: _T('Manage plugins'),
                level: $admin,
                group: false
            ),
            new Permission(
                name: 'role:manage',
                label: _T('Manage roles and their permissions'),
                level: $admin,
                group: false
            ),
        ];
    }
}
