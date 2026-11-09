<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Galette\Controllers\CspReportController;
use Galette\Core\Csp;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteParser;
use Throwable;

/**
 * Galette Slim security headers middleware
 *
 * Sends Content Security Policy and related headers on HTML responses.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SecurityHeaders
{
    /**
     * Policy for SVG images: harmless as images, an SVG opened on its own is a
     * document able to run scripts, within Galette
     */
    public const string SVG_POLICY = "default-src 'none'; style-src 'unsafe-inline'; img-src data:";

    /**
     * @param Csp         $csp         Content Security Policy of the request
     * @param RouteParser $routeparser Route parser instance
     */
    public function __construct(
        private readonly Csp $csp,
        private readonly RouteParser $routeparser
    ) {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler Request response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        try {
            $this->csp->setReportUri($this->routeparser->urlFor(CspReportController::ROUTE));
        } catch (Throwable) {
            // no log: policy is still sent, only without reports
        }

        $response = $handler->handle($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin');

        $type = $response->getHeaderLine('Content-Type');
        if (str_contains($type, 'image/svg+xml')) {
            return $response->withHeader('Content-Security-Policy', self::SVG_POLICY);
        }
        if ($type !== '' && !str_contains($type, 'text/html')) {
            //CSV, PDF, images, JSON... are not documents scripts can run in
            return $response;
        }

        $endpoints = $this->csp->getReportingEndpoints();
        if ($endpoints !== null) {
            $response = $response->withHeader('Reporting-Endpoints', $endpoints);
        }

        return $response
            ->withHeader('Content-Security-Policy', $this->csp->getEnforcedPolicy())
            ->withHeader('Content-Security-Policy-Report-Only', $this->csp->getFullPolicy());
    }
}
