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
 * - A flag has a stage: "dev" (default) or "preview"
 *   - dev flags are unfinished work; they are only enabled in debug mode, when
 *     declared via GALETTE_FEATURE_FLAGS in behavior.inc.php
 *   - preview flags are finished but not mature yet; the super administrator
 *     turns them on from the advanced configuration page, debug mode or not
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
 * Format (preview flag):
 *   'flag_name' => [
 *       'description' => 'Description of the feature', // console, logs
 *       'stage' => 'preview',
 *       'label' => fn(): string => _T('Name shown in the interface'),
 *       'risk' => fn(): string => _T('What turning it on puts at stake'),
 *   ]
 *
 * Label and risk are closures: this file is read each time the flags are
 * loaded, and they only have to be translated when the interface shows them.
 */

/** @var array<string, string|array{description: string, requires?: array<string>, stage?: string, label?: \Closure(): string, risk?: \Closure(): string}> $feature_flags_registry */
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
     * Mandatory two-factor authentication policies
     *
     * The second factor itself ships in 1.3.0, disabled by default and marked
     * experimental. Making it compulsory -- for administrators and staff, or
     * for everyone -- is a preview: under a mandatory policy a clock that
     * drifts or a botched enrolment can put members outside their own
     * instance. The galette:twofactor:reset console command is the way back.
     * Without this flag both policies read as "optional", so a member already
     * enrolled keeps being asked for their code.
     *
     * Status: Preview
     * Added: 2026-09-06
     * Target: 1.4.0
     */
    'two-factor-required' => [
        'description' => 'Mandatory two-factor authentication policies (staff, everyone)',
        'stage' => 'preview',
        'label' => fn(): string => _T('Mandatory two-factor authentication'),
        'risk' => fn(): string => _T('Lets the settings require a second factor from administrators and staff, or from everyone. A member who loses their device or whose phone clock drifts cannot log in any more until their second factor is reset; the galette:twofactor:reset console command resets any account, the super administrator included, or turns the policy off.'),
    ],

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
