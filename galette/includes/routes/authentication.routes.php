<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\AuthController;
use Galette\Entity\Adherent;
use Galette\Middleware\Authenticate;

/**
 * @var \Slim\App<\DI\Container> $app
 */

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
)->setName('impersonate')->add(Authenticate::class);

$app->get(
    '/unimpersonate',
    [AuthController::class, 'unimpersonate']
)->setName('unimpersonate')->add(Authenticate::class);

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
