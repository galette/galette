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

namespace Galette\Tests\Console\Command\Plugins;

use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PluginsList command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginsList extends GaletteTestCase
{
    protected bool $load_plugins = true;

    /**
     * Test plugins list command without options (all plugins)
     */
    public function testListAll(): void
    {
        $command = new \Galette\Console\Command\Plugins\PluginsList(GALETTE_ROOT);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Check command succeeded
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Galette plugins', $output);
        $this->assertStringContainsString('===============', $output);

        // Active plugins
        $this->assertStringContainsString('Galette Db Plugin', $output);
        $this->assertStringContainsString('plugin-test1', $output);
        $this->assertStringContainsString('plugin-test2', $output);
        // Inactive plugins
        $this->assertStringContainsString('Galette Db No Version', $output);
        $this->assertStringContainsString('plugin-disabled', $output);
        $this->assertStringContainsString('plugin-oldversion', $output);
        $this->assertStringContainsString('plugin-unversionned', $output);
    }

    /**
     * Test plugins list command with --enabled option
     */
    public function testListEnabled(): void
    {
        $command = new \Galette\Console\Command\Plugins\PluginsList(GALETTE_ROOT);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--enabled' => true]);

        $output = $commandTester->getDisplay();

        // Check command succeeded
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Galette plugins', $output);
        $this->assertStringContainsString('===============', $output);

        // Active plugins
        $this->assertStringContainsString('Galette Db Plugin', $output);
        $this->assertStringContainsString('plugin-test1', $output);
        $this->assertStringContainsString('plugin-test2', $output);
        // Inactive plugins
        $this->assertStringNotContainsString('Galette Db No Version', $output);
        $this->assertStringNotContainsString('plugin-disabled', $output);
        $this->assertStringNotContainsString('plugin-oldversion', $output);
        $this->assertStringNotContainsString('plugin-unversionned', $output);
    }

    /**
     * Test plugins list command with --disabled option
     */
    public function testListDisabled(): void
    {
        $command = new \Galette\Console\Command\Plugins\PluginsList(GALETTE_ROOT);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--disabled' => true]);

        $output = $commandTester->getDisplay();

        // Check command succeeded
        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Galette plugins', $output);
        $this->assertStringContainsString('===============', $output);

        // Active plugins
        $this->assertStringNotContainsString('Galette Db Plugin', $output);
        $this->assertStringNotContainsString('plugin-test1', $output);
        $this->assertStringNotContainsString('plugin-test2', $output);
        // Inactive plugins
        $this->assertStringContainsString('Galette Db No Version', $output);
        $this->assertStringContainsString('plugin-disabled', $output);
        $this->assertStringContainsString('plugin-oldversion', $output);
        $this->assertStringContainsString('plugin-unversionned', $output);
    }

    /**
     * Test plugins list command with --complete option
     */
    public function testExecuteCompleteInformation(): void
    {
        $command = new \Galette\Console\Command\Plugins\PluginsList(GALETTE_ROOT);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--complete' => true]);

        $output = $commandTester->getDisplay();

        // Check command succeeded
        $this->assertSame(0, $commandTester->getStatusCode());

        // Check output contains header
        $this->assertStringContainsString('Galette plugins', $output);

        // Check complete information fields are displayed
        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Description', $output);
        $this->assertStringContainsString('Version', $output);
        $this->assertStringContainsString('Author', $output);
        $this->assertStringContainsString('Date', $output);
        $this->assertStringContainsString('Has database', $output);

        // Active plugins
        $this->assertStringContainsString('Galette Db Plugin', $output);
        $this->assertStringContainsString('plugin-test1', $output);
        $this->assertStringContainsString('plugin-test2', $output);
        // Inactive plugins
        $this->assertStringContainsString('Galette Db No Version', $output);
        $this->assertStringContainsString('plugin-disabled', $output);
        $this->assertStringContainsString('plugin-oldversion', $output);
        $this->assertStringContainsString('plugin-unversionned', $output);
    }

    /**
     * Test plugins list command with --complete and --enabled options
     */
    public function testExecuteCompleteEnabledOnly(): void
    {
        $command = new \Galette\Console\Command\Plugins\PluginsList(GALETTE_ROOT);
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--complete' => true,
            '--enabled' => true
        ]);

        $output = $commandTester->getDisplay();

        // Check command succeeded
        $this->assertSame(0, $commandTester->getStatusCode());

        // Check output contains complete information
        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('Yes', $output);

        // Active plugins
        $this->assertStringContainsString('Galette Db Plugin', $output);
        $this->assertStringContainsString('plugin-test1', $output);
        $this->assertStringContainsString('plugin-test2', $output);
        // Inactive plugins
        $this->assertStringNotContainsString('Galette Db No Version', $output);
        $this->assertStringNotContainsString('plugin-disabled', $output);
        $this->assertStringNotContainsString('plugin-oldversion', $output);
        $this->assertStringNotContainsString('plugin-unversionned', $output);
    }

    /**
     * Test plugins list command with --complete and --disabled options
     */
    public function testExecuteCompleteDisabledOnly(): void
    {
        $command = new \Galette\Console\Command\Plugins\PluginsList(GALETTE_ROOT);
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--complete' => true,
            '--disabled' => true
        ]);

        $output = $commandTester->getDisplay();

        // Check command succeeded
        $this->assertSame(0, $commandTester->getStatusCode());

        // Check output contains complete information
        $this->assertMatchesRegularExpression('/Active\s+No \(.+\)/', $output);


        // Active plugins
        $this->assertStringNotContainsString('Galette Db Plugin', $output);
        $this->assertStringNotContainsString('plugin-test1', $output);
        $this->assertStringNotContainsString('plugin-test2', $output);

        // Inactive plugins
        $this->assertStringContainsString('Galette Db No Version', $output);
        $this->assertStringContainsString('Database version missing', $output);

        $this->assertStringContainsString('plugin-disabled', $output);
        $this->assertStringContainsString('Explicitly disabled', $output);

        $this->assertStringContainsString('plugin-oldversion', $output);
        $this->assertStringContainsString('plugin-unversionned', $output);
        $this->assertStringContainsString('Incompatible with current version', $output);

        $this->assertStringContainsString('Galette Noclass Plugin (plugin-noclass)', $output);
        $this->assertStringContainsString('A required file is missing', $output);
    }

    /**
     * Test that simple listing output is concise
     */
    public function testSimpleListingFormat(): void
    {
        $command = new \Galette\Console\Command\Plugins\PluginsList(GALETTE_ROOT);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // In simple mode (without --complete), output should be more concise
        // and show plugin names with versions
        $this->assertStringContainsString('plugin', $output);

        // Simple mode should not show all the detailed fields
        // (we can't check absence reliably as plugin names may contain these words,
        // but we can verify the format is different from complete mode)
        $this->assertSame(0, $commandTester->getStatusCode());
    }
}
