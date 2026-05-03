<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Galette\Core\I18n;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
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
    /**
     * Constructor
     *
     * @param I18n        $i18n        I18n instance
     * @param Session     $session     Session
     * @param RouteParser $routeparser Route parser instance
     */
    public function __construct(
        private readonly I18n $i18n,
        private readonly Session $session,
        private readonly RouteParser $routeparser
    ) {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler Request handler
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
