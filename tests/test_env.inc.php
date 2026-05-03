<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Shared test environment configuration
 */
//@phpstan-ignore theCodingMachineSafe.function
if (!defined('GALETTE_ROOT')) {
    define('GALETTE_ROOT', realpath(__DIR__ . '/../galette') . '/'); //@phpstan-ignore theCodingMachineSafe.function,theCodingMachineSafe.function
}

// Determine database type
$db = 'mysql';
$dbenv = (string)getenv('DB');
if (
    $dbenv === 'pgsql'
    || str_starts_with($dbenv, 'postgres')
) {
    $db = 'pgsql';
}

$testenv = getenv('TESTENV');
$fail_env = $testenv === 'FAIL';
if ($fail_env !== false) {
    $db .= '_fail';
}

// Base path
if (!isset($basepath)) {
    if (file_exists('../galette/index.php')) {
        $basepath = '../galette/';
    } elseif (file_exists('galette/index.php')) {
        $basepath = 'galette/';
    } elseif (file_exists(__DIR__ . '/../galette/index.php')) {
        $basepath = __DIR__ . '/../galette/';
    } else {
        $basepath = './galette/';
    }
}

if (!defined('GALETTE_BASE_PATH')) {
    // Force current dir if we are served via php built-in server (e2e context)
    if (php_sapi_name() === 'cli-server') { //@phpstan-ignore theCodingMachineSafe.function
        define('GALETTE_BASE_PATH', './'); //@phpstan-ignore theCodingMachineSafe.function
    } else {
        define('GALETTE_BASE_PATH', $basepath); //@phpstan-ignore theCodingMachineSafe.function
    }
}

// DO NOT define GALETTE_TESTS if we are in a web context (e2e)
// as it prevents the application from initializing and running Slim.
if (php_sapi_name() !== 'cli-server' && !defined('GALETTE_TESTS')) { //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_TESTS', true); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('GALETTE_TESTS_PATH')) {
    define('GALETTE_TESTS_PATH', __DIR__); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('GALETTE_CONFIG_PATH')) {
    define('GALETTE_CONFIG_PATH', GALETTE_TESTS_PATH . '/config/' . $db . '/'); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('GALETTE_DATA_PATH')) {
    define('GALETTE_DATA_PATH', GALETTE_TESTS_PATH . '/tests-data/'); //@phpstan-ignore theCodingMachineSafe.function
}

// Ensure tests-data directory exists
if (!is_dir(GALETTE_DATA_PATH)) {
    mkdir(GALETTE_DATA_PATH, 0o777, true); //@phpstan-ignore theCodingMachineSafe.function
}

// Map of directories to ensure they exist (as they might be used in realpath())
$test_directories = [
    'GALETTE_LOGS_PATH'         => 'logs/',
    'GALETTE_CACHE_DIR'         => 'cache/',
    'GALETTE_EXPORTS_PATH'       => 'exports/',
    'GALETTE_IMPORTS_PATH'       => 'imports/',
    'GALETTE_PHOTOS_PATH'        => 'photos/',
    'GALETTE_DOCUMENTS_PATH'     => 'documents/',
    'GALETTE_ATTACHMENTS_PATH'   => 'attachments/',
    'GALETTE_FILES_PATH'         => 'files/',
    'GALETTE_TEMPIMAGES_PATH'    => 'tempimages/',
    'GALETTE_PLUGINS_DATA_PATH'  => 'plugins/',
    'GALETTE_TPL_CACHE_DIR'      => 'templates_c/',
    'GALETTE_SESSIONS_PATH'     => 'sessions/'
];

foreach ($test_directories as $constant => $suffix) {
    $path = GALETTE_DATA_PATH . $suffix;
    if (!defined($constant)) {
        define($constant, $path); //@phpstan-ignore theCodingMachineSafe.function
    }
    if (!is_dir($path)) {
        mkdir($path, 0o777, true); //@phpstan-ignore theCodingMachineSafe.function
    }
}

// Force session path to be local for tests to avoid issues on CI
if (php_sapi_name() === 'cli-server') { //@phpstan-ignore theCodingMachineSafe.function
    ini_set('session.save_path', GALETTE_SESSIONS_PATH); //@phpstan-ignore theCodingMachineSafe.function,constant.notFound
}

// Allow overriding GALETTE_PLUGINS_PATH via environment variable
// e.g.: GALETTE_PLUGINS_PATH=/path/to/plugins php -S ...
if (!defined('GALETTE_PLUGINS_PATH')) {
    $plugins_path_env = getenv('GALETTE_PLUGINS_PATH');
    if ($plugins_path_env !== false && $plugins_path_env !== '') {
        define('GALETTE_PLUGINS_PATH', rtrim($plugins_path_env, '/') . '/'); //@phpstan-ignore theCodingMachineSafe.function
    } else {
        define('GALETTE_PLUGINS_PATH', GALETTE_TESTS_PATH . '/plugins/'); //@phpstan-ignore theCodingMachineSafe.function
    }
}

if (!defined('GALETTE_MODE')) {
    define('GALETTE_MODE', 'PROD'); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('GALETTE_TPL_SUBDIR')) {
    define('GALETTE_TPL_SUBDIR', 'templates/default/'); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('GALETTE_THEME')) {
    define('GALETTE_THEME', 'themes/default/'); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('_CURRENT_THEME_PATH')) {
    define( //@phpstan-ignore theCodingMachineSafe.function
        '_CURRENT_THEME_PATH',
        GALETTE_ROOT . 'webroot/themes/default/'
    );
}
