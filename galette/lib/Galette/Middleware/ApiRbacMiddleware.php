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

namespace Galette\Middleware;

use Galette\Core\AccessControl;
use Galette\Core\Login;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * API RBAC Middleware
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiRbacMiddleware
{
    /**
     * Constructor
     *
     * @param AccessControl $accessControl Access control instance
     * @param Login         $login         Login instance (to be populated)
     */
    public function __construct(
        private readonly AccessControl $accessControl,
        private readonly Login $login
    ) {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler PSR7 request handler
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // 1. Extract JWT from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            throw new HttpUnauthorizedException($request, _T("Missing or invalid Authorization header"));
        }
        $jwt = $matches[1];

        // 2. Validate JWT and identify user
        try {
            $userId = $this->validateToken($jwt);
        } catch (Throwable $e) {
            throw new HttpUnauthorizedException($request, $e->getMessage());
        }

        // 3. Load user into Login session-like object
        if (!$this->login->load($userId)) {
            throw new HttpUnauthorizedException($request, _T("User not found"));
        }

        // 4. Check RBAC permission for route
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        
        // We expect the permission to be defined in the route arguments or name
        // Example: $app->get('/api/members', ...)->setArgument('permission', 'member:read');
        $permission = $route->getArgument('permission');
        
        // Fallback: use route name as permission if not explicitly set
        if ($permission === null) {
            $permission = str_replace('.', ':', (string)$route->getName());
        }

        if ($permission && !$this->accessControl->can($permission, null, $this->login)) {
            throw new HttpForbiddenException(
                $request,
                sprintf(_T("Permission denied: %s"), $permission)
            );
        }

        return $handler->handle($request);
    }

    /**
     * Validate JWT Token
     *
     * @param string $token JWT token
     *
     * @return int User ID
     * @throws Throwable
     */
    private function validateToken(string $token): int
    {
        // TODO: Implement actual JWT validation using firebase/php-jwt
        // For now, this is a placeholder. 
        // In a real implementation:
        // $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        // return (int)$decoded->sub;
        
        if ($token === 'debug-admin-token') {
            return 1; // Assuming ID 1 is the admin for testing
        }
        
        throw new \RuntimeException(_T("Invalid Token (Placeholder validation)"));
    }
}
