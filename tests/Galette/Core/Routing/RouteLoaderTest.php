<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core\Routing;

use Galette\Access\Permissions;
use Galette\Core\Plugins;
use Galette\Core\Routing\RouteLoader;
use Galette\Core\SlimApp;
use Galette\Middleware\Authenticate;
use Galette\Tests\GaletteTestCase;

/**
 * Routes loading from attributes tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RouteLoaderTest extends GaletteTestCase
{
    protected bool $load_plugins = true;

    /**
     * Every route requiring authentication must have an ACL, a known level or
     * permission, or Authenticate throws when the route is called.
     */
    public function testAuthenticatedRoutesHaveAcl(): void
    {
        $authenticate = $this->container->get(Authenticate::class);
        $permissions = $this->container->get(Permissions::class);
        $levels = ['superadmin', 'admin', 'staff', 'groupmanager', 'member'];

        $routes = (new RouteLoader())->collect(GALETTE_ROOT . 'lib/Galette/Controllers', 'Galette\\Controllers');
        $this->assertGreaterThan(200, count($routes));

        $missing = [];
        foreach ($routes as $entry) {
            if (!$entry['route']->requiresAuth) {
                continue;
            }
            try {
                $acl = $authenticate->getAclFor($entry['route']->name);
                if (in_array($acl, $levels, strict: true)) {
                    continue;
                }
                //or permissions, any of them granting access
                foreach (explode('|', $acl) as $permission) {
                    if (!$permissions->has($permission)) {
                        $missing[] = $entry['route']->name . ' (unknown level or permission ' . $permission . ')';
                    }
                }
            } catch (\RuntimeException) {
                $missing[] = $entry['route']->name;
            }
        }

        $this->assertSame([], $missing, 'Routes requiring authentication without ACL');
    }

    /**
     * Routes are registered with their middlewares
     */
    public function testRegisteredRoutes(): void
    {
        $collector = $this->app->getRouteCollector();

        $route = $collector->getNamedRoute('publicMembersList');
        $this->assertSame('/public/members/list[/{option:page|order}/{value:\d+|\w+}]', $route->getPattern());
        $this->assertSame(['GET'], $route->getMethods());

        //route declared on class for an inherited method
        $route = $collector->getNamedRoute('removeTitle');
        $this->assertSame(
            [\Galette\Controllers\Crud\TitlesController::class, 'confirmDelete'],
            $route->getCallable()
        );

        //debug routes are not registered outside debug mode
        $this->expectException(\RuntimeException::class);
        $collector->getNamedRoute('debugPhpinfo');
    }

    /**
     * Plugins routes come from their controllers attributes, and from their
     * routes file when they have one
     */
    public function testPluginRoutes(): void
    {
        $collector = $this->app->getRouteCollector();

        $route = $collector->getNamedRoute('plugin1_public_page');
        $this->assertSame('/plugins/plugin1/public/page', $route->getPattern());
        $this->assertSame([\GaletteTest1Plugin\Controllers\PublicController::class, 'page'], $route->getCallable());

        $route = $collector->getNamedRoute('plugin1_public_other');
        $this->assertSame('/plugins/plugin1/public/other', $route->getPattern());
    }

    /**
     * Cron tasks do not need ajax routes
     */
    public function testCronRoutes(): void
    {
        $app = (new SlimApp(new Plugins()))->getApp();
        (new RouteLoader())->load($app, cron: true);
        $collector = $app->getRouteCollector();

        $this->assertSame('/', $collector->getNamedRoute('slash')->getPattern());
        $this->expectException(\RuntimeException::class);
        $collector->getNamedRoute('ajaxMessages');
    }

    /**
     * Two routes cannot share a name
     */
    public function testDuplicateNames(): void
    {
        $dir = GALETTE_TESTS_PATH . '/fixtures/routes/duplicates';
        require_once $dir . '/First.php';
        require_once $dir . '/Second.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Route "same" is declared twice');
        (new RouteLoader())->collect($dir, 'Galette\\Tests\\Fixtures\\Routes\\Duplicates');
    }

    /**
     * A route declared on a class must name its handler
     */
    public function testClassRouteWithoutHandler(): void
    {
        $dir = GALETTE_TESTS_PATH . '/fixtures/routes/nohandler';
        require_once $dir . '/Inherited.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must name an existing method as handler');
        (new RouteLoader())->collect($dir, 'Galette\\Tests\\Fixtures\\Routes\\Nohandler');
    }
}
