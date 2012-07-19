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

if ( !$installer ) { //If we're not working from installer
    require_once $base_path . 'config/config.inc.php';
}
require_once $base_path . 'config/versions.inc.php';
require_once $base_path . 'config/paths.inc.php';

//we start a php session
session_start();

define('GALETTE_VERSION', 'v0.7.0.2');
define('GALETTE_DB_VERSION', '0.700');
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
    GALETTE_PEAR_PATH . PATH_SEPARATOR .
    GALETTE_PEAR_LOG_PATH . PATH_SEPARATOR .
    GALETTE_PHP_MAILER_PATH . PATH_SEPARATOR .
    GALETTE_SMARTY_PATH . PATH_SEPARATOR .
    get_include_path()
);

/*------------------------------------------------------------------------------
LOG and DEBUG
_file_log and _screen_log should take PEAR::LOG verbosity modes :
PEAR_LOG_EMERG    	=>	System is unusable
PEAR_LOG_ALERT    	=>	Immediate action required
PEAR_LOG_CRIT    	=>	Critical conditions
PEAR_LOG_ERR    	=>	Error conditions
PEAR_LOG_WARNING    =>	Warning conditions
PEAR_LOG_NOTICE    	=>	Normal but significant
PEAR_LOG_INFO    	=>	Informational
PEAR_LOG_DEBUG    	=>	Debug-level messages

------------------------------------------------------------------------------*/
require_once 'Log.php';
/** FIXME: for stables versions, log level must not be INFO,
most probably WARNING or NOTICE */
// ***** LOG : enregistrement des erreur dans un fichier de log
define('_FILE_LOG', (GALETTE_MODE === 'DEV')?PEAR_LOG_DEBUG : PEAR_LOG_INFO);
// ***** LOG : fichier de log
define('_LOG_FILE', GALETTE_LOGS_PATH . '/galette.log');
// ***** LOG : affichage des erreurs à l'écran
define('_SCREEN_LOG', PEAR_LOG_EMERG);

$conf = array(
    'error_prepend' => '<div id="error" class="error">',
    'error_append'  => '</div>'
);
$display = Log::singleton('display', '', 'galette', $conf, _SCREEN_LOG);
$file = Log::singleton('file', _LOG_FILE, 'galette', '', _FILE_LOG);

$log = Log::singleton('composite');
$log->addChild($display);
$log->addChild($file);

// check required PHP version...
if ( version_compare(PHP_VERSION, '5.3.0', '<') ) {
    $log->log(
        'Galette is NOT compliant with your current PHP version. ' .
        'Galette requires PHP 5.3 minimum, current version is ' . phpversion(),
        PEAR_LOG_EMERG
    );
    die();
}

require_once WEB_ROOT . 'includes/functions.inc.php';

$session_name = null;
//since PREFIX_DB and NAME_DB are required to properly instanciate sessions,
// we have to check here if they're assigned
if ( $installer || !defined('PREFIX_DB') || !defined('NAME_DB') ) {
    $session_name = 'galette_galette';
} else {
    $session_name = PREFIX_DB . '_' . NAME_DB;
}

/**
* Language instantiation
*/
require_once WEB_ROOT . 'classes/i18n.class.php';

if ( isset($_SESSION['galette'][$session_name]['lang']) ) {
    $i18n = unserialize($_SESSION['galette'][$session_name]['lang']);
} else {
    $i18n = new I18n();
}

if ( isset($_POST['pref_lang'])
    && strpos($_SERVER['PHP_SELF'], 'champs_requis.php') === false
) {
    $_GET['pref_lang'] = $_POST['pref_lang'];
}
if ( isset($_GET['pref_lang']) ) {
    $i18n->changeLanguage($_GET['pref_lang']);
}
$_SESSION['galette'][$session_name]['lang'] = serialize($i18n);
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
    require_once WEB_ROOT . '/classes/galette-zend_db.class.php';
    $zdb = new GaletteZendDb();

    if ( $zdb->checkDbVersion() ) {

        /**
        * Load preferences
        */
        require_once WEB_ROOT . 'classes/preferences.class.php';
        $preferences = new Preferences();

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
        require_once WEB_ROOT . 'classes/plugins.class.php';
        $plugins = new plugins();
        $plugins->loadModules(GALETTE_PLUGINS_PATH, $i18n->getFileName());

        /**
        * Authentication
        */
        require_once WEB_ROOT . 'classes/galette-login.class.php';
        if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['login']) ) {
            $login = unserialize(
                $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['login']
            );
        } else {
            $login = new GaletteLogin();
        }

        /**
        * Members, also load Adherent and Picture objects
        */
        require_once WEB_ROOT . 'classes/members.class.php';

        /**
        * Instanciate history object
        */
        require_once WEB_ROOT . 'classes/history.class.php';
        if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['history'])
            && !GALETTE_MODE == 'DEV'
        ) {
            $hist = unserialize(
                $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['history']
            );
        } else {
            $hist = new History();
        }

        /**
        * Logo
        */
        require_once WEB_ROOT . 'classes/logo.class.php';
        if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['logo'])
            && !GALETTE_MODE == 'DEV'
        ) {
            $logo = unserialize(
                $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['logo']
            );
        } else {
            $logo = new Logo();
        }

        /**
        * Now that all objects are correctly setted,
        * we can include files that need it
        */
        require_once WEB_ROOT . 'classes/galette_mail.class.php';
        require_once WEB_ROOT . 'includes/session.inc.php';
        require_once WEB_ROOT . 'includes/smarty.inc.php';
    } else {
        header('location: needs_update.php');
        die();
    }
}
?>
