<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's instanciation and routes
 *
 * PHP version 5
 *
 * Copyright © 2012-2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-10
 */

use Slim\Slim;
use Slim\Route;
use Galette\Core\Login;
use Analog\Analog;

$time_start = microtime(true);

//define galette's root directory
if (!defined('GALETTE_ROOT')) {
    define('GALETTE_ROOT', __DIR__ . '/../');
}

// define relative base path templating can use
if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', '../');
}

$needs_update = false;
/** @ignore */
require_once GALETTE_ROOT . 'includes/galette.inc.php';

//Galette needs database update!
if ($needs_update) {
    $app = new \Slim\App(
        array(
            'templates.path'    => GALETTE_ROOT . 'templates/default/',
            'mode'              => 'NEED_UPDATE'
        )
    );

    define(
        'GALETTE_THEME',
        'themes/default/'
    );

    require_once GALETTE_ROOT . 'includes/dependencies.php';

    $app->add(
        new \Galette\Middleware\UpdateAndMaintenance(
            $i18n,
            \Galette\Middleware\UpdateAndMaintenance::NEED_UPDATE
        )
    );

    $app->run();
    die();
} else {
    $app = new \Slim\App(
        [
            'settings' => [
                'determineRouteBeforeAppMiddleware' => true,
                'displayErrorDetails' => (GALETTE_MODE === 'DEV'),
                'addContentLengthHeader' => false,
                // monolog settings
                'logger' => [
                    'name'  => 'galette',
                    'level' => \Monolog\Logger::DEBUG,
                    'path'  => GALETTE_LOGS_PATH . '/galette_slim.log',
                ],
                //'routerCacheFile' => (GALETTE_MODE === 'DEV') ? false : GALETTE_CACHE_DIR . '/fastroute.cache' //disabled until properly handled
            ],
            'mode'      => 'PROD'
        ]
    );
}

//handle notices
set_error_handler(function ($severity, $message, $file, $line) {
    if (GALETTE_MODE === 'DEV') {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
});

//Session duration
if (!defined('GALETTE_TIMEOUT')) {
    //See https://php.net/manual/en/session.configuration.php#ini.session.cookie-lifetime
    define('GALETTE_TIMEOUT', 0);
}

$plugins = new Galette\Core\Plugins();
$plugins->autoload(GALETTE_PLUGINS_PATH);

$session_name = '';
//since PREFIX_DB and NAME_DB are required to properly instanciate sessions,
// we have to check here if they're assigned
if ($installer || !defined('PREFIX_DB') || !defined('NAME_DB')) {
    $session_name = 'install_' . str_replace('.', '_', GALETTE_VERSION);
} else {
    $session_name = PREFIX_DB . '_' . NAME_DB . '_' . str_replace('.', '_', GALETTE_VERSION);
}
$session_name = 'galette_' . $session_name;
$session = new \RKA\SessionMiddleware([
    'name'      => $session_name,
    'lifetime'  => GALETTE_TIMEOUT
]);
$app->add($session);
$session->start();

// Set up dependencies
require GALETTE_ROOT . '/includes/dependencies.php';

$smarty = $app->getContainer()->get('view')->getSmarty();
require_once GALETTE_ROOT . 'includes/smarty.inc.php';

/**
 * Authentication middleware
 */
$authenticate = new \Galette\Middleware\Authenticate($container);

//Maintainance middleware
if ('MAINT' === GALETTE_MODE && !$container->get('login')->isSuperAdmin()) {
    $app->add(
        new \Galette\Middleware\UpdateAndMaintenance(
            $i18n,
            \Galette\Middleware\UpdateAndMaintenance::MAINTENANCE
        )
    );
}

/**
 * Trailing slash middleware
 */
$app->add('\Galette\Middleware\TrailingSlash');

/**
 * Change language middleware
 *
 * Require determineRouteBeforeAppMiddleware to be on.
 */
$app->add('\Galette\Middleware\Language');

//Telemetry update middleware
$app->add('\Galette\Middleware\Telemetry');

/**
 * Check routes ACLs
 * This is important this one to be the last, so it'll be executed first.
 */
$app->add('\Galette\Middleware\CheckAcls');

require_once GALETTE_ROOT . 'includes/routes/main.routes.php';
require_once GALETTE_ROOT . 'includes/routes/authentication.routes.php';
require_once GALETTE_ROOT . 'includes/routes/management.routes.php';
require_once GALETTE_ROOT . 'includes/routes/members.routes.php';
require_once GALETTE_ROOT . 'includes/routes/groups.routes.php';
require_once GALETTE_ROOT . 'includes/routes/contributions.routes.php';
require_once GALETTE_ROOT . 'includes/routes/public_pages.routes.php';
require_once GALETTE_ROOT . 'includes/routes/ajax.routes.php';
require_once GALETTE_ROOT . 'includes/routes/plugins.routes.php';

$app->run();

if (isset($profiler)) {
    $profiler->stop();
}
