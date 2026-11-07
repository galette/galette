<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Controllers\DebugController;
use Galette\Core\Galette;
use Galette\Tests\GaletteRoutingTestCase;
use Slim\CallableResolver;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Response;
use Slim\Routing\RouteCollector;

/**
 * Debug pages tests
 *
 * Tests do not run in debug mode: pages are called directly. Their routes,
 * declared only in debug mode, are reached by end to end tests.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DebugControllerTest extends GaletteRoutingTestCase
{
    /**
     * Debug pages routes are not declared outside debug mode
     */
    public function testNotDeclaredOutsideDebugMode(): void
    {
        $this->assertFalse(Galette::isDebugEnabled());

        $collector = $this->app->getRouteCollector();
        foreach (['debugPhpinfo', 'debugRoutes'] as $name) {
            try {
                $collector->getNamedRoute($name);
                $this->fail(sprintf('Route "%s" must not be declared outside debug mode', $name));
            } catch (\RuntimeException $e) {
                $this->assertSame('Named route does not exist for name: ' . $name, $e->getMessage());
            }
        }
    }

    /**
     * phpinfo() is written to the response
     */
    public function testPhpinfo(): void
    {
        $response = (new DebugController())->phpinfo(new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('PHP Version', (string)$response->getBody());
    }

    /**
     * Routes are listed, escaped
     */
    public function testRoutes(): void
    {
        $collector = new RouteCollector(new ResponseFactory(), new CallableResolver());
        $collector->map(['GET'], '/listed', fn(Response $response): Response => $response)
            ->setName('listedRoute');
        $collector->map(['GET'], '/<b>{id}</b>', fn(Response $response): Response => $response);

        $response = (new DebugController())->routes(new Response(), $collector);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('route0 → listedRoute → /listed<br><br>', $body);
        $this->assertStringContainsString('route1 → (unnamed) → /&lt;b&gt;{id}&lt;/b&gt;<br><br>', $body);
        $this->assertStringNotContainsString('<b>', $body);
    }
}
