<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Galette\Core\Login;
use Galette\Core\Preferences;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Flash\Messages;
use Slim\Routing\RouteParser;

/**
 * Galette Slim middleware for public pages access
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PublicPages
{
    /**
     * Constructor
     *
     * @param Login       $login       Login instance
     * @param RouteParser $routeparser Route parser instance
     * @param Preferences $preferences Preferences instance
     * @param Messages    $flash       Flash messages instance
     */
    public function __construct(
        private readonly Login $login,
        private readonly RouteParser $routeparser,
        private readonly Preferences $preferences,
        protected Messages $flash
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
        $response = new \Slim\Psr7\Response();

        $right_pattern = 'pref_publicpages_visibility_%s%s';
        $current_path = trim($request->getUri()->getPath(), '/');
        $page = explode('/', $current_path);
        if ($page[0] === 'plugins') {
            $right_pattern .= '_%s';
            $right = sprintf(
                $right_pattern,
                'plugin',
                $page[1] ?? '',
                $page[3] ?? ''
            );
        } else {
            $right = sprintf(
                $right_pattern,
                $page[1] ?? '',
                $page[2] ?? ''
            );
        }

        if (!$this->preferences->showPublicPage($this->login, $right)) {
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
