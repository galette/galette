<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\Preferences;

/**
 * Public pages menu
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PublicMenu
{
    /**
     * Constructor
     *
     * @param Login       $login       Login instance
     * @param Preferences $preferences Preferences instance
     * @param Plugins     $plugins     Plugins instance
     */
    public function __construct(
        private readonly Login $login,
        private readonly Preferences $preferences,
        private readonly Plugins $plugins
    ) {
    }

    /**
     * Get public menus
     *
     * @return array<string, Menu>
     */
    public function getMenus(): array
    {
        if (!$this->preferences->arePublicPagesEnabled()) {
            return [];
        }

        $items = [];
        $galleries = [];
        $lists = [];

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_memberslist')) {
            $lists[] = new MenuEntry(
                label: _T("Members"),
                route: new RouteTarget('publicMembersList'),
                icon: 'address book'
            );
        }

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_membersgallery')) {
            $galleries[] = new MenuEntry(
                label: _T('Gallery'),
                route: new RouteTarget('publicMembersGallery'),
                icon: 'images'
            );
        }

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_stafflist')) {
            $lists[] = new MenuEntry(
                label: _T("Staff"),
                route: new RouteTarget('publicStaffList'),
                icon: 'address book outline'
            );
        }

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_staffgallery')) {
            $galleries[] = new MenuEntry(
                label: _T('Staff gallery'),
                route: new RouteTarget('publicStaffGallery'),
                icon: 'images outline'
            );
        }

        if (count($lists) > 1) {
            //handle multiple lists
            $items[] = new MenuEntry(label: _T("Directories"), icon: 'address book', children: $lists);
        } else {
            array_push($items, ...$lists);
        }

        if (count($galleries) > 1) {
            //handle multiple galleries
            $items[] = new MenuEntry(label: _T("Galleries"), icon: 'images', children: $galleries);
        } else {
            array_push($items, ...$galleries);
        }

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_documents')) {
            $items[] = new MenuEntry(
                label: _T("Documents"),
                route: new RouteTarget('documentsPublicList'),
                title: _T("View documents related to your association"),
                icon: 'file alternate'
            );
        }

        if (
            $this->preferences->pref_maps_enabled
            && $this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_maps')
        ) {
            $items[] = new MenuEntry(
                label: _T("Maps"),
                route: new RouteTarget('maps'),
                title: _T("See where members live"),
                icon: 'map'
            );
        }

        foreach ($this->plugins->getProviders(Plugins\MenuProviderInterface::class) as $plugin) {
            array_push($items, ...MenuEntry::listFromArray($plugin->getPublicMenuItems()));
        }

        if ($items === []) {
            return [];
        }

        return ['public' => new Menu(_T("Public pages"), 'eye outline', $items)];
    }
}
