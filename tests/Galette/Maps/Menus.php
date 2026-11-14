<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Galette\Navigation\Dashboards;
use Galette\Navigation\MainMenu;
use Galette\Navigation\MemberActions;
use Galette\Navigation\MenuEntry;
use Galette\Tests\GaletteTestCase;

/**
 * Maps entries in menus, dashboards and members actions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Menus extends GaletteTestCase
{
    protected int $seed = 20260925101512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->preferences->pref_maps_enabled = false;
        $this->preferences->pref_publicpages_visibility_generic = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
        parent::tearDown();
    }

    /**
     * Get routes names of menus entries, maps related only
     *
     * @return array<string, array<string>>
     */
    private function getMapsRoutes(): array
    {
        $routes = [];
        foreach ($this->container->make(MainMenu::class)->getMenus(public: true) as $section => $menu) {
            foreach ($menu->items as $item) {
                $name = $item->route->name ?? '';
                if (str_starts_with(strtolower($name), 'maps')) {
                    $routes[$section][] = $name;
                }
            }
        }
        return $routes;
    }

    /**
     * Keep maps related entries only
     *
     * @param list<MenuEntry> $entries Entries
     *
     * @return list<MenuEntry>
     */
    private function findMaps(array $entries): array
    {
        return array_values(array_filter(
            $entries,
            fn(MenuEntry $entry): bool => str_starts_with($entry->route->name ?? '', 'maps')
        ));
    }

    /**
     * Log in member one
     */
    private function logMemberOne(): void
    {
        $this->assertTrue($this->login->login($this->dataAdherentOne()['login_adh'], $this->dataAdherentOne()['mdp_adh']));
    }

    /**
     * Menus depend on logged-in user, and on maps being enabled
     */
    public function testMenus(): void
    {
        $this->getMemberOne();
        $this->preferences->pref_publicpages_visibility_generic = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;

        //disabled: settings only, to enable them
        $this->assertSame([], $this->getMapsRoutes());
        $this->logMemberOne();
        $this->assertSame([], $this->getMapsRoutes());
        $this->login->logout();
        $this->logSuperAdmin();
        $this->assertSame(['configuration' => ['mapsSettings']], $this->getMapsRoutes());
        $this->login->logout();

        $this->preferences->pref_maps_enabled = true;
        $this->assertSame(['public' => ['maps']], $this->getMapsRoutes());

        $this->logMemberOne();
        $this->assertSame(['myaccount' => ['mapsLocalizeMe'], 'public' => ['maps']], $this->getMapsRoutes());
        $this->login->logout();

        //superadmin does not live anywhere
        $this->logSuperAdmin();
        $this->assertSame(['configuration' => ['mapsSettings'], 'public' => ['maps']], $this->getMapsRoutes());
        $this->login->logout();

        //the map has its own public page visibility
        $this->preferences->pref_publicpages_visibility_maps = \Galette\Enums\PublicPageVisibility::Hidden->value;
        try {
            $this->assertSame([], $this->getMapsRoutes());
        } finally {
            $this->preferences->pref_publicpages_visibility_maps = \Galette\Enums\PublicPageVisibility::Inherit->value;
        }
    }

    /**
     * Dashboards and actions
     */
    public function testDashboardsAndActions(): void
    {
        $member = $this->getMemberOne();
        $dashboards = fn(): array => $this->findMaps($this->container->make(Dashboards::class)->getMyEntries());
        $list = fn(): array => $this->findMaps($this->container->make(MemberActions::class)->getListActions($member));
        $detailed = fn(): array => $this->container->make(MemberActions::class)->getDetailedActions($member);

        $this->logMemberOne();
        //counted rather than compared: PHPStan would keep the empty type for the calls below
        $this->assertCount(0, $dashboards());
        $this->assertCount(0, $list());
        $this->assertCount(0, $detailed());

        $this->preferences->pref_maps_enabled = true;
        $entries = $dashboards();
        $this->assertCount(1, $entries);
        $this->assertSame('mapsLocalizeMe', $entries[0]->route?->name);

        $actions = $list();
        $this->assertCount(1, $actions);
        $this->assertSame('mapsLocalizeMember', $actions[0]->route?->name);
        $this->assertSame(['id' => $member->id], $actions[0]->route->args);
        $this->assertSame('Geolocalize ' . $member->sname, $actions[0]->title);
        //opened in a modal by pages loading maps libraries
        $this->assertSame('maps-localize', $actions[0]->extraClass);
        $this->assertEquals($actions, $detailed());
        $this->login->logout();

        //superadmin does not live anywhere
        $this->logSuperAdmin();
        $this->assertSame([], $dashboards());
    }

    /**
     * Members who can be shown but not edited: their position is only looked at
     */
    public function testReadOnlyActions(): void
    {
        $member_two = $this->getMemberTwo();
        $member_one = $this->getMemberOne();
        $group = new \Galette\Entity\Group();
        $group->setName('Maps menus group');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setManagers([$member_two]));
        $this->assertTrue($group->setMembers([$member_one]));
        //groups are read when the member is loaded
        $this->assertTrue($member_one->load((int)$member_one->id));
        $this->preferences->pref_maps_enabled = true;

        $this->assertTrue($this->login->login($this->dataAdherentTwo()['login_adh'], $this->dataAdherentTwo()['mdp_adh']));
        $this->assertTrue($member_one->canShow($this->login));
        $this->assertFalse($member_one->canEdit($this->login));
        $list = fn(): array => $this->findMaps($this->container->make(MemberActions::class)->getListActions($member_one));

        //nothing to set, nothing to show; counted: PHPStan would keep the empty type
        $this->assertCount(0, $list());

        (new \Galette\Maps\Coordinates($this->zdb, $this->login))->set($member_one->id, 48.85, 2.35);
        try {
            $actions = $list();
            $this->assertCount(1, $actions);
            $this->assertSame('mapsLocalizeMember', $actions[0]->route?->name);
            $this->assertSame(_T('Show on map'), $actions[0]->label);
            $this->assertSame(sprintf(_T('Show %1$s on map'), $member_one->sname), $actions[0]->title);
            $this->assertSame('maps-localize', $actions[0]->extraClass);
        } finally {
            $this->zdb->execute($this->zdb->delete(\Galette\Maps\Positions::TABLE));
        }
    }
}
