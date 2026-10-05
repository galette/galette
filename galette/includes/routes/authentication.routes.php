<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\AuthController;
use Galette\Controllers\TwoFactorController;
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
    [AuthController::class, 'doLogin']
)->setName('dologin');

//second factor challenge; no Authenticate middleware on purpose, the session
//holds accepted credentials but is not logged in until the factor is produced.
//Kept out of /login: that route is a catch-all ('/login[/{r:.+}]') declared
//above, and would swallow any sub path.
$app->get(
    '/two-factor',
    [TwoFactorController::class, 'challenge']
)->setName('two-factor');

$app->post(
    '/two-factor',
    [TwoFactorController::class, 'doChallenge']
)->setName('do-two-factor');

//second factor management, from the member's own account
$app->get(
    '/two-factor/manage',
    [TwoFactorController::class, 'manage']
)->setName('two-factor-manage')->add(Authenticate::class);

$app->get(
    '/two-factor/enrol',
    [TwoFactorController::class, 'enrol']
)->setName('two-factor-enrol')->add(Authenticate::class);

$app->post(
    '/two-factor/enrol',
    [TwoFactorController::class, 'doEnrol']
)->setName('do-two-factor-enrol')->add(Authenticate::class);

$app->post(
    '/two-factor/disable',
    [TwoFactorController::class, 'doDisable']
)->setName('do-two-factor-disable')->add(Authenticate::class);

$app->post(
    '/two-factor/recovery-codes',
    [TwoFactorController::class, 'doRenewCodes']
)->setName('do-two-factor-codes')->add(Authenticate::class);

//staff can clear a member's second factor, for the member who lost both their
//device and their recovery codes
$app->post(
    '/two-factor/reset/{id:\d+}',
    [TwoFactorController::class, 'doReset']
)->setName('do-two-factor-reset')->add(Authenticate::class);

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

//authentication attempts currently refused
$app->get(
    '/authentication-attempts',
    [AuthController::class, 'authAttempts']
)->setName('authAttempts')->add(Authenticate::class);

//lift a refused authentication attempt
$app->post(
    '/authentication-attempts',
    [AuthController::class, 'doAuthAttempts']
)->setName('doAuthAttempts')->add(Authenticate::class);
