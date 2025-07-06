<?php

/**
 * Copyright © 2003-2025 The Galette Team
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
)->setName('defaultFavicon');

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
