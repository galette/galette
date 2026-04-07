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

/**
 * Feature Flags Registry with Dependencies Support
 *
 * This file contains the official registry of all feature flags in Galette.
 *
 * IMPORTANT:
 * - Adding a flag to this registry is MANDATORY when developing a new feature
 * - Flags should be removed from this registry only when the feature is considered stable
 * - Users activate flags via GALETTE_FEATURE_FLAGS in behavior.inc.php
 *
 * Format (simple flag without dependencies):
 *   'flag_name' => 'Description of the feature'
 *
 * Format (flag with dependencies):
 *   'flag_name' => [
 *       'description' => 'Description of the feature',
 *       'requires' => ['dependency1', 'dependency2'], // Optional
 *   ]
 */

/** @var array<string, string|array{description: string, requires?: array<string>}> $feature_flags_registry */
$feature_flags_registry = [
    /**
     * ACLs - New Access Control Lists Management System
     *
     * Implements a new RBAC (Role-Based Access Control) system to replace
     * the legacy permission system.
     *
     * Status: In Development
     * Added: 2026-04-08
     * Target: 1.2.0
     */
    /*'acls' => 'New Access Control Lists (RBAC) management system',*/

    /**
     * OAuth2 - OAuth2 Authentication System
     *
     * OAuth2 server implementation for API authentication.
     * Requires ACLs for permission management.
     *
     * Status: Planning
     * Added: 2026-04-08
     * Target: 1.3.0
     */
    /*'oauth2' => [
        'description' => 'OAuth2 authentication system for API',
        'requires' => ['acls'], // Depends on ACLs
    ],*/

    /**
     * New Dashboard - Redesigned admin dashboard
     *
     * Modern dashboard with improved UX and better data visualization.
     *
     * Status: Planning
     * Added: 2026-04-08
     * Target: 1.3.0
     */
    /*'new-dashboard' => 'Redesigned admin dashboard with modern UI',*/

    /**
     * API v2 - RESTful API with OAuth2
     *
     * New REST API version with OAuth2 authentication support.
     * Requires both ACLs for permissions and OAuth2 for authentication.
     *
     * Status: Planning
     * Added: 2026-04-08
     * Target: 1.3.0
     */
    /*'api-v2' => [
        'description' => 'RESTful API version 2 with OAuth2 support',
        'requires' => ['acls', 'oauth2'], // Depends on ACLs AND OAuth2
    ],*/

    /**
     * Add new feature flags below following this format:
     *
     * Simple flag without dependencies:
     * 'flag-name' => 'Short description of the feature',
     *
     * Flag with dependencies:
     * 'flag-name' => [
     *     'description' => 'Short description',
     *     'requires' => ['dependency-flag-1', 'dependency-flag-2'],
     * ],
     */
];

return $feature_flags_registry;
