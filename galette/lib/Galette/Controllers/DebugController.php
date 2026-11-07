<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Controllers\Attributes\Route;
use Slim\Psr7\Response;
use Slim\Routing\RouteCollector;

use function Safe\ob_get_clean;
use function Safe\ob_start;
use function Safe\phpinfo;

/**
 * Debug pages, only available when debug mode is enabled
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DebugController
{
    /**
     * Display phpinfo()
     */
    #[Route(
        name: 'debugPhpinfo',
        pattern: '/debug/phpinfo/',
        methods: ['GET'],
        requiresAuth: false,
        debugOnly: true
    )]
    public function phpinfo(Response $response): Response
    {
        ob_start();
        phpinfo();
        $response->getBody()->write(ob_get_clean());
        return $response;
    }

    /**
     * List declared routes
     *
     * @template TContainerInterface of (\Psr\Container\ContainerInterface|null)
     *
     * @param Response                            $response       PSR Response
     * @param RouteCollector<TContainerInterface> $routeCollector Routes collector
     */
    #[Route(
        name: 'debugRoutes',
        pattern: '/debug/routes/',
        methods: ['GET'],
        requiresAuth: false,
        debugOnly: true
    )]
    public function routes(Response $response, RouteCollector $routeCollector): Response
    {
        foreach ($routeCollector->getRoutes() as $route) {
            $response->getBody()->write(
                htmlspecialchars(
                    $route->getIdentifier() . ' → '
                    . ($route->getName() ?? '(unnamed)') . ' → '
                    . $route->getPattern()
                ) . '<br><br>'
            );
        }

        return $response;
    }
}
