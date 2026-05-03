<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

// define relative base path templating can use
if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', '../'); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('GALETTE_ROOT')) {
    define('GALETTE_ROOT', __DIR__ . '/../'); //@phpstan-ignore theCodingMachineSafe.function
}

// check PHP version
require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';
if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<')) { //@phpstan-ignore if.alwaysFalse
    header('location: ' . GALETTE_BASE_PATH . 'compat_test.php');
    die(1);
}

// check PHP modules
require_once GALETTE_ROOT . '/vendor/autoload.php';

$cm = new Galette\Core\CheckModules(false);
$cm->doCheck(false); //do not load with translations!

if (!$cm->isValid()) {
    header('location: ' . GALETTE_BASE_PATH . 'compat_test.php');
    die(1);
}

/** @ignore */
require_once __DIR__ . '/../includes/main.inc.php';
