<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Plugins routes
 *
 * PHP version 5
 *
 * Copyright © 2015-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2015-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     0.9dev 2015-10-28
 */

use Slim\Psr7\Request;
use Slim\Psr7\Response;

$app->group(
    '/plugins',
    function (\Slim\Routing\RouteCollectorProxy $app) use ($authenticate, $showPublicPages) {
        $container = $app->getContainer();
        $modules = $container->get('plugins')->getModules();

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
                    'svg'   => 'image/svg+xml'
                ];
                if (strpos($path, '../') === false && isset($auth_ext[$ext])) {
                    $file = $container->get('plugins')->getFile(
                        $plugin,
                        $path
                    );

                    $response = $response->withHeader('Content-type', $auth_ext[$ext]);

                    $body = $response->getBody();
                    $body->write(file_get_contents($file));
                    return $response;
                } else {
                    $this->halt(
                        500,
                        _T("Invalid extension!")
                    );
                }
            }
        )->setName('plugin_res');

        //Declare configured routes for each plugin
        foreach ($modules as $module_id => $module) {
            $container->set('Plugin ' . $module['name'], ['module' => $module, 'module_id' => $module_id]);

            $app->group(
                '/' . $module['route'],
                //$module_id may be used in included _routes.php from plugin.
                function (\Slim\Routing\RouteCollectorProxy $app) use ($module, $module_id, $authenticate, $showPublicPages, $container) {
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
                            if ($container->get('login')->isAdmin()) {
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
                    include_once $f;
                }
            );
        }
    }
);
