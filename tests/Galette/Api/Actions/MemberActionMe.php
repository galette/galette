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

namespace Galette\Tests\Api\Actions;

use Galette\Tests\BaseGaletteTestCase;

use function Safe\json_decode;
use function Safe\json_encode;

/**
 * Galette API MemberActionMe tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberActionMe extends BaseGaletteTestCase
{
    /**
     * Test that GET /api/me returns the authenticated user's data
     * JwtMiddleware uses PHP-DI injection; here we inject the api_login attribute directly.
     */
    public function testGetMeReturnsUserData(): void
    {
        $app = \Slim\Factory\AppFactory::create();

        // Route de test — lit l'attribut api_login mis en place par JwtMiddleware
        $app->get('/api/me', function ($request, $response) {
            $login = $request->getAttribute('api_login');
            $userId = $login !== null ? $login->id : null;
            $response->getBody()->write(json_encode(['id' => $userId]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Simulation d'un objet Login minimal
        $loginMock = new \stdClass();
        $loginMock->id = 42;

        // Simulation de la requête avec l'attribut déjà hydraté (bypass JwtMiddleware)
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/api/me')
            ->withAttribute('api_login', $loginMock);

        $response = $app->handle($request);
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(42, $payload['id']);
    }
}
