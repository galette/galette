<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main routes
 *
 * PHP version 5
 *
 * Copyright © 2014-2023 The Galette Team
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
 * @copyright 2014-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Controllers\GaletteController;
use Galette\Controllers\ImagesController;

//main route
$app->get(
    '/',
    [GaletteController::class, 'slash']
)->setName('slash');

$app->get(
    '/favicon.ico',
    [GaletteController::class, 'favicon']
);

//logo route
$app->get(
    '/logo',
    [ImagesController::class, 'logo']
)->setName('logo');

//print logo route
$app->get(
    '/print-logo',
    [ImagesController::class, 'printLogo']
)->setName('printLogo');

//photo route
$app->get(
    '/photo/{id:\d+}',
    [ImagesController::class, 'photo']
)->setName('photo');

//system information - keep old route with typo ('s' on 'information') for now (0.9.4)
$app->get(
    '/system-informations',
    function ($request, $response) use ($routeparser) {
        return $response
            ->withStatus(302)
            ->withHeader('Location', $routeparser->urlFor('sysinfos'));
    }
);

//system information
$app->get(
    '/system-information',
    [GaletteController::class, 'systemInformation']
)->setName('sysinfos')->add($authenticate);

$app->post(
    '/write-dark-css',
    function ($request, $response) {
        $post = $request->getParsedBody();
        file_put_contents(GALETTE_CACHE_DIR . '/dark.css', $post);
        return $response->withStatus(200);
    }
)->setName('writeDarkCSS');

$app->get(
    '/get-dark-css',
    function ($request, $response) {
        $cssfile = GALETTE_CACHE_DIR . '/dark.css';
        if (file_exists($cssfile)) {
            $response = $response->withHeader('Content-type', 'text/css');
            $body = $response->getBody();
            $body->write(file_get_contents($cssfile));
        }
        return $response;
    }
)->setName('getDarkCSS');
