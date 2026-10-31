<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\CheckRoutes;
use Galette\Tests\GaletteTestCase;
use Slim\Factory\AppFactory;
use Symfony\Component\Console\Application;
use Slim\Interfaces\RouteCollectorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Safe\preg_replace;

/**
 * Routes naming conventions check command tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CheckRoutesTest extends GaletteTestCase
{
    /**
     * Get a routes collector holding given routes
     *
     * @param array<string, ?string> $routes Pattern => name
     */
    private function getCollector(array $routes): RouteCollectorInterface
    {
        $app = AppFactory::create();
        foreach ($routes as $pattern => $name) {
            $route = $app->get($pattern, fn() => null);
            if ($name !== null) {
                $route->setName($name);
            }
        }
        return $app->getRouteCollector();
    }

    /**
     * Run the command
     *
     * @param array<string, ?string> $routes    Pattern => name
     * @param array<string, mixed>   $arguments Command arguments and options
     * @param array<string, mixed>   $options   Tester options
     */
    private function runCommand(array $routes, array $arguments = [], array $options = []): CommandTester
    {
        $command = new CheckRoutes($this->getCollector($routes), GALETTE_ROOT, ['maps']);
        //verbose option is an application one
        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($command);
        $tester->execute($arguments, $options);
        return $tester;
    }

    /**
     * Get display, on a single line
     *
     * @param CommandTester $tester Command tester
     */
    private function getDisplay(CommandTester $tester): string
    {
        return preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /**
     * Routes following conventions, legacy ones included
     */
    public function testValidRoutes(): void
    {
        $routes = [
            '/' => 'slash',
            '/members' => 'members.list',
            '/members/{id:\d+}/edit' => 'members.edit',
            '/members/edit[/{id:\d+}]' => 'editMember',
            '/plugins/maps/show' => 'maps.show',
            '/plugins/{plugin}/res/{path:.*}' => 'plugin_res'
        ];
        $tester = $this->runCommand($routes);
        $tester->assertCommandIsSuccessful();

        $display = $this->getDisplay($tester);
        $this->assertStringContainsString('6 checked routes.', $display);
        //nothing to report, no table
        $this->assertStringNotContainsString('Methods', $display);

        //verbose mode lists every route
        $tester = $this->runCommand($routes, ['--verbose' => true]);
        $tester->assertCommandIsSuccessful();
        $display = $this->getDisplay($tester);
        $this->assertStringContainsString('Methods', $display);
        $this->assertStringContainsString('members.edit', $display);
    }

    /**
     * Routes breaking conventions are reported
     */
    public function testInvalidRoutes(): void
    {
        $routes = [
            '/members' => 'members.list',
            '/Bad_URL/' => 'badurl',
            '/unnamed' => null,
            '/plugins/maps/list' => 'other.list',
            '/plugins/maps/show' => 'maps.show',
            '/wrong-name' => 'wrong name',
        ];
        $tester = $this->runCommand($routes);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());

        $display = $this->getDisplay($tester);
        $this->assertStringContainsString(
            '6 checked routes, 2 names errors, 1 URLs errors, 1 warnings',
            $display
        );
        $this->assertStringContainsString('Not named route: [GET] /unnamed', $display);
        $this->assertStringContainsString('❌ other.list', $display);
        $this->assertStringContainsString('❌ wrong name', $display);
        $this->assertStringContainsString('❌ /Bad_URL/', $display);
        //valid routes are not listed
        $this->assertStringNotContainsString('members.list', $display);

        //names only
        $tester = $this->runCommand($routes, ['--no-urls' => true]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(
            '6 checked routes, 2 names errors, 1 warnings',
            $this->getDisplay($tester)
        );

        //URLs only
        $tester = $this->runCommand($routes, ['--no-names' => true]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(
            '6 checked routes, 1 URLs errors',
            $this->getDisplay($tester)
        );

        //plugins routes can be left out
        $tester = $this->runCommand($routes, ['--no-plugins' => true]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(
            '4 checked routes, 1 names errors, 1 URLs errors, 1 warnings',
            $this->getDisplay($tester)
        );
    }

    /**
     * There must be something to check
     */
    public function testNothingToCheck(): void
    {
        $tester = $this->runCommand(['/' => 'slash'], ['--no-names' => true, '--no-urls' => true]);
        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString(
            'Nothing to check, at least one of --names or --urls must be set.',
            $this->getDisplay($tester)
        );
    }
}
