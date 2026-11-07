<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Galette\Debug\DebugBar;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Expose current route to templates; reject requests no route matches
 *
 * Must be added before the routing middleware, so it runs after it.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RouteGlobals
{
    /**
     * Constructor
     *
     * @param Twig $view Twig view
     */
    public function __construct(private readonly Twig $view)
    {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler Request handler
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $route = RouteContext::fromRequest($request)->getRoute();

        if ($route === null) {
            throw new HttpNotFoundException($request);
        }

        DebugBar::setRoute($route);

        $arguments = $route->getArguments();

        $env = $this->view->getEnvironment();
        $env->addGlobal('cur_route', $route->getName());
        $env->addGlobal('cur_route_args', $arguments);
        $env->addGlobal('cur_subroute', array_shift($arguments));

        return $handler->handle($request);
    }
}
