<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

/**
 * Plugin definition
 *
 * Returned by the plugin _define.php file:
 *
 * ```php
 * use Galette\Core\Plugins\PluginDefinition;
 *
 * return new PluginDefinition(
 *     name: 'Galette Maps',
 *     desc: 'Maps features',
 *     author: 'Johan Cwiklinski',
 *     version: '2.3.0',
 *     compver: '1.4.0',
 *     route: 'maps',
 *     date: '2026-10-03',
 *     acls: ['maps_mymap' => 'member'],
 *     dbver: 1.00
 * );
 * ```
 *
 * Plugin main class is not loaded to read it, so an outdated plugin can be
 * disabled before it breaks anything.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class PluginDefinition
{
    /**
     * @param string                $name            Plugin name
     * @param string                $desc            Plugin description
     * @param string                $author          Plugin author name
     * @param string                $version         Plugin version
     * @param ?string               $compver         Galette version compatibility
     * @param ?string               $route           Plugin route name, also used as translation domain
     * @param ?string               $date            Plugin release date
     * @param ?array<string,string> $acls            Plugin routes ACLs
     * @param ?int                  $priority        Plugin priority; lowest comes first
     * @param ?float                $dbver           Plugin database version
     * @param ?string               $repository      GitHub repository releases are published on,
     *                                               as owner/name. Defaults to galette-plugins/<plugin directory>
     * @param ?array<string>        $csrf_exclusions Routes excluded from CSRF checks, as regular expressions
     */
    public function __construct(
        public string $name,
        public string $desc,
        public string $author,
        public string $version,
        public ?string $compver = null,
        public ?string $route = null,
        public ?string $date = null,
        public ?array $acls = null,
        public ?int $priority = 1000,
        public ?float $dbver = null,
        public ?string $repository = null,
        public ?array $csrf_exclusions = null
    ) {
    }
}
