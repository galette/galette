<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Middleware\PublicPages;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @var \Slim\Routing\RouteCollectorProxy<\Psr\Container\ContainerInterface|null> $app
 * @var string $module_id
 * @var \Psr\Container\ContainerInterface|null $container
 */

//routes are declared with attributes on controllers; this file remains for
//those that cannot be, and to check it is still included

//a public page left undeclared, which follows the default visibility
$app->get(
    '/public/other',
    fn(Request $request, Response $response): Response => $response,
)->setName('plugin1_public_other')->add(PublicPages::class);

//older routes files rely on $module_id and $container
if ($container !== null) {
    $app->get(
        '/legacy',
        fn(Request $request, Response $response): Response => $response,
    )->setName('plugin1_legacy_' . $module_id);
}
