<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

declare(strict_types=1);

use Galette\Controllers\AuthController;
use Galette\Entity\Adherent;
use GaletteOAuth2\Controllers\LoginController;
use Mezzio\Authentication\OAuth2;

/**
 * @var \Slim\App<\DI\Container> $app
 * @var \Slim\Routing\RouteParser $routeparser
 * @var \Galette\Middleware\Authenticate $authenticate
 * @var \Galette\Core\FeatureFlagManager $feature_flags
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

if ($feature_flags->isEnabled('oauth2')) {
    $app->group('/oauth2', function () use ($app) {
        //$app->get('login', [LoginController::class, 'login']);

        $app->get(
            '/token',
            OAuth2\TokenEndpointHandler::class
        )->setName('oauth2-token');

    });
}
