<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Authentication related routes
 *
 * PHP version 5
 *
 * Copyright © 2014-2020 The Galette Team
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
 * @copyright 2014-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Controllers\AuthController;
use Galette\Entity\Adherent;

//login page
$app->get(
    '/login[/{r:.+}]',
    [AuthController::class, 'login']
)->setName('login');

//Authentication procedure
$app->post(
    '/login',
    [AuthController::class, 'dologin']
)->setName('dologin');

//logout procedure
$app->get(
    '/logout',
    [AuthController::class, 'logout']
)->setName('logout');

//impersonating
$app->get(
    '/impersonate/{id:\d+}',
    [AuthController::class, 'impersonate']
)->setName('impersonate')->add($authenticate);

$app->get(
    '/unimpersonate',
    [AuthController::class, 'unimpersonate']
)->setName('unimpersonate')->add($authenticate);

//password lost page
$app->get(
    '/password-lost',
    [AuthController::class, 'lostPassword']
)->setName('password-lost');

//retrieve password procedure
$app->map(
    ['GET', 'POST'],
    '/retrieve-pass' . '[/{' . Adherent::PK . ':\d+}]',
    [AuthController::class, 'retrievePassword']
)->setName('retrieve-pass');

//password recovery page
$app->get(
    '/password-recovery/{hash}',
    [AuthController::class, 'recoverPassword']
)->setName('password-recovery');

//password recovery page
$app->post(
    '/password-recovery',
    [AuthController::class, 'doRecoverPassword']
)->setName('do-password-recovery');
