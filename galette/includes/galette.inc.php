<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main Galette initialisation
 *
 * PHP version 5
 *
 * Copyright © 2009-2012 The Galette Team
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
 * @copyright 2007-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7-dev - 2007-10-07
 */

$time_start = microtime(true);

//we'll only include relevant parts if we work from installer
if ( !isset($installer) ) {
    $installer = false;
}
// test if galette is already installed or if we're form installer
// and redirect to install page if not
$installed = file_exists(dirname(__FILE__) . '/../config/config.inc.php');
if ( !$installed && !$installer ) {
    header('location: install/index.php');
}

/**
* Import configuration settings
*/
if ( !isset($base_path) ) {
    $base_path = './';
}

if ( !$installer || $installed ) { //If we're not working from installer
    require_once $base_path . 'config/config.inc.php';
}
require_once $base_path . 'config/versions.inc.php';
require_once $base_path . 'config/paths.inc.php';

//start profiling
if (defined('GALETTE_XHPROF_PATH')
    && function_exists('xhprof_enable')
) {
    include_once __DIR__ . '/../lib/Galette/Common/XHProf.php';
    $profiler = new Galette\Common\XHProf();
    $profiler->start();
}

use Galette\Common\ClassLoader;
use Galette\Common\KLogger;
use Galette\Core;
require_once $base_path . 'lib/Galette/Common/ClassLoader.php';
$galetteLoader = new ClassLoader('Galette', WEB_ROOT . 'lib');
$zendLoader = new ClassLoader('Zend', GALETTE_ZEND_PATH);
$zendLoader->setNamespaceSeparator('_');
$smartyLoader = new ClassLoader(null, GALETTE_SMARTY_PATH);
$smartyLoader->setFileExtension('.class.php');
//register loaders
$galetteLoader->register();
$zendLoader->register();
$smartyLoader->register();

//we start a php session
session_start();

define('GALETTE_VERSION', 'v0.7.2');
define('GALETTE_COMPAT_VERSION', '0.7.1');
define('GALETTE_DB_VERSION', '0.701');
define('GALETTE_MODE', 'PROD'); //DEV or PROD
define('GALETTE_TWITTER', 'galette_soft');
define('GALETTE_GPLUS', '116977415489200387309');
define('GALETTE_GAPI_KEY', 'AIzaSyDT8Xkud_SdSHdvaagjePrpPoji2ySIZ7Q');

if ( !isset($_COOKIE['show_galette_dashboard']) ) {
    setcookie(
        'show_galette_dashboard',
        true,
        time()+31536000 //valid for a year
    );
}

@ini_set('display_errors', 0);

set_include_path(
    GALETTE_ZEND_PATH . PATH_SEPARATOR .
    GALETTE_PHP_MAILER_PATH . PATH_SEPARATOR .
    GALETTE_SMARTY_PATH . PATH_SEPARATOR .
    get_include_path()
);

/*------------------------------------------------------------------------------
Error severity, from low to high. From BSD syslog RFC, secion 4.1.1
@link http://www.faqs.org/rfcs/rfc3164.html

KLogger::EMERG  => System is unusable
KLogger::ALERT  => Immediate action required
KLogger::CRIT   => Critical conditions
KLogger::ERR    => Error conditions
KLogger::WARN   => Warning conditions
KLogger::NOTICE => Normal but significant
KLogger::INFO   => Informational messages
KLogger::DEBUG  => Debug-level messages
------------------------------------------------------------------------------*/
if ( !isset($logfile) ) {
    $logfile = 'galette_run';
}
$log = new KLogger(GALETTE_LOGS_PATH, KLogger::INFO, $logfile);

//set custom error handler
set_error_handler(
    array(
        "Galette\Core\Error",
        "errorHandler"
    )
);

// check required PHP version...
if ( version_compare(PHP_VERSION, '5.3.0', '<') ) {
    $log->log(
        'Galette is NOT compliant with your current PHP version. ' .
        'Galette requires PHP 5.3 minimum, current version is ' . phpversion(),
        KLogger::EMERG
    );
    die();
}

require_once WEB_ROOT . 'includes/functions.inc.php';

$session_name = null;
//since PREFIX_DB and NAME_DB are required to properly instanciate sessions,
// we have to check here if they're assigned
if ( $installer || !defined('PREFIX_DB') || !defined('NAME_DB') ) {
    $session_name = 'galette_galette';
    $session = &$_SESSION['galette']['galette_install'];
} else {
    $session_name = PREFIX_DB . '_' . NAME_DB;
    $session = &$_SESSION['galette'][PREFIX_DB . '_' . NAME_DB];
}

/**
* Language instantiation
*/
if ( isset($session['lang'])
    && GALETTE_MODE !== 'DEV'
) {
    $i18n = unserialize($session['lang']);
} else {
    $i18n = new Core\I18n();
}

if ( isset($_POST['pref_lang'])
    && strpos($_SERVER['PHP_SELF'], 'champs_requis.php') === false
) {
    $_GET['pref_lang'] = $_POST['pref_lang'];
}
if ( isset($_GET['pref_lang']) ) {
    $i18n->changeLanguage($_GET['pref_lang']);
}
$session['lang'] = serialize($i18n);
require_once WEB_ROOT . 'includes/i18n.inc.php';

// initialize messages arrays
$error_detected = array();
$warning_detected = array();
$success_detected = array();

if ( !$installer ) { //If we're not working from installer
    require_once WEB_ROOT . 'config/config.inc.php';

    /**
    * Database instanciation
    */
    $zdb = new Core\Db();

    if ( $zdb->checkDbVersion() || strpos($_SERVER['PHP_SELF'], 'picture.php') !== false  ) {

        /**
        * Load preferences
        */
        $preferences = new Core\Preferences();

        /**
        * Set the path to the current theme templates
        */
        define(
            '_CURRENT_TEMPLATE_PATH',
            GALETTE_TEMPLATES_PATH . $preferences->pref_theme . '/'
        );

        /**
        * Plugins
        */
        $plugins = new Core\Plugins();
        $plugins->loadModules(GALETTE_PLUGINS_PATH, $i18n->getFileName());

        /**
        * Authentication
        */
        if ( isset($session['login']) ) {
            $login = unserialize(
                $session['login']
            );
        } else {
            $login = new Core\Login();
        }

        /**
        * Instanciate history object
        */
        if ( isset($session['history'])
            && !GALETTE_MODE == 'DEV'
        ) {
            $hist = unserialize(
                $session['history']
            );
        } else {
            $hist = new Core\History();
        }

        /**
        * Logo
        */
        if ( isset($session['logo'])
            && !GALETTE_MODE == 'DEV'
        ) {
            $logo = unserialize(
                $session['logo']
            );
        } else {
            $logo = new Core\Logo();
        }

        /**
        * Now that all objects are correctly setted,
        * we can include files that need it
        */
        require_once WEB_ROOT . 'includes/session.inc.php';
        require_once WEB_ROOT . 'includes/smarty.inc.php';
    } else {
        header('location: needs_update.php');
        die();
    }
}
?>
