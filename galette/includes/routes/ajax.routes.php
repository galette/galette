<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\AjaxController;
use Slim\Routing\RouteCollectorProxy;

/**
 * @var \Slim\App<\DI\Container> $app
 * @var \Slim\Routing\RouteParser $routeparser
 * @var \Galette\Middleware\Authenticate $authenticate
 */

$app->group('/ajax', function (RouteCollectorProxy $app) use ($authenticate): void {
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
    )->setName('telemetryInfos')->add($authenticate);

    $app->post(
        '/telemetry/send',
        [AjaxController::class, 'telemetrySend']
    )->setName('telemetrySend')->add($authenticate);

    $app->get(
        '/telemetry/registered',
        [AjaxController::class, 'telemetryRegistered']
    )->setName('setRegistered')->add($authenticate);

    $app->post(
        '/contribution/dates',
        [AjaxController::class, 'contributionDates']
    )->setName('contributionDates')->add($authenticate);

    $app->post(
        '/contribution/members[/{page:\d+}[/{search}]]',
        [AjaxController::class, 'contributionMembers']
    )->setName('contributionMembers')->add($authenticate);

    $app->post(
        '/password/strength',
        [AjaxController::class, 'passwordStrength']
    )->setName('checkPassword');
});
