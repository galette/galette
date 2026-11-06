<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
 * - Every registered flag can be turned on by the super administrator, from
 *   the advanced configuration page or the console; GALETTE_FEATURE_FLAGS in
 *   behavior.inc.php takes precedence
 *
 * Format (simple flag without dependencies):
 *   'flag_name' => 'Description of the feature'
 *
 * Format (flag with dependencies):
 *   'flag_name' => [
 *       'description' => 'Description of the feature',
 *       'requires' => ['dependency1', 'dependency2'], // Optional
 *   ]
 *
 * Existing statuses (informal):
 * - Planning: The feature is planned but not yet implemented.
 * - In Development: The feature is currently being developed (not available).
 * - Experimental: The feature is implemented but not yet stable; it may have bugs or incomplete functionality (can be enabled).
 * - Testing: The feature is implemented and considered stable for testing purposes; it may still have some issues (can be enabled).
 * - Stable: The feature is fully implemented and considered stable for production use (cannot be disabled).
 */

/** @var array<string, string|array{description: string, requires?: array<string>}> $feature_flags_registry */
$feature_flags_registry = [
    /**
     * OAuth2 - OAuth2 Authentication System
     *
     * OAuth2 server implementation for API authentication.
     *
     * Status: Planning
     * Added: 2026-04-08
     * Target: 1.3.0
     */
    /*'oauth2' => 'OAuth2 authentication system for API',*/

    /**
     * API v2 - RESTful API with OAuth2
     *
     * New REST API version with OAuth2 authentication support.
     * Requires OAuth2 for authentication.
     *
     * Status: Planning
     * Added: 2026-04-08
     * Target: 1.3.0
     */
    /*'api-v2' => [
        'description' => 'RESTful API version 2 with OAuth2 support',
        'requires' => ['oauth2'], // Depends on OAuth2
    ],*/

    /**
     * Mandatory two-factor authentication policies
     *
     * The second factor itself ships in 1.3.0, disabled by default and marked
     * experimental. Making it compulsory -- for administrators and staff, or
     * for everyone.
     * The galette:twofactor:reset console command is the way back.
     * Without this flag both policies read as "optional", so a member already
     * enrolled keeps being asked for their code.
     *
     * Status: Testing
     * Added: 2026-09-06
     * Target: 1.4.0
     */
    'two-factor-required' => 'Mandatory two-factor authentication policies',

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
