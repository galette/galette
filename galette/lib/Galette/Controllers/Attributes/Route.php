<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Attributes;

use Attribute;

/**
 * Documents a route that calls this controller method.
 *
 * This attribute is for developer reference and documentation only.
 * Actual routes MUST still be declared in galette/includes/routes/*.routes.php files.
 * Multiple Route attributes can be added to a method if it's called by multiple routes.
 *
 * Example usage:
 * ```php
 * #[Route(
 *     name: 'members',
 *     pattern: '/members[/{option:page|order}/{value:\d+|\w+}]',
 *     methods: 'GET',
 *     description: 'Display members list with optional pagination'
 * )]
 * public function list(Request $request, Response $response): Response
 * {
 *     // ...
 * }
 * ```
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * Constructor
     *
     * @param string          $name         Route name (as used in setName())
     * @param string          $pattern      URL pattern (e.g., '/members', '/member/{id:\d+}')
     * @param string|string[] $methods      HTTP methods (e.g., 'GET', ['GET', 'POST'])
     * @param string|null     $description  Optional description of what this route does
     * @param bool            $requiresAuth Whether this route requires authentication (default: true)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $pattern,
        public readonly string|array $methods = 'GET',
        public readonly ?string $description = null,
        public readonly bool $requiresAuth = true
    ) {
    }

    /**
     * Get methods as array
     *
     * @return string[]
     */
    public function getMethods(): array
    {
        return is_array($this->methods) ? $this->methods : [$this->methods];
    }
}
