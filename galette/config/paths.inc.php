<?php
/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

/**
 * Various paths
 * Path to external librarires, logs files, exports directory, ...
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

if (file_exists(GALETTE_ROOT . 'config/local_paths.inc.php')) {
    include GALETTE_ROOT . 'config/local_paths.inc.php';
}

//external libraries
if (!defined('GALETTE_ZEND_PATH')) {
    define('GALETTE_ZEND_PATH', GALETTE_ROOT . 'vendor/zendframework');
}
if (!defined('GALETTE_ANALOG_PATH')) {
    define(
        'GALETTE_ANALOG_PATH',
        GALETTE_ROOT . 'vendor/analog/analog'
    );
}
if (!defined('GALETTE_PHP_MAILER_PATH')) {
    define(
        'GALETTE_PHP_MAILER_PATH',
        GALETTE_ROOT . 'vendor/phpmailer/phpmailer'
    );
}
if (!defined('GALETTE_SMARTY_PATH')) {
    define(
        'GALETTE_SMARTY_PATH',
        GALETTE_ROOT . 'vendor/smarty/smarty/'
    );
}
if (!defined('GALETTE_TCPDF_PATH')) {
    define(
        'GALETTE_TCPDF_PATH',
        GALETTE_ROOT . '/vendor/tecnickcom/tcpdf'
    );
}
/*if ( !defined('GALETTE_SLIM_PATH') ) {
    define('GALETTE_SLIM_PATH', GALETTE_ROOT . 'lib/Slim-' . SLIM_VERSION);
}
if ( !defined('GALETTE_SLIM_VIEWS_PATH') ) {
    define('GALETTE_SLIM_VIEWS_PATH', GALETTE_ROOT . 'lib/Slim-Views');
}*/
if (!defined('GALETTE_XHPROF_PATH')) {
    define('GALETTE_XHPROF_PATH', '/usr/share/xhprof/');
}

//galete's paths
if (!defined('GALETTE_CONFIG_PATH')) {
    define('GALETTE_CONFIG_PATH', GALETTE_ROOT . 'config/');
}
if (!defined('GALETTE_PLUGINS_PATH')) {
    define('GALETTE_PLUGINS_PATH', GALETTE_ROOT . 'plugins/');
}
if (!defined('GALETTE_DATA_PATH')) {
    define('GALETTE_DATA_PATH', GALETTE_ROOT . 'data/');
}
if (!defined('GALETTE_THEMES_PATH')) {
    define('GALETTE_THEMES_PATH', GALETTE_ROOT . 'webroot/themes/');
}
if (!defined('GALETTE_LOGS_PATH')) {
    define('GALETTE_LOGS_PATH', GALETTE_DATA_PATH . 'logs/');
}
if (!defined('GALETTE_COMPILE_DIR')) {
    define('GALETTE_COMPILE_DIR', GALETTE_DATA_PATH . 'templates_c/');
}
if (!defined('GALETTE_CACHE_DIR')) {
    define('GALETTE_CACHE_DIR', GALETTE_DATA_PATH . 'cache/' . GALETTE_VERSION . '/');
}
if (!defined('GALETTE_EXPORTS_PATH')) {
    define('GALETTE_EXPORTS_PATH', GALETTE_DATA_PATH . 'exports/');
}
if (!defined('GALETTE_IMPORTS_PATH')) {
    define('GALETTE_IMPORTS_PATH', GALETTE_DATA_PATH . 'imports/');
}
if (!defined('GALETTE_PHOTOS_PATH')) {
    define('GALETTE_PHOTOS_PATH', GALETTE_DATA_PATH . 'photos/');
}
if (!defined('GALETTE_DOCUMENTS_PATH')) {
    define('GALETTE_DOCUMENTS_PATH', GALETTE_DATA_PATH . 'documents/');
}
if (!defined('GALETTE_ATTACHMENTS_PATH')) {
    define('GALETTE_ATTACHMENTS_PATH', GALETTE_DATA_PATH . 'attachments/');
}
if (!defined('GALETTE_FILES_PATH')) {
    define('GALETTE_FILES_PATH', GALETTE_DATA_PATH . 'files/');
}
if (!defined('GALETTE_TEMPIMAGES_PATH')) {
    define('GALETTE_TEMPIMAGES_PATH', GALETTE_DATA_PATH . 'tempimages/');
}
if (!defined('GALETTE_TELEMETRY_URI')) {
    define('GALETTE_TELEMETRY_URI', 'https://telemetry.galette.eu/');
}

if (!defined('GALETTE_TPL_THEME_DIR')) {
    define('GALETTE_TPL_THEME_DIR', GALETTE_ROOT . 'templates/default/');
}

if (!defined('GALETTE_DOWNLOADS_URI')) {
    define('GALETTE_DOWNLOADS_URI', 'https://download.tuxfamily.org/galette/');
}
