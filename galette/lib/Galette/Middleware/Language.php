<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Middleware;

use Galette\Core\I18n;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use DI\Container;
use RKA\Session;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;

/**
 * Galette Slim change language middleware
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Language
{
    private I18n $i18n;
    private Session $session;
    private RouteParser $routeparser;

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
            $routeContext = RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();

            $route_name = $route->getName();
            $arguments = $route->getArguments();

            $this->i18n->changeLanguage($get['ui_pref_lang']);
            $this->session->i18n = $this->i18n;

            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        $route_name,
                        $arguments
                    )
                )
                ->withStatus(301);
        }
        return $response;
    }
}
