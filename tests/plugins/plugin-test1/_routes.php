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

/** @var \Slim\Routing\RouteCollectorProxy<\Psr\Container\ContainerInterface|null> $app */

//a public page, whose visibility is declared by the plugin
$app->get(
    '/public/page',
    fn(Request $request, Response $response): Response => $response,
)->setName('plugin1_public_page')->add(PublicPages::class);

//a public page left undeclared, which follows the default visibility
$app->get(
    '/public/other',
    fn(Request $request, Response $response): Response => $response,
)->setName('plugin1_public_other')->add(PublicPages::class);
