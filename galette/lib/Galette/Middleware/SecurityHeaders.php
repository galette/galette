<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Middleware;

use Galette\Core\Csp;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

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
     * @param Csp $csp Content Security Policy of the request
     */
    public function __construct(private readonly Csp $csp)
    {
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler Request response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin');

        $type = $response->getHeaderLine('Content-Type');
        if ($type !== '' && !str_contains($type, 'text/html')) {
            //CSV, PDF, images, JSON... are not documents scripts can run in
            return $response;
        }

        return $response
            ->withHeader('Content-Security-Policy', $this->csp->getEnforcedPolicy())
            ->withHeader('Content-Security-Policy-Report-Only', $this->csp->getFullPolicy());
    }
}
