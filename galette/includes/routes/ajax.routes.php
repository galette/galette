<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\AjaxController;
use Galette\Middleware\Authenticate;
use Slim\Routing\RouteCollectorProxy;

/**
 * @var \Slim\App<\DI\Container> $app
 */

$app->group('/ajax', function (RouteCollectorProxy $app): void {
    $app->get(
        '/messages',
        [AjaxController::class, 'messages']
    )->setName('ajaxMessages');

    $app->post(
        '/suggest/towns/{term}',
        [AjaxController::class, 'suggestTowns']
    )->setName('suggestTown');

    $app->post(
        '/suggest/countries/{term}',
        [AjaxController::class, 'suggestCountries']
    )->setName('suggestCountry');

    $app->post(
        '/suggest/regions/{term}',
        [AjaxController::class, 'suggestRegions']
    )->setName('suggestRegion');

    $app->get(
        '/telemetry/infos',
        [AjaxController::class, 'telemetryInfos']
    )->setName('telemetryInfos')->add(Authenticate::class);

    $app->post(
        '/telemetry/send',
        [AjaxController::class, 'telemetrySend']
    )->setName('telemetrySend')->add(Authenticate::class);

    $app->get(
        '/telemetry/registered',
        [AjaxController::class, 'telemetryRegistered']
    )->setName('setRegistered')->add(Authenticate::class);

    $app->post(
        '/contribution/dates',
        [AjaxController::class, 'contributionDates']
    )->setName('contributionDates')->add(Authenticate::class);

    $app->post(
        '/contribution/members[/{page:\d+}[/{search}]]',
        [AjaxController::class, 'contributionMembers']
    )->setName('contributionMembers')->add(Authenticate::class);

    $app->post(
        '/password/strength',
        [AjaxController::class, 'passwordStrength']
    )->setName('checkPassword');
});
