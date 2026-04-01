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

namespace Galette\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

use function Safe\json_encode;

/**
 * Galette API scope middleware
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScopeMiddleware
{
    /**
     * Constructor
     *
     * @param string $requiredScope Required OAuth2 scope
     */
    public function __construct(private readonly string $requiredScope)
    {
    }

    /**
     * Check that the token scopes include the required scope.
     *
     * @param Request $request Request
     * @param Handler $handler Next handler
     */
    public function __invoke(Request $request, Handler $handler): Response
    {
        $scopes = $request->getAttribute('api_scopes', []);
        if (!in_array($this->requiredScope, $scopes)) {
            $response = new SlimResponse();
            $response->getBody()->write((string)json_encode([
                'error'   => 'Forbidden',
                'message' => 'Scope insuffisant: ' . $this->requiredScope,
            ]));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
