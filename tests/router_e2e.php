<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Router script for the built-in PHP server in e2e testing context.
 *
 * Usage:
 *   php -S 0.0.0.0:8090 -t galette/webroot tests/router_e2e.php
 *
 * Role:
 *   - Defines the test environment before Galette bootstrap
 *   - Handles clean URLs by delegating to index.php when the requested URI doesn't match an existing file in the webroot
 */

require_once __DIR__ . '/test_env.inc.php';

// Galette root
$docRoot = realpath(__DIR__ . '/../galette/webroot'); //@phpstan-ignore theCodingMachineSafe.function

// Requested path, without query string
$uri = urldecode(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH)); //@phpstan-ignore theCodingMachineSafe.function

// If the file exists in the webroot (static or specific PHP like installer.php)
if ($uri !== '/' && file_exists($docRoot . $uri)) {
    if (str_ends_with($uri, '.php')) {
        require $docRoot . $uri;
        exit;
    }
    // Let the PHP server serve the static file
    return false;
}

// Everything else (Slim routes) → Galette entry point
require $docRoot . '/index.php';
