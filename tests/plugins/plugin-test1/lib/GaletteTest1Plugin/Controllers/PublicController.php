<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest1Plugin\Controllers;

use Galette\Controllers\Attributes\Route;
use Galette\Middleware\PublicPages;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Test plugin public pages
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PublicController
{
    /**
     * A public page, whose visibility is declared by the plugin
     */
    #[Route(
        name: 'plugin1_public_page',
        pattern: '/public/page',
        requiresAuth: false,
        middleware: [PublicPages::class]
    )]
    public function page(Response $response): Response
    {
        return $response;
    }

    /**
     * Any other public page; its pattern matches the routes file ones
     */
    #[Route(
        name: 'plugin1_any_page',
        pattern: '/{page:[a-z]+}',
        requiresAuth: false,
        middleware: [PublicPages::class],
        fallback: true
    )]
    public function anyPage(Response $response): Response
    {
        return $response;
    }
}
