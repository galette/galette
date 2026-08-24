<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Keep in sync with the dynamicConstantNames config option in the PHPStan config file
// Wrap in a function to be sure to never declare any variable in the global scope.
(static function () {
    $random_val = static fn(array $values) => $values[array_rand($values)];

    define('GALETTE_VERSION', $random_val(['0.7', '0.9.1', '1.0.0', '1.2.0']));

    // Directories constants
    define('GALETTE_BASE_PATH', dirname(__FILE__, 2) . '/galette');
    define('GALETTE_ROOT', $random_val(['./', './galette']));
    define('GALETTE_TESTS_PATH', dirname(__FILE__, 2) . '/tests');

    define('GALETTE_CONFIG_PATH', dirname(__FILE__, 2) . '/galette/config');
    define('GALETTE_PLUGINS_PATH', dirname(__FILE__, 2) . '/galette/plugins');
    define('GALETTE_DATA_PATH', dirname(__FILE__, 2) . '/galette/data');
    define('GALETTE_THEMES_PATH', dirname(__FILE__, 2) . '/galette/webroot/themes/');
    define('GALETTE_LOGS_PATH', dirname(__FILE__, 2) . '/galette/data/logs/');
    define('GALETTE_CACHE_DIR', dirname(__FILE__, 2) . '/galette/data/cache/' . GALETTE_VERSION . '/');
    define('GALETTE_EXPORTS_PATH', dirname(__FILE__, 2) . '/galette/data/exports/');
    define('GALETTE_IMPORTS_PATH', dirname(__FILE__, 2) . '/galette/data/imports/');
    define('GALETTE_PHOTOS_PATH', dirname(__FILE__, 2) . '/galette/data/photos/');
    define('GALETTE_DOCUMENTS_PATH', dirname(__FILE__, 2) . '/galette/data/documents/');
    define('GALETTE_ATTACHMENTS_PATH', dirname(__FILE__, 2) . '/galette/data/attachments/');
    define('GALETTE_FILES_PATH', dirname(__FILE__, 2) . '/galette/data/files/');
    define('GALETTE_TEMPIMAGES_PATH', dirname(__FILE__, 2) . '/galette/data/tempimages/');
    define('GALETTE_TELEMETRY_URI', 'https://telemetry.galette.eu/');
    define('GALETTE_TPL_THEME_DIR', dirname(__FILE__, 2) . '/galette/templates/default/');
    define('GALETTE_DOWNLOADS_URI', 'https://galette.eu/download/');
    define('_CURRENT_THEME_PATH', GALETTE_THEMES_PATH . '/default/');

    // Optional constants
    if ($random_val([false, true]) === true) {
        define('GALETTE_CRON', $random_val([false, true]));
        define('GALETTE_INSTALLER', $random_val([false, true]));
        define('GALETTE_LOGGER_CHECKED', $random_val([false, true]));
        define('GALETTE_TESTS', $random_val([false, true]));
        define('GALETTE_FEATURE_FLAGS', [$random_val(['acls', 'oauth2']), $random_val(['api-v2', 'new-dashboard'])]);
    }

    // Other constants
    define('GALETTE_MODE', $random_val([\Galette\Core\Galette::MODE_PROD, \Galette\Core\Galette::MODE_DEV, \Galette\Core\Galette::MODE_MAINT, \Galette\Core\Galette::MODE_DEMO]));
    define('GALETTE_DEBUG', $random_val([false, true]));
    define('GALETTE_LOG_LVL', $random_val([\Analog\Analog::URGENT, \Analog\Analog::ALERT, \Analog\Analog::CRITICAL, \Analog\Analog::ERROR, \Analog\Analog::WARNING, \Analog\Analog::NOTICE, \Analog\Analog::INFO, \Analog\Analog::DEBUG]));
    define('GALETTE_THEME', $random_val(['themes/default/', 'themes/alternative/']));
    define('GALETTE_TIMEOUT', $random_val([0, 5, 10]));
    define('GALETTE_NIGHTLY', $random_val([false, true]));
})();
