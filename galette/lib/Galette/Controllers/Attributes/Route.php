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
 * Declares a route that calls this controller method.
 *
 * Routes are registered from those attributes by Galette\Core\Routing\RouteLoader.
 * Multiple Route attributes can be added to a method if it's called by multiple routes.
 * A route calling an inherited method is declared on the class, naming the method
 * with $handler.
 *
 * Routes requiring authentication must have a matching ACL, see includes/core_acls.php.
 * For plugins, the pattern is relative to the plugin route (/plugins/{route}).
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
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
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
     * @param string|null     $handler      Method to call; only for a Route on a class
     * @param class-string[]  $middleware   Additional route middlewares, added in order.
     *                                      Authentication, if required, runs before them.
     * @param bool            $debugOnly    Only declared when debug mode is enabled
     */
    public function __construct(
        public readonly string $name,
        public readonly string $pattern,
        public readonly string|array $methods = 'GET',
        public readonly ?string $description = null,
        public readonly bool $requiresAuth = true,
        public readonly ?string $handler = null,
        public readonly array $middleware = [],
        public readonly bool $debugOnly = false
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
