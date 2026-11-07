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
use Galette\Core\Plugins\PublicPagesProviderInterface;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Public menu tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PublicMenuTest extends GaletteTestCase
{
    protected int $seed = 20230324120838;
    protected bool $load_plugins = true;

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
     * Test getPublicMenus
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetPublicMenus(): void
    {
        global $preferences;

        $db = new \Galette\Core\Db();

        //public pages are disabled
        $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
            ->setConstructorArgs([$db])
            ->onlyMethods(['arePublicPagesEnabled', 'showPublicPage'])
            ->getMock();
        $preferences->method('arePublicPagesEnabled')->willReturn(false);
        $preferences->method('showPublicPage')->willReturn(true); //should not matter.

        $menus = $this->publicMenu()->getMenus();
        $this->assertCount(0, $menus, print_r($menus, return: true));

        //public pages are enabled but not shown
        $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
            ->setConstructorArgs([$db])
            ->onlyMethods(['arePublicPagesEnabled', 'showPublicPage'])
            ->getMock();
        $preferences->method('arePublicPagesEnabled')->willReturn(true);
        $preferences->method('showPublicPage')->willReturn(false);

        //public pages are enabled and shown
        $menus = $this->publicMenu()->getMenus();
        $this->assertCount(0, $menus, print_r($menus, return: true));

        $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
            ->setConstructorArgs([$db])
            ->onlyMethods(['arePublicPagesEnabled', 'showPublicPage'])
            ->getMock();
        $preferences->method('arePublicPagesEnabled')->willReturn(true);
        $preferences->method('showPublicPage')->willReturn(true);

        $menus = $this->publicMenu()->getMenus();
        $this->assertCount(1, $menus);
        $menu = $menus['public'];
        $this->assertSame('Public pages', $menu->title);
        $this->assertSame(['Directories', 'Galleries', 'Documents'], array_column($menu->items, 'label'));
        //several lists and galleries are grouped
        $this->assertNull($menu->items[0]->route);
        $this->assertSame(['Members', 'Staff'], array_column($menu->items[0]->children, 'label'));
        $this->assertSame(
            ['publicMembersList', 'publicStaffList', 'publicMembersGallery', 'publicStaffGallery', 'documentsPublicList'],
            $menu->getRouteNames()
        );
    }

    /**
     * A plugin declaring its public pages shows each entry as its page allows
     *
     * The routes are the ones plugin-test1 declares or leaves undeclared.
     */
    public function testDeclaredPluginPublicMenus(): void
    {
        //the global preferences and login getPublicMenuItems() reads are the
        //ones setUp() made
        $page = 'pref_plugin1_publicpages_visibility_page';

        $plugin = new class extends GalettePlugin implements MenuProviderInterface, PublicPagesProviderInterface {
            /**
             * Get plugins menus
             *
             * @return array<string, string|array<string,mixed>>
             */
            public function getMenus(): array
            {
                return [];
            }

            /**
             * Get plugins public menus
             *
             * @return array<int, string|array<string,mixed>>
             */
            public function getPublicMenus(): array
            {
                return [
                    ['label' => 'Declared', 'route' => ['name' => 'plugin1_public_page']],
                    ['label' => 'Undeclared', 'route' => ['name' => 'plugin1_public_other']],
                    [
                        'label' => 'Parent',
                        'children' => [
                            ['label' => 'Child', 'route' => ['name' => 'plugin1_public_page']],
                        ],
                    ],
                ];
            }

            /**
             * Get the public pages the plugin declares
             *
             * @return array<string, array{routes: list<string>, default?: int}>
             */
            public function getPublicPages(): array
            {
                return [];
            }

            /**
             * Get the label of a declared public page
             *
             * @param string $id Page identifier
             */
            public function getPublicPageLabel(string $id): string
            {
                return $id;
            }
        };

        $labels = fn(): array => array_column($plugin->getPublicMenuItems(), 'label');

        $this->preferences->pref_bool_publicpages = true;
        $this->preferences->pref_publicpages_visibility_generic = \Galette\Enums\PublicPageVisibility::Hidden->value;
        $this->assertTrue(
            $this->preferences->setValue($page, \Galette\Enums\PublicPageVisibility::Everyone->value, $this->login)
        );

        try {
            //the default visibility no longer hides everything
            $this->assertSame(['Declared', 'Parent'], $labels());

            //a parent left without children goes too
            $this->preferences->setValue($page, \Galette\Enums\PublicPageVisibility::Hidden->value, $this->login);
            $this->assertSame([], $labels());

            $this->preferences->pref_publicpages_visibility_generic = \Galette\Enums\PublicPageVisibility::Everyone->value;
            $this->assertSame(['Undeclared'], $labels());
        } finally {
            $this->preferences->resetValue($page, $this->login);
            $this->preferences->load();
        }
    }
}
