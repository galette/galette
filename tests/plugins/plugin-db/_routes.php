<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** @var \Slim\Routing\RouteCollectorProxy<\Psr\Container\ContainerInterface|null> $app */

//the plugin root page, served instead of the generic plugin information page
$app->get(
    '',
    fn(Request $request, Response $response): Response => $response,
)->setName('plugdb_root');
