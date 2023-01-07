<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Slim change language middleware
 *
 * PHP version 5
 *
 * Copyright © 2020-2023 The Galette Team
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
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */

namespace Galette\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use DI\Container;
use Slim\Routing\RouteParser;

/**
 * Galette Slim change language middleware
 *
 * @category  Middleware
 * @name      Language
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */
class Language
{
    private $i18n;
    private $session;
    private $routeparser;

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        $this->i18n = $container->get('i18n');
        $this->session = $container->get('session');
        $this->routeparser = $container->get(RouteParser::class);
    }

    /**
     * Middleware invokable class
     *
     * @param  Request        $request PSR7 request
     * @param  RequestHandler $handler Request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        $get = $request->getQueryParams();

        if (isset($get['ui_pref_lang'])) {
            $route = $request->getAttribute('route');

            $route_name = $route->getName();
            $arguments = $route->getArguments();

            $this->i18n->changeLanguage($get['ui_pref_lang']);
            $this->session->i18n = $this->i18n;

            return $response->withRedirect(
                $this->routeparser->urlFor(
                    $route_name,
                    $arguments
                ),
                301
            );
        }
        return $response;
    }
}
