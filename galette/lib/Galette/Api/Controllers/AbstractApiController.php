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

namespace Galette\Api\Controllers;

use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Core\I18n;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Safe\json_encode;

/**
 * Base class for Galette API controllers
 *
 * Provides DI-injected services and JSON response helpers.
 * Controllers retrieve the authenticated Login from the request attribute
 * 'api_login' (set by JwtMiddleware) rather than from the DI container,
 * keeping the API stateless.
 *
 * When feature/acls is merged, the checkPermission() method can be updated
 * to delegate to AccessControl::can() instead of using Login flags directly.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractApiController
{
    #[Inject]
    protected Db $zdb;

    #[Inject]
    protected I18n $i18n;

    #[Inject]
    protected Preferences $preferences;

    /**
     * Get the authenticated Login from the request (set by JwtMiddleware)
     */
    protected function getLogin(Request $request): Login
    {
        /** @var Login $login */
        $login = $request->getAttribute('api_login');
        return $login;
    }

    /**
     * Check whether the authenticated user has the required access level.
     *
     * Permission levels (from lowest to highest):
     *  - 'member' : any logged-in user
     *  - 'staff'  : staff member or admin or superadmin
     *  - 'admin'  : admin or superadmin
     *
     * When feature/acls is merged this method can be replaced with
     * AccessControl::can($permission, $subject, $login).
     */
    protected function checkPermission(Request $request, string $required = 'staff'): bool
    {
        $login = $this->getLogin($request);
        if (!$login->isLogged()) {
            return false;
        }
        return match ($required) {
            'member' => true,
            'staff'  => $login->isStaff() || $login->isAdmin() || $login->isSuperAdmin(),
            'admin'  => $login->isAdmin() || $login->isSuperAdmin(),
            default  => false,
        };
    }

    /**
     * Return a JSON response
     *
     * @param mixed $data   Data to encode
     * @param int   $status HTTP status code
     */
    protected function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(
            (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Return a 403 Forbidden JSON response
     */
    protected function forbidden(Response $response): Response
    {
        return $this->json($response, ['error' => 'Forbidden'], 403);
    }

    /**
     * Return a 404 Not Found JSON response
     */
    protected function notFound(Response $response, string $message = 'Not found'): Response
    {
        return $this->json($response, ['error' => $message], 404);
    }
}
