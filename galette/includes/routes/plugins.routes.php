<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Plugins routes
 *
 * PHP version 5
 *
 * Copyright © 2015 The Galette Team
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
 * @copyright 2015 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.9dev 2015-10-28
 */

$app->group(
    __('/plugins', 'routes'),
    function () use ($authenticate, $app) {
        $container = $this->getContainer();
        $modules = $container->plugins->getModules();

        //Global route to access plugin resources (CSS, JS, images, ...)
        $this->get(
            '/{plugin}/res/{path:.*}',
            function ($request, $response, $args) {
                $plugin = $args['plugin'];
                $path = $args['path'];
                $ext = pathinfo($args['path'])['extension'];
                $auth_ext = [
                    'js'    => 'text/javascript',
                    'css'   => 'text/css',
                    'png'   => 'image/png',
                    'jpg'   => 'image/jpg',
                    'jpeg'  => 'image/jpg',
                    'gif'   => 'image/gif'
                ];
                if (strpos($path, '../') === false && isset($auth_ext[$ext])) {
                    $file = $this->plugins->getFile(
                        $plugin,
                        $path
                    );

                    $response = $response->withHeader('Content-type', $auth_ext[$ext]);

                    $body = $response->getBody();
                    $body->write(file_get_contents($file));
                    return $response;
                } else {
                    $app->halt(
                        500,
                        _T("Invalid extension!")
                    );
                }
            }
        )->setName('plugin_res');

        //Declare configured routes for each plugin
        foreach ($modules as $module_id => $module) {
            $this->group(
                '/' . $module['route'],
                function () use ($module, $module_id, $authenticate) {
                    $f = $module['root'] . '/_routes.php';
                    include_once $f;
                }
            );
        }
    }
);
