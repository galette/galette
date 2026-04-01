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
use Galette\Api\Repository\ApiTokenRepository;
use Galette\Core\Login;
use Galette\Entity\ApiClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/v1/auth/refresh
 *
 * Rotates a refresh token and returns a new access + refresh token pair.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RefreshAction extends AbstractApiController
{
    private const ACCESS_TOKEN_TTL = 900;       // 15 minutes
    private const REFRESH_TOKEN_TTL = 2592000;  // 30 days

    /**
     * Handle POST /api/v1/auth/refresh
     *
     * @param array<string, string> $args Route arguments (unused)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $body = (array)($request->getParsedBody() ?? []);
        $rawToken = (string)($body['refresh_token'] ?? '');
        $clientId = ($body['client_id'] ?? null) !== null ? (string)$body['client_id'] : null;

        if ($rawToken === '') {
            return $this->json($response, ['error' => 'refresh_token is required'], 400);
        }

        $tokenRepo = new ApiTokenRepository($this->zdb);
        $data = $tokenRepo->verifyAndRotate($rawToken, $clientId);

        if ($data === null) {
            return $this->json($response, ['error' => 'Invalid or expired refresh token'], 401);
        }

        $newRefreshToken = bin2hex(random_bytes(40));
        $now = time();

        if ($data['id_adh'] !== null) {
            $login = new Login($this->zdb, $this->i18n);
            if (!$login->loadById($data['id_adh'])) {
                return $this->json($response, ['error' => 'User not found or inactive'], 401);
            }
            $payload = [
                'iss'  => 'galette-api',
                'iat'  => $now,
                'exp'  => $now + self::ACCESS_TOKEN_TTL,
                'sub'  => $login->id,
                'type' => 'user',
            ];
            $tokenRepo->createRefreshToken($data['id_adh'], null, $newRefreshToken, [], self::REFRESH_TOKEN_TTL);
        } else {
            $client = new ApiClient($data['client_id']);
            $payload = [
                'iss'     => 'galette-api',
                'iat'     => $now,
                'exp'     => $now + self::ACCESS_TOKEN_TTL,
                'sub'     => $client->getClientId(),
                'type'    => 'client',
                'trusted' => $client->isTrusted(),
                'scopes'  => [],
            ];
            $tokenRepo->createRefreshToken(null, $data['client_id'], $newRefreshToken, [], self::REFRESH_TOKEN_TTL);
        }

        $accessToken = JWT::encode($payload, JwtMiddleware::getSecret(), 'HS256');

        return $this->json($response, [
            'access_token'  => $accessToken,
            'token_type'    => 'Bearer',
            'expires_in'    => self::ACCESS_TOKEN_TTL,
            'refresh_token' => $newRefreshToken,
        ]);
    }
}
