<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\PluginsController;
use Galette\Core\Plugins;
use Galette\Middleware\Authenticate;

/**
 * @var \Slim\App<\DI\Container> $app
 */
$app->group(
    '/plugins',
    function (\Slim\Routing\RouteCollectorProxy $app): void {
        /** @var \DI\Container $container */
        $container = $app->getContainer();
        $modules = $container->get(Plugins::class)->getActiveModules();

        //Global route to access plugin resources (CSS, JS, images, ...)
        $app->get(
            '/{plugin}/res/{path:.*}',
            [PluginsController::class, 'resource']
        )->setName('plugin_res');

        //Declare configured routes for each plugin
        foreach ($modules as $module_id => $module) {
            $container->set('Plugin ' . $module['name'], ['module' => $module, 'module_id' => $module_id]);

            $app->group(
                '/' . $module['route'],
                //@phpstan-ignore closure.unusedUse, closure.unusedUse ($module_id and $container may be used in included _routes.php from plugin.)
                function (\Slim\Routing\RouteCollectorProxy $app) use ($module, $module_id, $container): void {
                    $f = $module['root'] . '/_routes.php';
                    require $f;
                }
            );
        }

        //Generic plugin info route (handles /{route} for any active plugin)
        $app->get(
            '/{route}',
            [PluginsController::class, 'pluginInfo']
        )->setName('pluginInfo')->add(Authenticate::class);
    }
);
