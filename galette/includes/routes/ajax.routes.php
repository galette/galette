<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Ajax routes
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

use Galette\Controllers\AjaxController;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\Members;
use Galette\Filters\MembersList;

$app->group('/ajax', function () use ($authenticate) {
    $this->get(
        '/messages',
        [AjaxController::class, 'messages']
    )->setName('ajaxMessages');

    $this->post(
        'photo',
        [AjaxController::class, 'photo']
    )->setName('photoDnd');

    $this->post(
        '/suggest/towns/{term}',
        [AjaxController::class, 'suggestTowns']
    )->setName('suggestTown');

    $this->post(
        '/suggest/countries/{term}',
        [AjaxController::class, 'suggestCountries']
    )->setName('suggestCountry');

    $this->get(
        '/telemetry/infos',
        [AjaxController::class, 'telemetryInfos']
    )->setName('telemetryInfos')->add($authenticate);

    $this->post(
        '/telemetry/send',
        [AjaxController::class, 'telemetrySend']
    )->setName('telemetrySend')->add($authenticate);

    $this->get(
        '/telemetry/registered',
        [AjaxController::class, 'telemetryRegistered']
    )->setName('setRegistered')->add($authenticate);

    $this->post(
        '/contribution/dates',
        [AjaxController::class, 'contributionDates']
    )->setName('contributionDates')->add($authenticate);

    $this->post(
        '/contribution/members[/{page:\d+}[/{search}]]',
        [AjaxController::class, 'contributionMembers']
    )->setName('contributionMembers')->add($authenticate);

    $this->post(
        '/password/strength',
        [AjaxController::class, 'passwordStrength']
    )->setName('checkPassword');
});
