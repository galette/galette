<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Slim ACLs checks middleware
 *
 * PHP version 5
 *
 * Copyright © 2020-2022 The Galette Team
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
 * @copyright 2020-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */

namespace Galette\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Analog\Analog;
use DI\Container;
use Slim\Views\Twig;

/**
 * Galette Slim ACLs checks middleware
 *
 * @category  Middleware
 * @name      CheckAcls
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */
class CheckAcls
{
    /** @var Twig */
    protected $view;
    protected $router;
    protected $flash;
    protected $acls;

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        $this->view = $container->get('view');
        $this->router = $container->get('router');
        $this->flash = $container->get('flash');
        $this->acls = $container->get('acls');
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
        $route = $request->getAttribute('route');
        $route_info = $request->getAttribute('routeInfo');

        if ($route != null) {
            $this->view->getEnvironment()->addGlobal('cur_route', $route->getName());
            if ($route_info != null && is_array($route_info[2])) {
                $this->view->getEnvironment()->addGlobal('cur_subroute', array_shift($route_info[2]));
            }
        }

        if (GALETTE_MODE === 'DEV') {
            //check for routes that are not in ACLs
            $routes = $this->router->getRoutes();

            $missing_acls = [];
            $excluded_names = [
                'publicList',
                'filterPublicList'
            ];
            foreach ($routes as $route) {
                $name = $route->getName();
                //check if route has $authenticate middleware
                $middlewares = $route->getMiddleware();
                if (count($middlewares) > 0) {
                    foreach ($middlewares as $middleware) {
                        if (
                            !in_array($name, $excluded_names)
                            && !in_array($name, $missing_acls)
                        ) {
                            try {
                                $this->getAclFor($name);
                            } catch (\RuntimeException $e) {
                                $missing_acls[] = $name;
                            }
                        }
                    }
                }
            }
            if (count($missing_acls) > 0) {
                if (count($missing_acls) == 1) {
                    $msg = str_replace(
                        '%name',
                        $missing_acls[0],
                        _T("Route '%name' is not registered in ACLs!")
                    );
                } else {
                    $msg = str_replace(
                        '%names',
                        implode("', '", $missing_acls),
                        _T("Routes '%names' are not registered in ACLs!")
                    );
                }
                Analog::log($msg, Analog::WARNING);
                $this->flash->addMessageNow('warning_detected', $msg);
            }
        }

        return $next($request, $response);
    }

    /**
     * Get ACL for route name
     *
     * @param string $name Route name
     *
     * @return string
     * @throw RuntimeException
     */
    public function getAclFor($name)
    {
        //first, check for exact match
        if (isset($this->acls[$name])) {
            return $this->acls[$name];
        } else {
            //handle routes regexps
            foreach ($this->acls as $regex => $route_acl) {
                if (preg_match('@/(.+)/[imsxADU]?@', $regex)) {
                    //looks like a regular expression, go
                    $matches = [];
                    if (preg_match($regex, $name, $matches)) {
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
