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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/v1/auth/login
 *
 * Authenticates a Galette user with login + password.
 * Returns a short-lived JWT access token and a long-lived refresh token.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LoginAction extends AbstractApiController
{
    private const ACCESS_TOKEN_TTL = 900;       // 15 minutes
    private const REFRESH_TOKEN_TTL = 2592000;  // 30 days

    /**
     * Handle POST /api/v1/auth/login
     *
     * @param array<string, string> $args Route arguments (unused)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $body = (array)($request->getParsedBody() ?? []);
        $userLogin = trim((string)($body['login'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($userLogin === '' || $password === '') {
            return $this->json($response, ['error' => 'login and password are required'], 400);
        }

        $login = new Login($this->zdb, $this->i18n);

        if (!$login->logIn($userLogin, $password)) {
            return $this->json($response, ['error' => 'Invalid credentials'], 401);
        }

        $now = time();
        $payload = [
            'iss'  => 'galette-api',
            'iat'  => $now,
            'exp'  => $now + self::ACCESS_TOKEN_TTL,
            'sub'  => $login->id,
            'type' => 'user',
        ];
        $accessToken = JWT::encode($payload, JwtMiddleware::getSecret(), 'HS256');
        $refreshToken = bin2hex(random_bytes(40));

        $tokenRepo = new ApiTokenRepository($this->zdb);
        $tokenRepo->createRefreshToken($login->id, null, $refreshToken, [], self::REFRESH_TOKEN_TTL);

        return $this->json($response, [
            'access_token'  => $accessToken,
            'token_type'    => 'Bearer',
            'expires_in'    => self::ACCESS_TOKEN_TTL,
            'refresh_token' => $refreshToken,
        ]);
    }
}
