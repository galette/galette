<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\GaletteController;
use Galette\Controllers\ImagesController;
use Galette\Middleware\Authenticate;
use Galette\Core\Galette;
use Slim\Routing\RouteCollectorProxy;

use function Safe\phpinfo;
use function Safe\ob_start;
use function Safe\ob_get_clean;

/**
 * @var \Slim\App<\DI\Container> $app
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
)->setName('sysinfos')->add(Authenticate::class);

$app->post(
    '/write-dark-css',
    [GaletteController::class, 'writeDarkCss']
)->setName('writeDarkCSS');

$app->get(
    '/get-dark-css',
    [GaletteController::class, 'getDarkCss']
)->setName('getDarkCSS');

if (Galette::isDebugEnabled()) {
    $app->group('/debug', function (RouteCollectorProxy $app): void {
        $app->get('/phpinfo/', function ($request, $response) {
            ob_start();
            phpinfo();
            $phpinfo = ob_get_clean();

            $response->getBody()->write($phpinfo);
            return $response;
        });

        $app->get('/routes/', function ($request, $response) use ($app) {
            $routes = $app->getRouteCollector()->getRoutes();
            foreach ($routes as $route) {
                echo $route->getIdentifier() . " → ";
                echo ($route->getName() ?? "(unnamed)") . " → ";
                echo $route->getPattern();
                echo "<br><br>";
            }

            return $response;
        });
    });
}
