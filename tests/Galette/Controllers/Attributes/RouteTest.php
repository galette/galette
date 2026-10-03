<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers\Attributes;

use Galette\Controllers\Attributes\Route;
use PHPUnit\Framework\TestCase;

/**
 * Route attribute tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RouteTest extends TestCase
{
    /**
     * Test Route attribute construction
     */
    public function testRouteConstruction(): void
    {
        $route = new Route(
            name: 'test_route',
            pattern: '/test/{id:\d+}',
            methods: 'GET',
            description: 'Test route',
            requiresAuth: true
        );

        $this->assertSame('test_route', $route->name);
        $this->assertSame('/test/{id:\d+}', $route->pattern);
        $this->assertSame('GET', $route->methods);
        $this->assertSame('Test route', $route->description);
        $this->assertTrue($route->requiresAuth);
    }

    /**
     * Test Route attribute with array of methods
     */
    public function testRouteWithMultipleMethods(): void
    {
        $route = new Route(
            name: 'test_route',
            pattern: '/test',
            methods: ['GET', 'POST']
        );

        $this->assertIsArray($route->methods);
        $this->assertSame(['GET', 'POST'], $route->methods);
        $this->assertSame(['GET', 'POST'], $route->getMethods());
    }

    /**
     * Test Route attribute with single method as string
     */
    public function testRouteWithSingleMethod(): void
    {
        $route = new Route(
            name: 'test_route',
            pattern: '/test',
            methods: 'GET'
        );

        $this->assertIsString($route->methods);
        $this->assertSame('GET', $route->methods);
        $this->assertSame(['GET'], $route->getMethods());
    }

    /**
     * Test Route attribute with default values
     */
    public function testRouteWithDefaults(): void
    {
        $route = new Route(
            name: 'test_route',
            pattern: '/test'
        );

        $this->assertSame('GET', $route->methods);
        $this->assertNull($route->description);
        $this->assertTrue($route->requiresAuth);
    }

    /**
     * Test Route attribute without authentication requirement
     */
    public function testRouteWithoutAuth(): void
    {
        $route = new Route(
            name: 'public_route',
            pattern: '/public',
            requiresAuth: false
        );

        $this->assertFalse($route->requiresAuth);
    }

    /**
     * Test Route attribute can be used as PHP attribute
     */
    public function testRouteAsAttribute(): void
    {
        $reflection = new \ReflectionMethod(RouteTestController::class, 'testMethod');
        $attributes = $reflection->getAttributes(Route::class);

        $this->assertCount(1, $attributes);

        $route = $attributes[0]->newInstance();
        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('test_method', $route->name);
        $this->assertSame('/test/method', $route->pattern);
    }

    /**
     * Test Route attribute is repeatable
     */
    public function testRouteIsRepeatable(): void
    {
        $reflection = new \ReflectionMethod(RouteTestController::class, 'multiRouteMethod');
        $attributes = $reflection->getAttributes(Route::class);

        $this->assertCount(2, $attributes);

        $routes = array_map(fn($attr) => $attr->newInstance(), $attributes);

        $this->assertSame('route1', $routes[0]->name);
        $this->assertSame('/route1', $routes[0]->pattern);

        $this->assertSame('route2', $routes[1]->name);
        $this->assertSame('/route2', $routes[1]->pattern);
    }
}

/**
 * Test controller for Route attribute tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RouteTestController //phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
{
    /**
     * A test method
     */
    #[Route(name: 'test_method', pattern: '/test/method')]
    public function testMethod(): void
    {
        //empty
    }

    /**
     * A mutli-route method
     */
    #[Route(name: 'route1', pattern: '/route1')]
    #[Route(name: 'route2', pattern: '/route2')]
    public function multiRouteMethod(): void
    {
        //empty
    }
}
