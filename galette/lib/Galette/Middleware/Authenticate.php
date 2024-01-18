<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Slim middleware for authentication
 *
 * PHP version 5
 *
 * Copyright © 2020-2024 The Galette Team
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9.4dev - 2020-05-06
 */

namespace Galette\Middleware;

use Galette\Core\Login;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Analog\Analog;
use DI\Container;
use RKA\Session;
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;

/**
 * Galette Slim middleware for authentication
 *
 * @category  Middleware
 * @name      Authenticate
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-20234 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9.4dev - 2020-05-06
 */
class Authenticate
{
    /**
     * @var Messages
     */
    protected Messages $flash;

    /**
     * @var array<string, string>
     */
    protected array $acls;

    /**
     * @var Login
     */
    private Login $login;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @var RouteParser
     */
    private RouteParser $routeparser;

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        $this->login = $container->get('login');
        $this->session = $container->get('session');
        $this->flash = $container->get('flash');
        $this->acls = $container->get('acls');
        $this->routeparser = $container->get(RouteParser::class);
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler PSR7 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = new \Slim\Psr7\Response();

        if (!$this->login->isLogged()) {
            if ($request->getMethod() === 'GET') {
                $this->session->set('urlRedirect', $request->getUri()->getPath());
                Analog::log(
                    'Login required to access ' . $this->session->get('urlRedirect'),
                    Analog::DEBUG
                );
            }

            $this->flash->addMessage('error_detected', _T("Login required"));
            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                )->withStatus(302);
        }

        //check for ACLs
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $cur_route = $route->getName();
        $acl = $this->getAclFor($cur_route);

        $go = false;
        switch ($acl) {
            case 'superadmin':
                if ($this->login->isSuperAdmin()) {
                    $go = true;
                }
                break;
            case 'admin':
                if (
                    $this->login->isSuperAdmin()
                    || $this->login->isAdmin()
                ) {
                    $go = true;
                }
                break;
            case 'staff':
                if (
                    $this->login->isSuperAdmin()
                    || $this->login->isAdmin()
                    || $this->login->isStaff()
                ) {
                    $go = true;
                }
                break;
            case 'groupmanager':
                if (
                    $this->login->isSuperAdmin()
                    || $this->login->isAdmin()
                    || $this->login->isStaff()
                    || $this->login->isGroupManager()
                ) {
                    $go = true;
                }
                break;
            case 'member':
                $go = true;
                break;
            default:
                throw new \RuntimeException(
                    str_replace(
                        '%acl',
                        $acl,
                        _T("Unknown ACL rule '%acl'!")
                    )
                );
        }
        if (!$go) {
            Analog::log(
                'Permission denied for route ' . $cur_route . ' for user ' . $this->login->login,
                Analog::DEBUG
            );
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );
            return $response
                ->withHeader('Location', $this->routeparser->urlFor('slash'))
                ->withStatus(302);
        }

        return $handler->handle($request);
    }

    /**
     * Get ACL for route name
     *
     * @param string $name Route name
     *
     * @return string
     * @throw RuntimeException
     */
    public function getAclFor(string $name): string
    {
        //first, check for exact match
        if (isset($this->acls[$name])) {
            return $this->acls[$name];
        } else {
            //handle routes regexps
            foreach ($this->acls as $regex => $route_acl) {
                if (preg_match('@/(.+)/[imsxADU]?@', $regex)) {
                    //looks like a regular expression, go
                    if (preg_match($regex, $name)) {
                        return $route_acl;
                    }
                }
            }
        }

        throw new \RuntimeException(
            str_replace(
                '%name',
                $name,
                _T("Route '%name' is not registered in ACLs!")
            )
        );
    }
}
