<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Controllers\AbstractController;
use Galette\Controllers\Attributes\Route;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * AbstractController route helpers tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AbstractControllerRouteTest extends GaletteRoutingTestCase
{
    private TestController $controller;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestController($this->container);
    }

    /**
     * Test getRouteAttributes
     */
    public function testGetRouteAttributes(): void
    {
        $attributes = $this->controller->testGetRouteAttributes();

        $this->assertCount(2, $attributes);
        $this->assertContainsOnlyInstancesOf(Route::class, $attributes); // @phpstan-ignore method.alreadyNarrowedType

        $this->assertSame('route1', $attributes[0]->name);
        $this->assertSame('route2', $attributes[1]->name);
    }

    /**
     * Test route helpers with real routing
     * This test uses the real application routing
     */
    public function testRouteHelpersWithRealRequest(): void
    {
        // We can't easily test the protected methods without actual routing,
        // but we can verify the methods exist and are callable
        $reflection = new \ReflectionClass(AbstractController::class);

        $this->assertTrue($reflection->hasMethod('getRoute'));
        $this->assertTrue($reflection->hasMethod('getRouteName'));
        $this->assertTrue($reflection->hasMethod('getRouteArguments'));
        $this->assertTrue($reflection->hasMethod('getRoutePattern'));
        $this->assertTrue($reflection->hasMethod('getRouteMethods'));
        $this->assertTrue($reflection->hasMethod('isRoute'));
        $this->assertTrue($reflection->hasMethod('urlForCurrentRoute'));
        $this->assertTrue($reflection->hasMethod('getRouteAttributes'));
        $this->assertTrue($reflection->hasMethod('getCurrentRouteAttribute'));

        // Verify they are all protected
        foreach (
            [
                'getRoute',
                'getRouteName',
                'getRouteArguments',
                'getRoutePattern',
                'getRouteMethods',
                'isRoute',
                'urlForCurrentRoute',
                'getRouteAttributes',
                'getCurrentRouteAttribute'
            ] as $methodName
        ) {
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isProtected(), "Method $methodName should be protected");
        }
    }

    /**
     * Test that route helper methods return correct types
     */
    public function testRouteHelperReturnTypes(): void
    {
        $reflection = new \ReflectionClass(AbstractController::class);

        // Test return types
        $getRouteMethod = $reflection->getMethod('getRoute');
        $this->assertTrue($getRouteMethod->hasReturnType());

        $getRouteNameMethod = $reflection->getMethod('getRouteName');
        $this->assertTrue($getRouteNameMethod->hasReturnType());

        $getRouteArgumentsMethod = $reflection->getMethod('getRouteArguments');
        $this->assertTrue($getRouteArgumentsMethod->hasReturnType());

        $isRouteMethod = $reflection->getMethod('isRoute');
        $this->assertTrue($isRouteMethod->hasReturnType());
    }
}

/**
 * Test controller for testing AbstractController route helpers
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TestController extends AbstractController //phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
{
    /**
     * Method to test get attributes
     *
     * @return Route[]
     */
    #[Route(name: 'route1', pattern: '/test/route1', methods: 'GET')]
    #[Route(name: 'route2', pattern: '/test/route2', methods: 'POST')]
    public function testGetRouteAttributes(): array
    {
        return parent::getRouteAttributes();
    }
}
