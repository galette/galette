<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Tests\GaletteRoutingTestCase;
use Galette\Util\Release;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Plugins controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AllowMockObjectsWithoutExpectations]
class PluginsControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20261025093000;

    /**
     * Set up a releases checker reporting a new release of test2 plugin,
     * and no release for test1 plugin
     */
    private function setUpRelease(): void
    {
        $release = $this->getMockBuilder(Release::class)
            ->setConstructorArgs([true])
            ->onlyMethods(['getPluginsUpdates', 'getPluginsReleasesStatus'])
            ->getMock();
        $release->method('getPluginsUpdates')->willReturn([
            'plugin-test2' => [
                'name' => 'Galette Test2 Plugin',
                'current' => '1.0',
                'latest' => '1.1.0',
                'url' => 'https://github.com/galette-plugins/plugin-test2/releases/tag/1.1.0'
            ]
        ]);
        $release->method('getPluginsReleasesStatus')->willReturn([
            'plugin-test1' => [
                'repository' => 'galette/plugin-test-one',
                'status' => Release::STATUS_NOT_FOUND
            ],
            'plugin-test2' => [
                'repository' => 'galette-plugins/plugin-test2',
                'status' => Release::STATUS_FOUND
            ]
        ]);

        /** @var \DI\Container $container */
        $container = $this->app->getContainer();
        $container->set(Release::class, $release);
    }

    /**
     * Plugins list shows new releases, and plugins whose releases cannot be checked
     */
    public function testPluginsListReleases(): void
    {
        $this->logSuperAdmin();
        $this->setUpRelease();

        $request = $this->createRequest('plugins');
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();

        $this->assertStringContainsString(
            'href="https://github.com/galette-plugins/plugin-test2/releases/tag/1.1.0"',
            $body
        );
        $this->assertStringContainsString('New version: 1.1.0', $body);
        $this->assertStringContainsString(
            'Unable to check for new releases: no release found in galette/plugin-test-one repository.',
            $body
        );
        $this->assertStringNotContainsString('galette-plugins/plugin-test2 repository', $body);
    }

    /**
     * Plugin information shows its releases repository
     */
    public function testPluginInfoReleases(): void
    {
        $this->logSuperAdmin();
        $this->setUpRelease();

        $request = $this->createRequest('pluginInfo', ['route' => 'plugin2']);
        $body = (string)$this->app->handle($request)->getBody();
        $this->assertStringContainsString(
            '<a href="https://github.com/galette-plugins/plugin-test2" target="_blank" rel="noopener">galette-plugins/plugin-test2</a>',
            $body
        );
        $this->assertStringContainsString('New version: 1.1.0', $body);

        $request = $this->createRequest('pluginInfo', ['route' => 'plugin1']);
        $body = (string)$this->app->handle($request)->getBody();
        $this->assertStringContainsString(
            'Unable to check for new releases: no release found in galette/plugin-test-one repository.',
            $body
        );
        $this->assertStringNotContainsString('New version:', $body);
    }
}
