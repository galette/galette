<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

use Galette\Controllers\AjaxController;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\Members;
use Galette\Filters\MembersList;
use Slim\Routing\RouteCollectorProxy;

$app->group('/ajax', function (RouteCollectorProxy $app) use ($authenticate) {
    $app->get(
        '/messages',
        [AjaxController::class, 'messages']
    )->setName('ajaxMessages');

    $app->post(
        'photo',
        [AjaxController::class, 'photo']
    )->setName('photoDnd');

    $app->post(
        '/suggest/towns/{term}',
        [AjaxController::class, 'suggestTowns']
    )->setName('suggestTown');

    $app->post(
        '/suggest/countries/{term}',
        [AjaxController::class, 'suggestCountries']
    )->setName('suggestCountry');

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
