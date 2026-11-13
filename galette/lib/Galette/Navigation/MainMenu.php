<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

use Galette\Access\AccessControl;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Core\TwoFactorAuth;
use Galette\Entity\Contribution;

/**
 * Main menu
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MainMenu
{
    /**
     * Constructor
     *
     * @param Login          $login       Login instance
     * @param Preferences    $preferences Preferences instance
     * @param Plugins        $plugins     Plugins instance
     * @param AccessControl  $access      Access control
     * @param ReleaseNotices $releases    New releases
     * @param PublicMenu     $publicMenu  Public pages menu
     */
    public function __construct(
        private readonly Login $login,
        private readonly Preferences $preferences,
        private readonly Plugins $plugins,
        private readonly AccessControl $access,
        private readonly ReleaseNotices $releases,
        private readonly PublicMenu $publicMenu
    ) {
    }

    /**
     * Get all menus, public ones included
     *
     * @return array<string, Menu>
     */
    public function getAllMenus(): array
    {
        return $this->getMenus(public: true);
    }

    /**
     * Get menus
     *
     * @param bool $public Include public menus. Defaults to false
     *
     * @return array<string, Menu>
     */
    public function getMenus(bool $public = false): array
    {
        $menus = [];

        if ($this->login->isLogged()) {
            $menus = [
                'myaccount' => $this->getMyAccountMenu(),
                'members' => $this->getMembersMenu(),
                'contributions' => $this->getContributionsMenu(),
                'management' => $this->getManagementMenu()
            ];
            $configuration = $this->getConfigurationMenu();
            if ($configuration !== null) {
                $menus['configuration'] = $configuration;
            }
        }

        $menus = $this->addPluginsMenus($menus);

        //cleanup empty menus
        $menus = array_filter($menus, fn(Menu $menu): bool => $menu->items !== []);

        if ($public) {
            //never empty
            $menus += $this->publicMenu->getMenus();
        }

        return $menus;
    }

    /**
     * Get "My account" menu
     */
    private function getMyAccountMenu(): Menu
    {
        if (!$this->login->isSuperAdmin()) {
            //member menu
            $items = [
                new MenuEntry(
                    label: _T('My information'),
                    route: new RouteTarget('me'),
                    title: _T('View my member card')
                ),
                new MenuEntry(
                    label: _T('My contributions'),
                    route: new RouteTarget('myContributions', ['type' => 'contributions']),
                    title: _T('View and filter all my contributions')
                ),
                new MenuEntry(
                    label: _T('My scheduled payments'),
                    route: new RouteTarget('myScheduledPayments'),
                    title: _T('View and filter all my scheduled payments')
                ),
                new MenuEntry(
                    label: _T('My transactions'),
                    route: new RouteTarget('myContributions', ['type' => 'transactions']),
                    title: _T('View and filter all my transactions')
                )
            ];
        } else {
            $items = [
                new MenuEntry(
                    label: _T('My information'),
                    route: new RouteTarget('adminCredentials'),
                    title: _T('Modify my login and password')
                )
            ];
        }

        if (!$this->login->isSuperAdmin() && $this->preferences->pref_maps_enabled) {
            $items[] = new MenuEntry(
                label: _T('My localization'),
                route: new RouteTarget('mapsLocalizeMe'),
                title: _T('Set my position on the members map')
            );
        }

        if (!$this->login->isSuperAdmin() && $this->access->can('member:create-child', login: $this->login)) {
            $items[] = new MenuEntry(
                label: _T('Add a child member'),
                route: new RouteTarget('addMemberChild'),
                title: _T('Add new child member in database')
            );
        }

        //outside the member menu block above: the super administrator is not a
        //member, but it does hold a second factor of its own. That one lives in
        //the preferences, which are read only in the settings -- no form may
        //write them -- so this entry is the only way in from the interface.
        if (TwoFactorAuth::modeFrom($this->preferences) !== TwoFactorAuth::MODE_DISABLED) {
            $items[] = new MenuEntry(
                label: _T('Two-factor authentication'),
                route: new RouteTarget('two-factor-manage'),
                title: _T('Manage my two-factor authentication')
            );
        }

        return new Menu(_T("My Account"), 'user', $items);
    }

    /**
     * Get members menu
     */
    private function getMembersMenu(): Menu
    {
        $items = [];

        if ($this->access->getGroupScope('member:read', $this->login) !== []) {
            $items[] = new MenuEntry(
                label: _T("List of members"),
                route: new RouteTarget('members', aliases: ['editMember', 'member']),
                title: _T("View, search into and filter member's list")
            );
            $items[] = new MenuEntry(
                label: _T("Advanced search"),
                route: new RouteTarget('advanced-search'),
                title: _T("Perform advanced search into members list")
            );
            $items[] = new MenuEntry(
                label: _T("Saved searches"),
                route: new RouteTarget('searches')
            );
        }

        if ($this->access->can('member:create', login: $this->login)) {
            $items[] = new MenuEntry(
                label: _T("Add a member"),
                route: new RouteTarget('addMember'),
                title: _T("Add new member in database")
            );
            $items[] = new MenuEntry(
                label: _T("Empty adhesion form"),
                route: new RouteTarget('emptyAdhesionForm'),
                title: _T("Download empty adhesion form")
            );
        }

        return new Menu(_T("Members"), 'users', $items);
    }

    /**
     * Get contributions menu
     */
    private function getContributionsMenu(): Menu
    {
        $items = [];

        if ($this->access->getGroupScope('contribution:read', $this->login) !== []) {
            $items[] = new MenuEntry(
                label: _T("List of contributions"),
                route: new RouteTarget('contributions', ['type' => 'contributions'], ['editContribution']),
                title: _T("View and filter contributions")
            );
        }

        if ($this->access->can('contribution:schedule', login: $this->login)) {
            $items[] = new MenuEntry(
                label: _T("List of scheduled payments"),
                route: new RouteTarget('scheduledPayments', aliases: ['addScheduledPayment', 'editScheduledPayment']),
                title: _T("View and filter scheduled payments")
            );
        }

        if ($this->access->getGroupScope('transaction:read', $this->login) !== []) {
            $items[] = new MenuEntry(
                label: _T("List of transactions"),
                route: new RouteTarget('contributions', ['type' => 'transactions'], ['editTransaction']),
                title: _T("View and filter transactions")
            );
        }

        if ($this->access->can('contribution:create', login: $this->login)) {
            $items[] = new MenuEntry(
                label: _T("Add a membership fee"),
                route: new RouteTarget('addContribution', ['type' => Contribution::TYPE_FEE]),
                title: _T("Add new membership fee in database")
            );
            $items[] = new MenuEntry(
                label: _T("Add a donation"),
                route: new RouteTarget('addContribution', ['type' => Contribution::TYPE_DONATION]),
                title: _T("Add new donation in database")
            );
        }

        if ($this->access->can('transaction:create', login: $this->login)) {
            $items[] = new MenuEntry(
                label: _T("Add a transaction"),
                route: new RouteTarget('addTransaction'),
                title: _T("Add new transaction in database")
            );
        }

        if ($this->access->can('contribution:remind', login: $this->login)) {
            $items[] = new MenuEntry(
                label: _T("Reminders"),
                route: new RouteTarget('reminders'),
                title: _T("Send reminders to late members")
            );
        }

        return new Menu(_T('Contributions'), 'receipt', $items);
    }

    /**
     * Get management menu
     */
    private function getManagementMenu(): Menu
    {
        return new Menu(_T("Management"), 'dharmachakra', $this->granted([
            ['group:read', new MenuEntry(
                label: _T("Groups"),
                route: new RouteTarget('groups', aliases: ['editGroup']),
                title: _T("View and manage groups")
            )],
            ['document:manage', new MenuEntry(
                label: _T("Documents"),
                route: new RouteTarget('documentsList', aliases: ['editDocument', 'addDocument']),
                title: _T("Add documents to share related to your association (status, rules of procedure, ...)")
            )],
            ['mailing:history', new MenuEntry(
                label: _T("Mailings"),
                route: new RouteTarget('mailings'),
                title: _T("Manage mailings that has been sent")
            )],
            ['system:info', new MenuEntry(
                label: _T("Charts"),
                route: new RouteTarget('charts'),
                title: _T("Various charts")
            )],
            ['data:export', new MenuEntry(
                label: _T("Exports"),
                route: new RouteTarget('export'),
                title: _T("Export some data in various formats")
            )],
            ['member:import', new MenuEntry(
                label: _T("Imports"),
                route: new RouteTarget('import', aliases: ['importModel']),
                title: _T("Import members from CSV files")
            )],
            ['security:manage', new MenuEntry(
                label: _T("Authentication attempts"),
                route: new RouteTarget('authAttempts'),
                title: _T("See what is being refused after repeated failures, and lift it")
            )],
            ['history:manage', new MenuEntry(
                label: _T("Logs"),
                route: new RouteTarget('history'),
                title: _T("View application's logs")
            )]
        ]));
    }

    /**
     * Get configuration menu, if any entry is granted
     */
    private function getConfigurationMenu(): ?Menu
    {
        $plugins_updates = $this->access->can('plugin:manage', login: $this->login)
            ? count($this->releases->getPluginsUpdates())
            : 0;

        $items = $this->granted([
            ['config:preferences', new MenuEntry(
                label: _T("Settings"),
                route: new RouteTarget('preferences'),
                title: _T("Set applications preferences (address, website, member's cards configuration, ...)")
            )],
            //listed even when maps are disabled: they are enabled from there
            ['config:preferences', new MenuEntry(
                label: _T("Maps settings"),
                route: new RouteTarget('mapsSettings'),
                title: _T("Display members positions on maps, and choose the background map")
            )],
            ['plugin:manage', new MenuEntry(
                label: _T("Plugins"),
                route: new RouteTarget('plugins'),
                title: $plugins_updates > 0
                    ? sprintf(
                        //TRANS: %1$d is the number of plugins that have a new release
                        _Tn('%1$d plugin has a new release available', '%1$d plugins have a new release available', $plugins_updates),
                        $plugins_updates
                    ) : _T("Information about available plugins"),
                badge: $plugins_updates > 0 ? $plugins_updates : null
            )],
            ['config:fields', new MenuEntry(
                label: _T("Core lists"),
                route: new RouteTarget('configureListFields', ['table' => 'adherents']),
                title: _T("Customize lists fields and order")
            )],
            ['config:fields', new MenuEntry(
                label: _T("Core fields"),
                route: new RouteTarget('configureCoreFields'),
                title: _T("Customize fields order, set which are required, and for who they're visibles")
            )],
            ['config:fields', new MenuEntry(
                label: _T("Dynamic fields"),
                route: new RouteTarget('configureDynamicFields', aliases: ['editDynamicField']),
                title: _T("Manage additional fields for various forms")
            )],
            ['config:references', new MenuEntry(
                label: _T("Contributions types"),
                route: new RouteTarget('contributionsTypes', aliases: ['editContributionType']),
                title: _T("Manage contributions types")
            )],
            ['config:references', new MenuEntry(
                label: _T("Payment types"),
                route: new RouteTarget('paymentTypes', aliases: ['editPaymentType']),
                title: _T("Manage payment types")
            )],
            ['config:references', new MenuEntry(
                label: _T("User statuses"),
                route: new RouteTarget('status', aliases: ['editStatus'])
            )],
            ['config:references', new MenuEntry(
                label: _T("Titles"),
                route: new RouteTarget('titles', aliases: ['editTitle']),
                title: _T("Manage titles")
            )],
            ['config:templates', new MenuEntry(
                label: _T("PDF models"),
                route: new RouteTarget('pdfModels'),
                title: _T("Manage PDF models")
            )],
            ['config:templates', new MenuEntry(
                label: _T("Emails content"),
                route: new RouteTarget('texts'),
                title: _T("Manage emails texts and subjects")
            )],
            ['config:references', new MenuEntry(
                label: _T("Labels translation"),
                route: new RouteTarget('dynamicTranslations', aliases: ['dynamicTranslation']),
                title: _T("Manage translatable labels")
            )],
            ['role:manage', new MenuEntry(
                label: _T("Roles"),
                route: new RouteTarget('roles', aliases: ['editRole']),
                title: _T("Manage roles and their permissions")
            )]
        ]);

        if ($this->login->isSuperAdmin()) {
            $items[] = new MenuEntry(
                label: _T("Administration tools"),
                route: new RouteTarget('adminTools'),
                title: _T("Various administrative tools")
            );
            $items[] = new MenuEntry(
                label: _T("Advanced configuration"),
                route: new RouteTarget('advancedConfig'),
                title: _T("Edit every setting, including those the settings form does not show")
            );
        }

        return $items === [] ? null : new Menu(_T("Configuration"), 'tools', $items);
    }

    /**
     * Add plugins menus entries
     *
     * Plugins add entries to existing menus, or new menus.
     *
     * @param array<string, Menu> $menus Core menus
     *
     * @return array<string, Menu>
     */
    private function addPluginsMenus(array $menus): array
    {
        foreach ($this->plugins->getProviders(Plugins\MenuProviderInterface::class) as $plugin) {
            foreach ($plugin->getMenus() as $key => $menu) {
                if (!is_array($menu)) {
                    continue;
                }
                $items = MenuEntry::listFromArray(is_array($menu['items'] ?? null) ? $menu['items'] : []);
                $menus[$key] = isset($menus[$key])
                    ? $menus[$key]->withItems(...$items)
                    : new Menu(
                        is_string($menu['title'] ?? null) ? $menu['title'] : '',
                        is_string($menu['icon'] ?? null) ? $menu['icon'] : '',
                        $items
                    );
            }
        }

        // Moves the "Add a child member" and the "Two-factor authentication"
        // items to the end of the "My Account" menu.
        if (isset($menus['myaccount'])) {
            $last = ['addMemberChild', 'two-factor-manage'];
            $items = array_filter(
                $menus['myaccount']->items,
                fn(MenuEntry $item): bool => !in_array($item->route?->name, $last, strict: true)
            );
            foreach ($last as $name) {
                foreach ($menus['myaccount']->items as $item) {
                    if ($item->route?->name === $name) {
                        $items[] = $item;
                    }
                }
            }
            $menus['myaccount'] = new Menu($menus['myaccount']->title, $menus['myaccount']->icon, array_values($items));
        }

        return $menus;
    }

    /**
     * Get menu entries granted to login
     *
     * @param list<array{0: string, 1: MenuEntry}> $items Permission and menu entry pairs
     *
     * @return list<MenuEntry>
     */
    private function granted(array $items): array
    {
        $granted = [];
        foreach ($items as [$permission, $item]) {
            if ($this->access->can($permission, login: $this->login)) {
                $granted[] = $item;
            }
        }
        return $granted;
    }
}
