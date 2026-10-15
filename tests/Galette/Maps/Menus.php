<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Galette\Core\Galette;
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
        foreach (Galette::getMenus(public: true) as $section => $menu) {
            foreach ($menu['items'] as $item) {
                $name = $item['route']['name'] ?? '';
                if (str_starts_with(strtolower($name), 'maps') || str_contains($name, 'MapsSettings')) {
                    $routes[$section][] = $name;
                }
            }
        }
        return $routes;
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
        $find = fn(array $entries): array => array_values(array_filter(
            $entries,
            fn(array $entry): bool => str_starts_with($entry['route']['name'], 'maps')
        ));

        $this->logMemberOne();
        //counted rather than compared: PHPStan would keep the empty type for the calls below
        $this->assertCount(0, $find(Galette::getMyDashboards()));
        $this->assertCount(0, $find(Galette::getListActions($member)));
        $this->assertCount(0, Galette::getDetailedActions($member));

        $this->preferences->pref_maps_enabled = true;
        $dashboards = $find(Galette::getMyDashboards());
        $this->assertCount(1, $dashboards);
        $this->assertSame(['name' => 'mapsLocalizeMe'], $dashboards[0]['route']);

        $actions = $find(Galette::getListActions($member));
        $this->assertCount(1, $actions);
        $this->assertSame(
            ['name' => 'mapsLocalizeMember', 'args' => ['id' => $member->id]],
            $actions[0]['route']
        );
        $this->assertSame('Geolocalize ' . $member->sname, $actions[0]['title']);
        $this->assertSame($actions, Galette::getDetailedActions($member));
        $this->login->logout();

        //superadmin does not live anywhere
        $this->logSuperAdmin();
        $this->assertSame([], $find(Galette::getMyDashboards()));
    }
}
