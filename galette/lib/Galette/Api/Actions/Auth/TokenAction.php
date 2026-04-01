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

namespace Galette\Api\Actions\Auth;

use Firebase\JWT\JWT;
use Galette\Api\Controllers\AbstractApiController;
use Galette\Api\Middleware\JwtMiddleware;
use Galette\Entity\ApiClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/v1/auth/token
 *
 * OAuth2 client credentials flow.
 * Returns a short-lived JWT for the authenticated API client.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TokenAction extends AbstractApiController
{
    private const ACCESS_TOKEN_TTL = 900; // 15 minutes

    /**
     * Handle POST /api/v1/auth/token
     *
     * @param array<string, string> $args Route arguments (unused)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $body = (array)($request->getParsedBody() ?? []);
        $grantType = (string)($body['grant_type'] ?? '');
        $clientId = (string)($body['client_id'] ?? '');
        $clientSecret = (string)($body['client_secret'] ?? '');

        if ($grantType !== 'client_credentials') {
            return $this->json($response, ['error' => 'unsupported_grant_type'], 400);
        }
        if ($clientId === '' || $clientSecret === '') {
            return $this->json($response, ['error' => 'client_id and client_secret are required'], 400);
        }

        $client = new ApiClient($clientId);
        if (!$client->isLoaded() || !$client->verifySecret($clientSecret)) {
            return $this->json($response, ['error' => 'Invalid client credentials'], 401);
        }

        $now = time();
        $payload = [
            'iss'     => 'galette-api',
            'iat'     => $now,
            'exp'     => $now + self::ACCESS_TOKEN_TTL,
            'sub'     => $client->getClientId(),
            'type'    => 'client',
            'trusted' => $client->isTrusted(),
            'scopes'  => [],
        ];
        $accessToken = JWT::encode($payload, JwtMiddleware::getSecret(), 'HS256');

        return $this->json($response, [
            'access_token' => $accessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => self::ACCESS_TOKEN_TTL,
        ]);
    }
}
