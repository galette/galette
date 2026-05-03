<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\GaletteController;
use Galette\Controllers\ImagesController;

use function Safe\file_get_contents;
use function Safe\file_put_contents;

/**
 * @var \Slim\App<\DI\Container> $app
 * @var \Slim\Routing\RouteParser $routeparser
 * @var \Galette\Middleware\Authenticate $authenticate
 */

//main route
$app->get(
    '/',
    [GaletteController::class, 'slash']
)->setName('slash');

$app->get(
    '/{url:favicon.ico|robots.txt}',
    [GaletteController::class, 'empty']
)->setName('defaultEmpty');

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
