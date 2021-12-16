<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main Galette initialisation
 *
 * PHP version 5
 *
 * Copyright © 2009-2021 The Galette Team
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
 * @copyright 2007-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7-dev - 2007-10-07
 */

//define galette's root directory
if (!defined('GALETTE_ROOT')) {
    define('GALETTE_ROOT', __DIR__ . '/../');
}

require_once GALETTE_ROOT . 'config/versions.inc.php';
require_once GALETTE_ROOT . 'config/paths.inc.php';

// check required PHP version...
if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<')) {
    echo 'Galette is NOT compliant with your current PHP version. ' .
        'Galette requires PHP ' . GALETTE_PHP_MIN .
        ' minimum and current version is ' . phpversion();
    die(1);
}

$time_start = microtime(true);
$cron = (PHP_SAPI === 'cli');

// define relative base path templating can use
if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', './');
}

//we'll only include relevant parts if we work from installer
if (!isset($installer)) {
    $installer = false;
}
// test if galette is already installed or if we're form installer
// and redirect to install page if not
$installed = file_exists(GALETTE_CONFIG_PATH . 'config.inc.php');
if (!$installed && !$installer) {
    header('location: ./installer.php');
    die();
}

if (
    file_exists(GALETTE_CONFIG_PATH . 'behavior.inc.php')
    && !defined('GALETTE_TESTS') && !$cron
) {
    include_once GALETTE_CONFIG_PATH . 'behavior.inc.php';
}

if (isset($installer) && $installer !== true) {
    //If we're not working from installer
    include_once GALETTE_CONFIG_PATH . 'config.inc.php';
}

use Analog\Analog;
use Analog\Handler;
use Analog\Handler\LevelName;
use Galette\Core;

require GALETTE_ROOT . '/vendor/autoload.php';

//start profiling
if (
    defined('GALETTE_XHPROF_PATH')
    && function_exists('xhprof_enable')
) {
    include_once __DIR__ . '/../lib/Galette/Common/XHProf.php';
    $profiler = new Galette\Common\XHProf();
    $profiler->start();
}

define('GALETTE_NIGHTLY', false);
define('GALETTE_VERSION', 'v0.9.6.1');

//Version to display
if (!defined('GALETTE_HIDE_VERSION')) {
    define('GALETTE_DISPLAY_VERSION', \Galette\Core\Galette::gitVersion(false));
}

define('GALETTE_COMPAT_VERSION', '0.9.6');
define('GALETTE_DB_VERSION', '0.960');
if (!defined('GALETTE_MODE')) {
    define('GALETTE_MODE', \Galette\Core\Galette::MODE_PROD);
}

if (!isset($_COOKIE['show_galette_dashboard'])) {
    setcookie(
        'show_galette_dashboard',
        true,
        [
            'expires'   => time() + 31536000, //valid for a year
            'path'      => '/'
        ]
    );
}

if (!defined('GALETTE_DISPLAY_ERRORS')) {
    if (GALETTE_MODE === \Galette\Core\Galette::MODE_DEV) {
        define('GALETTE_DISPLAY_ERRORS', 1);
    } else {
        define('GALETTE_DISPLAY_ERRORS', 0);
    }
}
ini_set('display_errors', (defined('GALETTE_TESTS') ? '1' : '0'));

/*------------------------------------------------------------------------------
Logger stuff
------------------------------------------------------------------------------*/

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
});

//change default format so the 3rd param is a string for level name
Analog::$format = "%s - %s - %s - %s\n";
$galette_run_log = null;

if (!defined('GALETTE_LOG_LVL')) {
    if (GALETTE_MODE === 'DEV') {
        define('GALETTE_LOG_LVL', Analog::DEBUG);
    } elseif (defined('GALETTE_TESTS')) {
        define('GALETTE_LOG_LVL', Analog::NOTICE);
    } else {
        define('GALETTE_LOG_LVL', Analog::WARNING);
    }
}

if (defined('GALETTE_TESTS')) {
    $log_path = GALETTE_LOGS_PATH . 'tests.log';
    $galette_run_log = LevelName::init(Handler\File::init($log_path));
} else {
    $galette_log_var = null;

    if (!$installer || ($installer && defined('GALETTE_LOGGER_CHECKED'))) {
        //logs everything in galette log file
        if (!isset($logfile)) {
            //if no filename has been setted (ie. from install), set default one
            $logfile = 'galette';
        }
        $log_path = GALETTE_LOGS_PATH . $logfile . '.log';
        $galette_run_log = LevelName::init(Handler\File::init($log_path));
    } else {
        $galette_run_log = LevelName::init(Handler\Variable::init($galette_log_var));
    }
    if (!$installer) {
        Core\Logs::cleanup();
    }
}

Analog::handler($galette_run_log);

require_once GALETTE_ROOT . 'includes/functions.inc.php';

if (!$installer and !defined('GALETTE_TESTS')) {
    //If we're not working from installer nor from tests
    include_once GALETTE_CONFIG_PATH . 'config.inc.php';

    /**
     * Database instantiation
     */
    $zdb = new Core\Db();

    if ($zdb->checkDbVersion()) {

        /**
         * Load preferences
         */
        $preferences = new Core\Preferences($zdb);

        /**
         * Set the path to the current theme templates
         */
        define(
            '_CURRENT_THEME_PATH',
            GALETTE_THEMES_PATH . $preferences->pref_theme . '/'
        );

        if (!defined('GALETTE_TPL_SUBDIR')) {
            define(
                'GALETTE_TPL_SUBDIR',
                'templates/' . $preferences->pref_theme . '/'
            );
        }

        if (!defined('GALETTE_THEME')) {
            define(
                'GALETTE_THEME',
                'themes/' . $preferences->pref_theme . '/'
            );
        }
    } else {
        $needs_update = true;
    }
}

$plugins = new Galette\Core\Plugins();
//make sure plugins autoload is called before session start
$plugins->autoload(GALETTE_PLUGINS_PATH);
