<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

use Galette\Access\AccessControl;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Repository\Documents;

/**
 * Dashboard entries
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Dashboards
{
    /**
     * Constructor
     *
     * @param Db             $zdb         Database instance
     * @param Login          $login       Login instance
     * @param Plugins        $plugins     Plugins instance
     * @param AccessControl  $access      Access control
     * @param ReleaseNotices $releases    New releases
     * @param Preferences    $preferences Preferences instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Login $login,
        private readonly Plugins $plugins,
        private readonly AccessControl $access,
        private readonly ReleaseNotices $releases,
        private readonly Preferences $preferences
    ) {
    }

    /**
     * Get current logged-in user dashboards
     *
     * @return list<MenuEntry>
     */
    public function getMyEntries(): array
    {
        $dashboards = [];
        if ($this->login->isLogged() && !$this->login->isSuperAdmin()) {
            // Single member
            $dashboards = [
                new MenuEntry(
                    label: _T("My information"),
                    route: new RouteTarget('me'),
                    title: _T("View my member card"),
                    icon: 'bust_in_silhouette'
                ),
                new MenuEntry(
                    label: _T("My contributions"),
                    route: new RouteTarget('myContributions', ['type' => 'contributions']),
                    title: _T("View and filter all my contributions"),
                    icon: 'receipt'
                ),
                new MenuEntry(
                    label: _T("My transactions"),
                    route: new RouteTarget('myContributions', ['type' => 'transactions']),
                    title: _T("View and filter all my transactions"),
                    icon: 'book'
                )
            ];

            if ($this->preferences->pref_maps_enabled) {
                $dashboards[] = new MenuEntry(
                    label: _T("My localization"),
                    route: new RouteTarget('mapsLocalizeMe'),
                    title: _T('Set my position on the members map'),
                    icon: 'round_pushpin'
                );
            }
        }

        foreach ($this->plugins->getProviders(Plugins\DashboardProviderInterface::class) as $plugin) {
            array_push($dashboards, ...MenuEntry::listFromArray($plugin->getMyDashboards()));
        }

        return $dashboards;
    }

    /**
     * Get dashboards
     *
     * @return list<MenuEntry>
     */
    public function getEntries(): array
    {
        $dashboards = [];

        if ($this->access->getGroupScope('member:read', $this->login) !== []) {
            $dashboards[] = new MenuEntry(
                label: _T("Members"),
                route: new RouteTarget('members'),
                title: _T("View, search into and filter member's list"),
                icon: 'card_box'
            );
        }

        if ($this->access->can('group:read', login: $this->login)) {
            $dashboards[] = new MenuEntry(
                label: _T("Groups"),
                route: new RouteTarget('groups'),
                title: _T("View and manage groups"),
                icon: 'busts_in_silhouette'
            );
        }

        if ($this->access->can('mailing:history', login: $this->login)) {
            $dashboards[] = new MenuEntry(
                label: _T("Mailings"),
                route: new RouteTarget('mailings'),
                title: _T("Manage mailings that has been sent"),
                icon: 'postbox'
            );
        }

        if ($this->access->getGroupScope('contribution:read', $this->login) !== []) {
            $dashboards[] = new MenuEntry(
                label: _T("Contributions"),
                route: new RouteTarget('contributions', ['type' => 'contributions']),
                title: _T("View and filter contributions"),
                icon: 'receipt'
            );
        }

        if ($this->access->getGroupScope('transaction:read', $this->login) !== []) {
            $dashboards[] = new MenuEntry(
                label: _T("Transactions"),
                route: new RouteTarget('contributions', ['type' => 'transactions']),
                title: _T("View and filter transactions"),
                icon: 'book'
            );
        }

        if ($this->access->can('contribution:remind', login: $this->login)) {
            $dashboards[] = new MenuEntry(
                label: _T("Reminders"),
                route: new RouteTarget('reminders'),
                title: _T("Send reminders to late members"),
                icon: 'bell'
            );
        }

        //display documents menu if at least one document is present with current ACLs
        $documents = new Documents($this->zdb, $this->login);
        if ($this->login->isSuperAdmin() || count($documents->getList())) {
            $dashboards[] = new MenuEntry(
                label: _T("Documents"),
                route: new RouteTarget('documentsPublicList'),
                title: _T("View documents related to your association"),
                icon: 'dividers'
            );
        }

        if ($this->access->can('config:preferences', login: $this->login)) {
            $dashboards[] = new MenuEntry(
                label: _T("Settings"),
                route: new RouteTarget('preferences'),
                title: _T("Set applications preferences (address, website, member's cards configuration, ...)"),
                icon: 'control_knobs'
            );
        }

        if ($this->access->can('plugin:manage', login: $this->login)) {
            $plugins_updates = count($this->releases->getPluginsUpdates());
            $dashboards[] = new MenuEntry(
                label: _T("Plugins"),
                route: new RouteTarget('plugins'),
                title: $plugins_updates > 0
                    ? sprintf(
                        //TRANS: %1$d is the number of plugins that have a new release
                        _Tn('%1$d plugin has a new release available', '%1$d plugins have a new release available', $plugins_updates),
                        $plugins_updates
                    ) : _T("Information about available plugins"),
                icon: 'package',
                badge: $plugins_updates > 0 ? $plugins_updates : null
            );
        }

        foreach ($this->plugins->getProviders(Plugins\DashboardProviderInterface::class) as $plugin) {
            array_push($dashboards, ...MenuEntry::listFromArray($plugin->getDashboards()));
        }

        return $dashboards;
    }
}
