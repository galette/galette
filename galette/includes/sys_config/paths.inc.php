<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Various paths
 * Path to external libraries, logs files, exports directory, ...
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
 */

if (file_exists(GALETTE_ROOT . 'config/local_paths.inc.php')) {
    include GALETTE_ROOT . 'config/local_paths.inc.php';
}

//3rd party libs paths
if (!defined('GALETTE_TCPDF_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define(
        'GALETTE_TCPDF_PATH',
        GALETTE_ROOT . '/vendor/tecnickcom/tcpdf'
    );
}
if (!defined('GALETTE_XHPROF_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_XHPROF_PATH', '/usr/share/xhprof/');
}

//Galette paths
const GALETTE_SYSCONFIG_PATH = GALETTE_ROOT . 'includes/sys_config/';
if (!defined('GALETTE_CONFIG_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_CONFIG_PATH', GALETTE_ROOT . 'config/');
}

if (!defined('GALETTE_PLUGINS_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_PLUGINS_PATH', GALETTE_ROOT . 'plugins/');
}
if (!defined('GALETTE_DATA_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_DATA_PATH', GALETTE_ROOT . 'data/');
}
if (!defined('GALETTE_ENABLE_INSTALL_FILE')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_ENABLE_INSTALL_FILE', GALETTE_DATA_PATH . 'ENABLE_INSTALL');
}
if (!defined('GALETTE_PLUGINS_DATA_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_PLUGINS_DATA_PATH', GALETTE_DATA_PATH . 'plugins/');
}
if (!defined('GALETTE_THEMES_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_THEMES_PATH', GALETTE_ROOT . 'webroot/themes/');
}
if (!defined('GALETTE_LOGS_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_LOGS_PATH', GALETTE_DATA_PATH . 'logs/');
}
if (!defined('GALETTE_CACHE_DIR')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_CACHE_DIR', GALETTE_DATA_PATH . 'cache/' . GALETTE_VERSION . '/');
}
if (!defined('GALETTE_EXPORTS_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_EXPORTS_PATH', GALETTE_DATA_PATH . 'exports/');
}
if (!defined('GALETTE_IMPORTS_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_IMPORTS_PATH', GALETTE_DATA_PATH . 'imports/');
}
if (!defined('GALETTE_PHOTOS_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_PHOTOS_PATH', GALETTE_DATA_PATH . 'photos/');
}
if (!defined('GALETTE_DOCUMENTS_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_DOCUMENTS_PATH', GALETTE_DATA_PATH . 'documents/');
}
if (!defined('GALETTE_ATTACHMENTS_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_ATTACHMENTS_PATH', GALETTE_DATA_PATH . 'attachments/');
}
if (!defined('GALETTE_FILES_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_FILES_PATH', GALETTE_DATA_PATH . 'files/');
}
if (!defined('GALETTE_TEMPIMAGES_PATH')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_TEMPIMAGES_PATH', GALETTE_DATA_PATH . 'tempimages/');
}
if (!defined('GALETTE_TELEMETRY_URI')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_TELEMETRY_URI', 'https://telemetry.galette.eu/');
}

if (!defined('GALETTE_TPL_THEME_DIR')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_TPL_THEME_DIR', GALETTE_ROOT . 'templates/default/');
}

if (!defined('GALETTE_DOWNLOADS_URI')) {
    //@phpstan-ignore theCodingMachineSafe.function
    define('GALETTE_DOWNLOADS_URI', 'https://galette.eu/download/');
}
