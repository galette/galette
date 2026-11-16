<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Galette\Core\Csp;
use Galette\Core\Login;
use Galette\Core\Logs;
use Galette\Debug\DebugBar as GaletteDebugBar;
use Galette\Debug\Psr7HttpDriver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use RKA\Session;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteParser;
use Throwable;

/**
 * Debug bar middleware
 *
 * Must be the outermost middleware, to see the final response. Nothing is done
 * unless a super administrator turned the bar on for current session. HTML pages get
 * the bar injected; redirections keep their data for the next page; any other
 * response (AJAX, JSON, files) carries an id the bar fetches its data with.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DebugBar
{
    /**
     * @param RouteParser $routeparser Route parser instance
     * @param Session     $session     Session instance
     * @param Login       $login       Login instance
     * @param Csp         $csp         Content Security Policy of the request
     */
    public function __construct(
        private readonly RouteParser $routeparser,
        private readonly Session $session,
        private readonly Login $login,
        private readonly Csp $csp
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
        $bar = GaletteDebugBar::get();
        if ($bar === null || str_contains($request->getUri()->getPath(), '/debug/debugbar/')) {
            return $handler->handle($request);
        }

        GaletteDebugBar::addMeasure('Bootstrap', (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(as_float: true)));
        GaletteDebugBar::startMeasure('application', 'Application');
        $response = $handler->handle($request);
        GaletteDebugBar::stopMeasure('application');

        //checked after handling: the request may just have turned the bar on or off
        if (!GaletteDebugBar::isActive($this->session, $this->login)) {
            return $response;
        }

        try {
            $driver = $bar->getHttpDriver();
            if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400 && $response->hasHeader('Location')) {
                $bar->stackData();
                return $response;
            }

            if ($this->isHtmlPage($request, $response)) {
                return $this->inject($response);
            }

            $bar->sendDataInHeaders(useOpenHandler: true);
            if ($driver instanceof Psr7HttpDriver) {
                $response = $driver->apply($response);
            }
        } catch (Throwable $e) {
            //never break a page because of the debug bar
            Logs::exception($e, 'Debug bar failure', \Analog\Analog::WARNING);
        }

        return $response;
    }

    /**
     * Is the response a full HTML page?
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    private function isHtmlPage(Request $request, Response $response): bool
    {
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return false;
        }

        $type = $response->getHeaderLine('Content-Type');
        if ($type !== '' && !str_contains($type, 'text/html')) {
            return false;
        }

        return stripos((string)$response->getBody(), '</body>') !== false;
    }

    /**
     * Inject the bar in an HTML page
     *
     * @param Response $response PSR7 response
     */
    private function inject(Response $response): Response
    {
        $bar = GaletteDebugBar::get();
        if ($bar === null) {
            return $response;
        }

        $renderer = $bar->getJavascriptRenderer();
        $renderer->setAssetHandlerUrl($this->routeparser->urlFor('debugbarAssets'));
        $renderer->setOpenHandlerUrl($this->routeparser->urlFor('debugbarOpen'));
        $renderer->setTheme('auto');
        $renderer->setHideEmptyTabs(hide: true);
        $renderer->setCspNonce($this->csp->getNonce());

        $html = (string)$response->getBody();
        $widget = $renderer->renderHead() . $renderer->render();
        $pos = (int)strripos($html, '</body>');
        $html = substr($html, 0, $pos) . $widget . substr($html, $pos);

        return $response
            ->withoutHeader('Content-Length')
            ->withBody((new StreamFactory())->createStream($html));
    }
}
