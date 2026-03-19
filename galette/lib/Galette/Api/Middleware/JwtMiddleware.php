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

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Galette API JWT middleware
 */
class JwtMiddleware
{
    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $奠定secretKey;
    }

    public function __invoke(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = str_replace('Bearer ', '', $authHeader);

        if (!$token) {
            return $this->unauthorized("Token manquant.");
        }

        try {
            // Décodage du token avec firebase/php-jwt
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));

            // Optionnel : On ajoute les infos du user dans les attributs de la requête
            $request = $request->withAttribute('user_id', $decoded->sub);
            $request = $request->withAttribute('user_role', $decoded->role ?? 'membre');

            return $handler->handle($request);
        } catch (\Exception $e) {
            return $this->unauthorized("Token invalide ou expiré : " . $e->getMessage());
        }
    }

    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $message
        ]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
}