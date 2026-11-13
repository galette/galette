<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Navigation;

use Galette\Core\GalettePlugin;
use Galette\Core\Plugins\MenuProviderInterface;
use Galette\Navigation\MainMenu;
use Galette\Navigation\MenuEntry;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Main menu tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MainMenuTest extends GaletteTestCase
{
    protected int $seed = 20230324120838;
    protected bool $load_plugins = true;

    /**
     * New releases notices
     */
    private function releases(): \Galette\Navigation\ReleaseNotices
    {
        return new \Galette\Navigation\ReleaseNotices(
            fn(): \Galette\Util\Release => $this->container->get(\Galette\Util\Release::class)
        );
    }

    /**
     * Public menu built from the globals tests replace with mocks
     *
     * @phpstan-impure
     */
    private function publicMenu(): \Galette\Navigation\PublicMenu
    {
        global $login, $preferences, $plugins;
        return new \Galette\Navigation\PublicMenu($login, $preferences, $plugins);
    }

    /**
     * Main menu built from the globals tests replace with mocks
     *
     * @phpstan-impure
     */
    private function mainMenu(): \Galette\Navigation\MainMenu
    {
        global $login, $preferences, $plugins;
        return new \Galette\Navigation\MainMenu(
            login: $login,
            preferences: $preferences,
            plugins: $plugins,
            access: $this->container->get(\Galette\Access\AccessControl::class),
            releases: $this->releases(),
            publicMenu: $this->publicMenu()
        );
    }

    /**
     * Test getMenus
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetMenus(): void
    {
        global $preferences, $login, $plugins;
        $db = new \Galette\Core\Db();
        $plugins = new \Galette\Core\Plugins();  //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- global
        $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
            ->setConstructorArgs([$db])
            ->onlyMethods(['arePublicPagesEnabled', 'showPublicPage'])
            ->getMock();

        $preferences->method('arePublicPagesEnabled')->willReturn(true);
        $preferences->method('showPublicPage')->willReturn(true);

        $menus = $this->mainMenu()->getMenus();
        $this->assertCount(0, $menus);

        $menus = $this->mainMenu()->getMenus(public: true);
        $this->assertCount(1, $menus);
        $this->assertArrayHasKey('public', $menus);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $menus = $this->mainMenu()->getMenus(public: true);
        $this->assertCount(6, $menus);

        $this->assertArrayHasKey('myaccount', $menus);
        $this->assertArrayHasKey('members', $menus);
        $this->assertArrayHasKey('contributions', $menus);
        $this->assertArrayHasKey('management', $menus);
        $this->assertArrayHasKey('configuration', $menus);
        $this->assertArrayHasKey('public', $menus);

        $this->assertSame(
            [
                'preferences',
                'mapsSettings',
                'plugins',
                'configureListFields',
                'configureCoreFields',
                'configureDynamicFields',
                'contributionsTypes',
                'paymentTypes',
                'status',
                'titles',
                'pdfModels',
                'texts',
                'dynamicTranslations',
                'roles'
            ],
            array_column(array_column($menus['configuration']->items, 'route'), 'name')
        );
        $this->assertSame(
            ['groups', 'documentsList', 'mailings', 'charts', 'export', 'import', 'authAttempts', 'history'],
            array_column(array_column($menus['management']->items, 'route'), 'name')
        );

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $menus = $this->mainMenu()->getMenus(public: true);
        $this->assertCount(6, $menus);

        $this->assertArrayHasKey('myaccount', $menus);
        $this->assertArrayHasKey('members', $menus);
        $this->assertArrayHasKey('contributions', $menus);
        $this->assertArrayHasKey('management', $menus);
        $this->assertArrayHasKey('configuration', $menus);
        $this->assertArrayHasKey('public', $menus);
        $this->assertSame(
            ['groups', 'documentsList', 'mailings', 'charts', 'export', 'import', 'history'],
            array_column(array_column($menus['management']->items, 'route'), 'name')
        );
        //staff members get configuration pages their role allows
        $this->assertSame(
            ['contributionsTypes', 'paymentTypes', 'status', 'titles', 'pdfModels', 'texts', 'dynamicTranslations'],
            array_column(array_column($menus['configuration']->items, 'route'), 'name')
        );

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $menus = $this->mainMenu()->getMenus(public: true);
        $this->assertCount(6, $menus);

        $this->assertArrayHasKey('myaccount', $menus);
        $this->assertArrayHasKey('members', $menus);
        $this->assertArrayHasKey('contributions', $menus);
        $this->assertArrayHasKey('management', $menus);
        $this->assertArrayHasKey('configuration', $menus);
        $this->assertArrayHasKey('public', $menus);

        //the super administrator holds a second factor of its own, kept in
        //preferences no form may write: without this entry, that account has
        //no way to enrol from the interface
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $menus = $this->mainMenu()->getMenus(public: true);
        $this->assertCount(2, $menus['myaccount']->items);
        $this->assertSame(
            'adminCredentials',
            $menus['myaccount']->items[0]->route?->name
        );
        $this->assertSame(
            'two-factor-manage',
            $menus['myaccount']->items[1]->route?->name
        );
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_DISABLED;
    }

    /**
     * Plugins add entries to core menus, and menus of their own
     */
    public function testPluginsMenus(): void
    {
        $plugin = new class extends GalettePlugin implements MenuProviderInterface {
            /**
             * Get plugins menus
             *
             * @return array<string, string|array<string,mixed>>
             */
            public function getMenus(): array
            {
                return [
                    'myaccount' => [
                        'items' => [['label' => 'My map', 'route' => ['name' => 'maps_mymap']]]
                    ],
                    'plugin' => [
                        'title' => 'Plugin',
                        'icon' => 'puzzle piece',
                        'items' => [['label' => 'Plugin page', 'route' => ['name' => 'plugin_page']]]
                    ],
                    'empty' => [
                        'title' => 'Empty',
                        'icon' => 'circle',
                        'items' => []
                    ]
                ];
            }

            /**
             * Get plugins public menus
             *
             * @return array<int, string|array<string,mixed>>
             */
            public function getPublicMenus(): array
            {
                return [];
            }

            /**
             * Is plugin fully installed?
             */
            public function isInstalled(): bool
            {
                return true;
            }
        };
        $plugins = $this->createStub(\Galette\Core\Plugins::class);
        $plugins->method('getProviders')->willReturnCallback(
            fn(string $interface): array => $plugin instanceof $interface ? [$plugin] : []
        );

        $this->logSuperAdmin();
        $this->preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $menus = (new MainMenu(
            login: $this->login,
            preferences: $this->preferences,
            plugins: $plugins,
            access: $this->container->get(\Galette\Access\AccessControl::class),
            releases: $this->releases(),
            publicMenu: new \Galette\Navigation\PublicMenu($this->login, $this->preferences, $plugins)
        ))->getMenus();
        $this->preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_DISABLED;
        $this->login->logOut();

        //plugin entry is added before the ones the core keeps at the end
        $this->assertSame(
            ['adminCredentials', 'maps_mymap', 'two-factor-manage'],
            array_map(fn(MenuEntry $item): ?string => $item->route?->name, $menus['myaccount']->items)
        );
        $this->assertSame('My Account', $menus['myaccount']->title);
        //plugin menu comes after core ones, an empty one is dropped
        $this->assertSame(
            ['myaccount', 'members', 'contributions', 'management', 'configuration', 'plugin'],
            array_keys($menus)
        );
        $this->assertSame('Plugin', $menus['plugin']->title);
        $this->assertSame('puzzle piece', $menus['plugin']->icon);
        $this->assertSame(['plugin_page'], $menus['plugin']->getRouteNames());
    }
}
