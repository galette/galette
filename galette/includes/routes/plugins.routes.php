<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use Galette\Core\Login;
use Galette\Core\Plugins;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

use function Safe\file_get_contents;

/**
 * @var \Slim\App<\DI\Container> $app
 * @var \Galette\Middleware\Authenticate $authenticate
 */
$app->group(
    '/plugins',
    function (\Slim\Routing\RouteCollectorProxy $app) use ($authenticate): void {
        /** @var \DI\Container $container */
        $container = $app->getContainer();
        $modules = $container->get(Plugins::class)->getActiveModules();

        //Global route to access plugin resources (CSS, JS, images, ...)
        $app->get(
            '/{plugin}/res/{path:.*}',
            function (Request $request, Response $response, $plugin, $path) use ($container) {
                $ext = pathinfo($path)['extension'];
                $auth_ext = [
                    'js'    => 'text/javascript',
                    'css'   => 'text/css',
                    'png'   => 'image/png',
                    'jpg'   => 'image/jpg',
                    'jpeg'  => 'image/jpg',
                    'gif'   => 'image/gif',
                    'svg'   => 'image/svg+xml',
                    'map'   => 'application/json',
                    'woff'  => 'application/font-woff',
                    'woff2' => 'application/font-woff2'
                ];
                if (!str_contains($path, '../') && isset($auth_ext[$ext])) {
                    $file = $container->get(Plugins::class)->getFile(
                        $plugin,
                        $path
                    );

                    $response = $response->withHeader('Content-type', $auth_ext[$ext]);

                    $body = $response->getBody();
                    $body->write(file_get_contents($file));
                    return $response;
                } else {
                    throw new \RuntimeException(
                        sprintf(
                            'Invalid extension %1$s (%2$s)!',
                            $ext,
                            $path
                        ),
                        404
                    );
                }
            }
        )->setName('plugin_res');

        //Declare configured routes for each plugin
        foreach ($modules as $module_id => $module) {
            $container->set('Plugin ' . $module['name'], ['module' => $module, 'module_id' => $module_id]);

            $app->group(
                '/' . $module['route'],
                //@phpstan-ignore closure.unusedUse ($module_id may be used in included _routes.php from plugin.)
                function (\Slim\Routing\RouteCollectorProxy $app) use ($module, $module_id, $authenticate, $container): void {
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
                    )->setName($module['route'] . 'Info')->add($authenticate);

                    $f = $module['root'] . '/_routes.php';
                    require $f;
                }
            );
        }
    }
);
