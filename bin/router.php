<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Router script for the built-in PHP server in a development context.
 *
 * Usage:
 *   bin/serve
 *   php -S 127.0.0.1:8100 -t galette/webroot bin/router.php
 *
 * Role:
 *   - Serves the real installation of the checkout: galette/config/config.inc.php,
 *     galette/data/ and galette/plugins/ are all used as-is. This is what sets it
 *     apart from tests/router_e2e.php, which redirects configuration, data and
 *     plugins to their test counterparts under tests/.
 *   - Handles clean URLs by delegating to index.php when the requested URI does not
 *     match an existing file in the webroot, the built-in server having no
 *     equivalent to the mod_rewrite rules of galette/webroot/.htaccess.
 */

//index.php assumes it is reached through the webroot and defines '../', which
//breaks every generated link and asset under the built-in server. Same reason as
//the cli-server special case of tests/test_env.inc.php.
if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', './'); //@phpstan-ignore theCodingMachineSafe.function
}

//Keep the mutable state of the instance under .serve/, so that it is wiped along
//with the rest of it and, above all, never fought over with another server. A
//checkout also served by Apache has a galette/data/cache/ subtree owned by the web
//server user, which the built-in server - running as the developer - cannot write
//to; paths.inc.php honours an already defined GALETTE_CACHE_DIR, and
//dependencies.php:50 derives the Twig cache from it.
$state_path = __DIR__ . '/../.serve';

if (!defined('GALETTE_CACHE_DIR')) {
    define('GALETTE_CACHE_DIR', $state_path . '/cache/'); //@phpstan-ignore theCodingMachineSafe.function
}
if (!is_dir(GALETTE_CACHE_DIR)) {
    mkdir(GALETTE_CACHE_DIR, 0755, true); //@phpstan-ignore theCodingMachineSafe.function
}

$sessions_path = $state_path . '/sessions';
if (!is_dir($sessions_path)) {
    mkdir($sessions_path, 0700, true); //@phpstan-ignore theCodingMachineSafe.function
}
ini_set('session.save_path', $sessions_path); //@phpstan-ignore theCodingMachineSafe.function

$doc_root = realpath(__DIR__ . '/../galette/webroot'); //@phpstan-ignore theCodingMachineSafe.function

// Requested path, without query string
$uri = urldecode(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH)); //@phpstan-ignore theCodingMachineSafe.function

// If the file exists in the webroot (static or specific PHP like installer.php)
if ($uri !== '/' && file_exists($doc_root . $uri)) {
    if (str_ends_with($uri, '.php')) {
        require $doc_root . $uri;
        exit;
    }
    // Let the PHP server serve the static file
    return false;
}

// Everything else (Slim routes) → Galette entry point
require $doc_root . '/index.php';
