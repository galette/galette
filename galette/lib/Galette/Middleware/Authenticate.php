<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Slim middleware for authentication
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */

namespace Galette\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Galette\Entity\Adherent;
use Galette\Filters\MembersList;
use Galette\Repository\Members;
use Analog\Analog;
use DI\Container;

/**
 * Galette Slim middleware for authentication
 *
 * @category  Middleware
 * @name      Authenticate
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */
class Authenticate extends CheckAcls
{
    /**
     * @var Galette\Core\Login
     */
    private $login;

    /**
     * @var RKA\Session
     */
    private $session;

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->login = $container->get('login');
        $this->session = $container->get('session');
    }

    /**
     * Middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, $next): Response
    {
        if (!$this->login || !$this->login->isLogged()) {
            if ($request->isGet()) {
                $this->session->urlRedirect = $request->getUri()->getPath();
            }
            Analog::log(
                'Login required to access ' . $this->session->urlRedirect,
                Analog::DEBUG
            );
            $this->flash->addMessage('error_detected', _T("Login required"));
            return $response
                ->withHeader('Location', $this->router->pathFor('slash'));
        } else {
            //check for ACLs
            $cur_route = $request->getAttribute('route')->getName();
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
                    if ($this->login->isLogged()) {
                        $go = true;
                    }
                    break;
                default:
                    throw new \RuntimeException(
                        str_replace(
                            '%acl',
                            $acl,
                            _T("Unknown ACL rule '%acl'!")
                        )
                    );
                    break;
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
                    ->withHeader('Location', $this->router->pathFor('slash'));
            }
        }

        return $next($request, $response);
    }
}
