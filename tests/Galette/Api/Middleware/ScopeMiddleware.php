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

namespace Galette\Tests\Api\Middleware;

use Galette\Tests\BaseGaletteTestCase;

/**
 * Galette API scope middleware tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScopeMiddleware extends BaseGaletteTestCase
{
    public function testInvokeReturns403WhenScopeIsMissing()
    {
        $middleware = new \Galette\Api\Middleware\ScopeMiddleware('admin:all');
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/api/admin');

        // On simule un utilisateur qui n'a que le scope 'profile:read'
        $request = $request->withAttribute('user_scopes', ['profile:read']);

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware($request, $handler);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Scope insuffisant', (string)$response->getBody());
    }
}
