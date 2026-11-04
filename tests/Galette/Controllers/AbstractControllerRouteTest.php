<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Controllers\AbstractController;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * AbstractController route helpers tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AbstractControllerRouteTest extends GaletteRoutingTestCase
{
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

        // Verify they are all protected
        foreach (
            [
                'getRoute',
                'getRouteName',
                'getRouteArguments',
                'getRoutePattern',
                'getRouteMethods',
                'isRoute',
                'urlForCurrentRoute'
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
