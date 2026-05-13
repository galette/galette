<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\PluginsController;
use Galette\Core\Login;
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
        $modules = $container->get(Plugins::class)->getModules();

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
                //@phpstan-ignore closure.unusedUse ($module_id may be used in included _routes.php from plugin.)
                function (\Slim\Routing\RouteCollectorProxy $app) use ($module, $module_id, $container): void {
                    //Plugin home: give information
                    $app->get(
                        '',
                        function ($request, $response) use ($module, $container) {
                            $params = [
                                'page_title'    => $module['name'],
                                'name'          => $module['name'],
                                'version'       => $module['version'],
                                'date'          => $module['date'],
                                'author'        => $module['author']
                            ];
                            if ($container->get(Login::class)->isAdmin()) {
                                $params['module'] = $module;
                            }
                            // display page
                            $container->get(\Slim\Views\Twig::class)->render(
                                $response,
                                'pages/plugin_info.html.twig',
                                $params
                            );
                            return $response;
                        }
                    )->setName($module['route'] . 'Info')->add(Authenticate::class);

                    $f = $module['root'] . '/_routes.php';
                    require $f;
                }
            );
        }
    }
);
