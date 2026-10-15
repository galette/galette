<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\MapsController;
use Galette\Middleware\Authenticate;

/**
 * @var \Slim\App<\DI\Container> $app
 */

//logged-in member position
$app->get(
    '/member/position',
    [MapsController::class, 'localizeMe']
)->setName('mapsLocalizeMe')->add(Authenticate::class);

$app->get(
    '/member/position/{id:\d+}',
    [MapsController::class, 'localizeMember']
)->setName('mapsLocalizeMember')->add(Authenticate::class);

$app->post(
    '/member/position/store[/{id:\d+}]',
    [MapsController::class, 'storePosition']
)->setName('mapsStorePosition')->add(Authenticate::class);

$app->get(
    '/maps/settings',
    [MapsController::class, 'settings']
)->setName('mapsSettings')->add(Authenticate::class);

$app->post(
    '/maps/settings',
    [MapsController::class, 'doSettings']
)->setName('doMapsSettings')->add(Authenticate::class);
