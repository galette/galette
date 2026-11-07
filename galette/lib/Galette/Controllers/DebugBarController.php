<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use DebugBar\AssetHandler;
use DebugBar\DebugBarException;
use DebugBar\OpenHandler;
use Galette\Controllers\Attributes\Route;
use Galette\Core\Login;
use Galette\Debug\DebugBar;
use Galette\Debug\Psr7HttpDriver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RKA\Session;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\UriFactory;
use Slim\Routing\RouteParser;

/**
 * Debug bar switch, assets and stored data
 *
 * Routes are only declared in debug mode; without the bar, assets and data
 * are not found. Assets and data are only served to a session where the bar
 * has been turned on.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DebugBarController
{
    /**
     * @param Login       $login       Login instance
     * @param Session     $session     Session instance
     * @param RouteParser $routeparser Route parser instance
     */
    public function __construct(
        private readonly Login $login,
        private readonly Session $session,
        private readonly RouteParser $routeparser
    ) {
    }

    /**
     * Turn the bar on or off for current session
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     *
     * @throws HttpForbiddenException
     */
    #[Route(
        name: 'debugbarToggle',
        pattern: '/debug/debugbar/toggle',
        methods: ['POST'],
        debugOnly: true
    )]
    public function toggle(Request $request, Response $response): Response
    {
        if (DebugBar::get() === null || !DebugBar::canToggle($this->login)) {
            throw new HttpForbiddenException($request);
        }

        DebugBar::setActive($this->session, $this->login, !DebugBar::isActive($this->session, $this->login));

        return $response
            ->withStatus(302)
            ->withHeader('Location', $this->getReturnUrl($request));
    }

    /**
     * Serve debug bar CSS and JavaScript
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    #[Route(
        name: 'debugbarAssets',
        pattern: '/debug/debugbar/assets',
        methods: ['GET'],
        requiresAuth: false,
        debugOnly: true
    )]
    public function assets(Request $request, Response $response): Response
    {
        $bar = $this->getBar($request);
        $content = (new AssetHandler($bar))->handle($request->getQueryParams(), echo: false);
        return $this->respond($bar, $response, $content);
    }

    /**
     * Serve stored data of current session
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     *
     * @throws HttpNotFoundException
     */
    #[Route(
        name: 'debugbarOpen',
        pattern: '/debug/debugbar/open',
        methods: ['GET'],
        requiresAuth: false,
        debugOnly: true
    )]
    public function open(Request $request, Response $response): Response
    {
        $bar = $this->getBar($request);
        try {
            $content = (new OpenHandler($bar))->handle($request->getQueryParams(), echo: false);
        } catch (DebugBarException $e) {
            throw new HttpNotFoundException($request, $e->getMessage(), $e);
        }
        return $this->respond($bar, $response, $content);
    }

    /**
     * Where to go back after toggling
     *
     * @param Request $request PSR7 request
     */
    private function getReturnUrl(Request $request): string
    {
        $referer = $request->getHeaderLine('Referer');
        if ($referer !== '') {
            try {
                $uri = (new UriFactory())->createUri($referer);
                if (
                    $uri->getHost() === $request->getUri()->getHost()
                    && $uri->getPort() === $request->getUri()->getPort()
                ) {
                    return (string)$uri;
                }
            } catch (\InvalidArgumentException) {
                // no log: a malformed referer just sends back home
            }
        }
        return $this->routeparser->urlFor('slash');
    }

    /**
     * Get the debug bar, when turned on for current session
     *
     * @param Request $request PSR7 request
     *
     * @throws HttpNotFoundException
     */
    private function getBar(Request $request): \DebugBar\DebugBar
    {
        $bar = DebugBar::get();
        if ($bar === null || !DebugBar::isActive($this->session, $this->login)) {
            throw new HttpNotFoundException($request);
        }
        return $bar;
    }

    /**
     * Build response from handler output
     *
     * @param \DebugBar\DebugBar $bar      Debug bar
     * @param Response           $response PSR7 response
     * @param string             $content  Content
     */
    private function respond(\DebugBar\DebugBar $bar, Response $response, string $content): Response
    {
        $driver = $bar->getHttpDriver();
        if ($driver instanceof Psr7HttpDriver) {
            $response = $driver->apply($response);
        }
        $response->getBody()->write($content);
        return $response;
    }
}
