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

use DI\Attribute\Inject;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Galette\Core\Db;
use Galette\Core\I18n;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_encode;

/**
 * Galette API JWT middleware
 *
 * Validates a Bearer JWT token and hydrates a Login instance for the request.
 * For user tokens: loads the member by id via Login::loadById().
 * For client tokens (OAuth2): sets admin/staff flags based on client trust level.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class JwtMiddleware
{
    #[Inject]
    protected Db $zdb;

    #[Inject]
    protected I18n $i18n;

    #[Inject]
    protected Preferences $preferences;

    /**
     * Process the request: validate Bearer JWT and hydrate Login on the request.
     *
     * @param Request $request Request
     * @param Handler $handler Next handler
     */
    public function __invoke(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Missing or malformed Authorization header.');
        }

        $token = substr($authHeader, 7);

        try {
            $secret = $this->getSecret();
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Exception $e) {
            return $this->unauthorized('Invalid or expired token: ' . $e->getMessage());
        }

        $type = $decoded->type ?? 'user';

        if ($type === 'user') {
            $userId = (int)($decoded->sub ?? 0);
            if ($userId <= 0) {
                return $this->unauthorized('Invalid token payload.');
            }
            $login = new Login($this->zdb, $this->i18n);
            if (!$login->loadById($userId)) {
                return $this->unauthorized('User not found or inactive.');
            }
            $request = $request->withAttribute('api_login', $login);
            $request = $request->withAttribute('api_scopes', (array)($decoded->scopes ?? []));
        } elseif ($type === 'client') {
            $clientId = (string)($decoded->sub ?? '');
            $trusted = (bool)($decoded->trusted ?? false);
            $scopes = (array)($decoded->scopes ?? []);

            // For OAuth2 clients, synthesize a Login with appropriate privileges
            $login = new Login($this->zdb, $this->i18n);
            if ($trusted) {
                $login->logAdmin($clientId, $this->preferences);
            }
            $request = $request->withAttribute('api_login', $login);
            $request = $request->withAttribute('api_client_id', $clientId);
            $request = $request->withAttribute('api_scopes', $scopes);
        } else {
            return $this->unauthorized('Unknown token type.');
        }

        return $handler->handle($request);
    }

    /**
     * Get or generate the HMAC secret for JWT signing.
     * Stored in galette/config/api_secret.key; auto-generated if absent.
     */
    public static function getSecret(): string
    {
        $path = GALETTE_CONFIG_PATH . 'api_secret.key';
        if (file_exists($path)) {
            return (string)file_get_contents($path);
        }
        $secret = bin2hex(random_bytes(32));
        file_put_contents($path, $secret);
        chmod($path, 0o600);
        return $secret;
    }

    /**
     * Build a 401 Unauthorized JSON response
     *
     * @param string $message Error message
     */
    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write((string)json_encode([
            'error' => 'Unauthorized',
            'message' => $message,
        ]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
