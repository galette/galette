<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Various paths
 * Path to external librarires, logs files, exports directory, ...
 *
 * PHP version 5
 *
 * Copyright © 2011 The Galette Team
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
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-13
 */

if ( file_exists(WEB_ROOT . 'config/local_paths.inc.php') ) {
    include WEB_ROOT . 'config/local_paths.inc.php';
}

//external libraries
if ( !defined('GALETTE_PEAR_PATH') ) {
    define('GALETTE_PEAR_PATH', WEB_ROOT . 'includes/pear/PEAR-' . PEAR_VERSION);
}
if ( !defined('GALETTE_PEAR_MDB2_PATH') ) {
    define('GALETTE_PEAR_PATH', WEB_ROOT . 'includes/pear/MDB2-' . MDB2_VERSION);
}
if ( !defined('GALETTE_PEAR_LOG_PATH') ) {
    define('GALETTE_PEAR_PATH', WEB_ROOT . 'includes/pear/Log-' . LOG_VERSION);
}
if ( !defined('GALETTE_PHP_MAILER_PATH') ) {
    define('GALETTE_PHP_MAILER_PATH', WEB_ROOT . 'includes/phpMailer-' . PHP_MAILER_VERSION);
}
if ( !defined('GALETTE_SMARTY_PATH') ) {
    define('GALETTE_SMARTY_PATH', WEB_ROOT . 'includes/Smarty-' . SMARTY_VERSION);
}

//galete's paths
if ( !defined('GALETTE_TEMPLATES_PATH') ) {
    define('GALETTE_TEMPLATES_PATH', WEB_ROOT . 'templates/');
}
if ( !defined('GALETTE_LOGS_PATH') ) {
    define('GALETTE_LOGS_PATH', WEB_ROOT . 'logs/');
}
if ( !defined('GALETTE_COMPILE_DIR') ) {
    define('GALETTE_COMPILE_DIR', WEB_ROOT . 'templates_c/');
}
if ( !defined('GALETTE_CACHE_DIR') ) {
    define('GALETTE_CACHE_DIR', WEB_ROOT . 'cache/');
}
if ( !defined('GALETTE_PLUGINS_PATH') ) {
    define('GALETTE_PLUGINS_PATH', WEB_ROOT . 'plugins');
}
if ( !defined('GALETTE_EXPORTS_PATH') ) {
    define('GALETTE_EXPORTS_PATH', WEB_ROOT . 'exports/');
}
?>
