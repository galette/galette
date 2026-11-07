<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins;
use Galette\Core\SlimApp;
use RKA\SessionMiddleware;

use function Safe\define;
use function Safe\ini_set;

if (!defined('GLOB_BRACE')) {
    \define('GLOB_BRACE', 0); //@phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
}

//define galette's root directory
if (!defined('GALETTE_ROOT')) {
    \define('GALETTE_ROOT', __DIR__ . '/../'); //@phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
}

// define relative base path templating can use
if (!defined('GALETTE_BASE_PATH')) {
    \define('GALETTE_BASE_PATH', '../'); //@phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
}
/** @var bool $needs_update */
$needs_update = false;
/** @ignore */
require_once GALETTE_ROOT . 'includes/galette.inc.php';

/** @var Plugins $plugins */

//CONFIGURE AND START SESSION

//Session duration
//See https://php.net/manual/en/session.configuration.php#ini.session.cookie-lifetime
//Preferences are unavailable from the installer, from tests, and while database
//needs an update; the constant is then the only source left.
//GALETTE_TIMEOUT is deliberately not defined from the preference: doing so
//would make the setting look overridden by behavior.inc.php on the advanced
//configuration page, and log a bogus warning on every read.
$session_lifetime = isset($preferences)
    ? (int)$preferences->getConfigValue('pref_session_timeout')
    : (defined('GALETTE_TIMEOUT') ? (int)GALETTE_TIMEOUT : 0);

$session_name = '';
//since PREFIX_DB and NAME_DB are required to properly instantiate sessions,
// we have to check here if they're assigned
/** @var bool $installer */
if ($installer || !defined('PREFIX_DB') || !defined('NAME_DB')) {
    $session_name = 'install_' . str_replace('.', '_', GALETTE_VERSION);
} else {
    $session_name = PREFIX_DB . '_' . NAME_DB . '_' . str_replace('.', '_', GALETTE_VERSION);
}
$session_name = 'galette_' . $session_name;
$session = new SessionMiddleware([
    'name'      => $session_name,
    'lifetime'  => $session_lifetime
]);

if (session_status() === PHP_SESSION_NONE) {
    //Do not send the session cookie along with cross site requests.
    //"Lax" rather than "Strict", so that following a link from an
    //email or from the association website does not look like a logged out
    //session.
    ini_set('session.cookie_samesite', 'Lax');
    $session->start();
}
//Galette needs database update!
if ($needs_update) { //@phpstan-ignore if.alwaysFalse (variable defined in galette.inc.php)
    define('GALETTE_THEME', 'themes/default/');
    $gapp = new SlimApp(plugins: $plugins, mode: SlimApp::MODE_NEED_UPDATE);
} else {
    $gapp = new SlimApp(plugins: $plugins);
}
/** @var \Analog\Logger $logger */
$gapp->configure($session, $logger);
$app = $gapp->getApp();
//global $container is still read by legacy code and tests bootstrap
$container = $gapp->getContainer(); //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable

if (!defined('GALETTE_TESTS')) {
    $app->run();
}

if (isset($profiler)) {
    $profiler->stop();
}
