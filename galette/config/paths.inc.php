<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Various paths
 * Path to external librarires, logs files, exports directory, ...
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @category  Config
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-13
 */

if ( file_exists(GALETTE_ROOT . 'config/local_paths.inc.php') ) {
    include GALETTE_ROOT . 'config/local_paths.inc.php';
}

//external libraries
if ( !defined('GALETTE_PASSWORD_COMPAT_PATH') ) {
    define(
        'GALETTE_PASSWORD_COMPAT_PATH',
        GALETTE_ROOT . 'includes/password_compat-' . PASSWORD_COMPAT_VERSION
    );
}
if ( !defined('GALETTE_ZEND_PATH') ) {
    define('GALETTE_ZEND_PATH', GALETTE_ROOT . 'includes/Zend-' . ZEND_VERSION);
}
if ( !defined('GALETTE_ANALOG_PATH') ) {
    define(
        'GALETTE_ANALOG_PATH',
        GALETTE_ROOT . 'includes/Analog-' . ANALOG_VERSION
    );
}
if ( !defined('GALETTE_PHP_MAILER_PATH') ) {
    define(
        'GALETTE_PHP_MAILER_PATH',
        GALETTE_ROOT . 'includes/phpMailer-' . PHP_MAILER_VERSION
    );
}
if ( !defined('GALETTE_SMARTY_PATH') ) {
    define(
        'GALETTE_SMARTY_PATH',
        GALETTE_ROOT . 'includes/Smarty-' . SMARTY_VERSION
    );
}
if ( !defined('GALETTE_GAPI_PATH') ) {
    define(
        'GALETTE_GAPI_PATH',
        GALETTE_ROOT . 'includes/google-api-' . GAPI_VERSION
    );
}
if ( !defined('GALETTE_TCPDF_PATH') ) {
    define(
        'GALETTE_TCPDF_PATH',
        GALETTE_ROOT . 'includes/tcpdf_' . TCPDF_VERSION
    );
}
/*if ( !defined('GALETTE_XHPROF_PATH') ) {
    define('GALETTE_XHPROF_PATH', '/usr/share/xhprof/');
}*/

//galete's paths
if ( !defined('GALETTE_CONFIG_PATH') ) {
    define('GALETTE_CONFIG_PATH', GALETTE_ROOT . 'config/');
}
if ( !defined('GALETTE_TEMPLATES_PATH') ) {
    define('GALETTE_TEMPLATES_PATH', GALETTE_ROOT . 'templates/');
}
if ( !defined('GALETTE_LOGS_PATH') ) {
    define('GALETTE_LOGS_PATH', GALETTE_ROOT . 'logs/');
}
if ( !defined('GALETTE_COMPILE_DIR') ) {
    define('GALETTE_COMPILE_DIR', GALETTE_ROOT . 'templates_c/');
}
if ( !defined('GALETTE_CACHE_DIR') ) {
    define('GALETTE_CACHE_DIR', GALETTE_ROOT . 'cache/');
}
if ( !defined('GALETTE_PLUGINS_PATH') ) {
    define('GALETTE_PLUGINS_PATH', GALETTE_ROOT . 'plugins/');
}
if ( !defined('GALETTE_EXPORTS_PATH') ) {
    define('GALETTE_EXPORTS_PATH', GALETTE_ROOT . 'exports/');
}
if ( !defined('GALETTE_IMPORTS_PATH') ) {
    define('GALETTE_IMPORTS_PATH', GALETTE_ROOT . 'imports/');
}
if ( !defined('GALETTE_PHOTOS_PATH') ) {
    define('GALETTE_PHOTOS_PATH', GALETTE_ROOT . 'photos/');
}
if ( !defined('GALETTE_ATTACHMENTS_PATH') ) {
    define('GALETTE_ATTACHMENTS_PATH', GALETTE_ROOT . 'attachments/');
}
if ( !defined('GALETTE_TEMPIMAGES_PATH') ) {
    define('GALETTE_TEMPIMAGES_PATH', GALETTE_ROOT . 'tempimages/');
}
if ( !defined('GALETTE_DATA_PATH') ) {
    define('GALETTE_DATA_PATH', GALETTE_ROOT . 'data/');
}
if ( !defined('GALETTE_SQLITE_PATH') ) {
    define('GALETTE_SQLITE_PATH', GALETTE_DATA_PATH . 'database.sqlite');
}
