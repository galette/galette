<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Plugins tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Plugins extends GaletteTestCase
{
    /** @var array<string, mixed> */
    private array $plugin2 = [
        'root'          => 'plugin-test2',
        'name'          => 'Galette Test2 Plugin',
        'desc'          => 'Test two plugin',
        'author'        => 'Johan Cwiklinski',
        'version'       => '1.0',
        'acls'          => [
            'plugin2_root'  => 'member',
            'plugin2_admin' => 'staff'
        ],
        'date'          => '2013-12-15',
        'priority'      => 1000,
        'route'         => 'plugin2'
    ];

    /**
     * Get instantiated plugins instance
     */
    private function getPlugins(): \Galette\Core\Plugins
    {
        $plugins = new \Galette\Core\Plugins();
        $plugins->autoload(GALETTE_PLUGINS_PATH);
        $plugins->loadModules($this->preferences, GALETTE_PLUGINS_PATH);
        return $plugins;
    }

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->plugins = $this->getPlugins();
        $this->plugin2['root'] = GALETTE_PLUGINS_PATH
            . $this->plugin2['root'];
    }

    /**
     * Tests plugins load
     */
    public function testLoadModules(): void
    {
        $this->getPlugins();
        $this->assertCount(4, $this->plugins->getModules());

        $loaded_plugin = $this->plugins->getModules('plugin-test2');
        $loaded_plugin['date'] = $this->plugin2['date'];

        $this->assertSame($this->plugin2, $loaded_plugin);
    }

    /**
     * Test module existence
     */
    public function testModuleExists(): void
    {
        $this->assertTrue($this->plugins->moduleExists('plugin-test2'));
        $this->assertFalse($this->plugins->moduleExists('plugin-disabled'));
    }

    /**
     * Data provider for disabled modules test
     *
     * @return array<int, array{module: string, cause: int}>
     */
    public static function disabledModulesProvider(): array
    {
        return [
            [
                'module' => 'plugin-disabled',
                'cause' => \Galette\Core\Plugins::DISABLED_EXPLICIT],
            [
                'module' => 'plugin-unversionned',
                'cause' =>  \Galette\Core\Plugins::DISABLED_COMPAT
            ],
            [
                'module' => 'plugin-oldversion',
                'cause' =>  \Galette\Core\Plugins::DISABLED_COMPAT
            ],
            [
                'module' => 'plugin-news',
                'cause' =>  \Galette\Core\Plugins::DISABLED_EXPLICIT
            ],
            [
                'module' => 'plugin-noclass',
                'cause' =>  \Galette\Core\Plugins::DISABLED_MISS
            ]
        ];
    }

    /**
     * Test disabled plugin
     */
    #[DataProvider('disabledModulesProvider')]
    public function testDisabledModules(string $module, int $cause): void
    {
        $disabled_modules = $this->plugins->getDisabledModules();
        $this->assertTrue(isset($disabled_modules[$module]));
        $this->assertSame($cause, $disabled_modules[$module]['cause']);
    }

    /**
     * Test module root
     */
    public function testModuleRoot(): void
    {
        $this->assertSame($this->plugin2['root'], $this->plugins->moduleRoot('plugin-test2'));
    }

    /**
     * Test reset modules list
     */
    public function testResetModulesList(): void
    {
        $this->plugins->resetModulesList();

        $this->assertEmpty($this->plugins->getModules());
    }

    /**
     * Test plugin (des)activation
     */
    public function testModuleActivation(): void
    {
        $plugins = $this->getPlugins();
        $modules = $plugins->getModules();
        $this->assertCount(4, $modules);
        $this->assertTrue(isset($modules['plugin-test2']));
        $plugins->deactivateModule('plugin-test2');

        $plugins = $this->getPlugins();
        $modules = $plugins->getModules();
        $this->assertCount(3, $modules);
        $this->assertFalse(isset($modules['plugin-test2']));
        $plugins->activateModule('plugin-test2');

        $plugins = $this->getPlugins();
        $modules = $plugins->getModules();
        $this->assertCount(4, $modules);
        $this->assertTrue(isset($modules['plugin-test2']));
    }

    /**
     * Test non-existant module activation
     */
    public function testNonExistantModuleActivation(): void
    {
        $plugins = $this->getPlugins();
        $this->expectExceptionMessage(_T('No such module.'));
        $plugins->activateModule('nonexistant');
    }

    /**
     * Test non-existant module de-activation
     */
    public function testNonExistantModuleDeactivation(): void
    {
        $plugins = $this->getPlugins();
        $this->expectExceptionMessage(_T('No such module.'));
        $plugins->deactivateModule('nonexistant');
    }

    /**
     * Test if plugin needs database
     */
    public function testNeedDatabse(): void
    {
        $this->assertTrue($this->plugins->needsDatabase('plugin-db'));
        $this->assertTrue($this->plugins->needsDatabase('plugin-db-noversion'));
        $this->assertFalse($this->plugins->needsDatabase('plugin-test2'));

        $plugins = $this->getPlugins();
        $this->expectExceptionMessage('Module nonexistant does not exists!');
        $plugins->needsDatabase('nonexistant');
    }
}
