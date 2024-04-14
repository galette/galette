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

use Galette\Core\Login;
use Galette\Core\Preferences;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use DI\Container;
use Slim\Flash\Messages;
use Slim\Routing\RouteParser;

/**
 * Galette Slim middleware for public pages access
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PublicPages
{
    protected Messages $flash;

    private Login $login;

    private RouteParser $routeparser;

    private Preferences $preferences;

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        $this->login = $container->get('login');
        $this->flash = $container->get('flash');
        $this->routeparser = $container->get(RouteParser::class);
        $this->preferences = $container->get('preferences');
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

        if (!$this->preferences->showPublicPages($this->login)) {
            $this->flash->addMessage('error_detected', _T("Unauthorized"));
            return $response
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                )->withStatus(302);
        }

        return $handler->handle($request);
    }
}
