<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

declare(strict_types=1);

/**
 * Test bootstrap
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */


if (!isset($basepath)) {
    if (file_exists('../galette/index.php')) {
        $basepath = '../galette/';
    } elseif (file_exists('galette/index.php')) {
        $basepath = 'galette/';
    } else {
        die('Unable to define GALETTE_BASE_PATH :\'(');
    }
}

$db = 'mysql';
$dbenv = getenv('DB');
if (
    $dbenv === 'pgsql'
    || str_starts_with($dbenv, 'postgres')
) {
    $db = 'pgsql';
}

$testenv = getenv('TESTENV');
$fail_env = $testenv === 'FAIL';
if ($fail_env !== false) {
    $db .= '_fail';
}

define('GALETTE_CONFIG_PATH', __DIR__ . '/config/' . $db . '/');
define('GALETTE_BASE_PATH', $basepath);
define('GALETTE_TESTS', true);
define('GALETTE_TESTS_PATH', __DIR__);
define('GALETTE_MODE', 'PROD');
if (!defined('GALETTE_PLUGINS_PATH')) {
    define('GALETTE_PLUGINS_PATH', GALETTE_TESTS_PATH . '/plugins/');
}
define('GALETTE_TPL_SUBDIR', 'templates/default/');
define('GALETTE_THEME', 'themes/default/');
define('GALETTE_DATA_PATH', GALETTE_TESTS_PATH . '/tests-data/');
define('GALETTE_CACHE_DIR', GALETTE_DATA_PATH . 'cache/');
if (is_dir(GALETTE_DATA_PATH)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            GALETTE_DATA_PATH,
            RecursiveDirectoryIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir(GALETTE_DATA_PATH);
}

mkdir(GALETTE_DATA_PATH);
$directories = [
    'logs',
    'templates_c',
    'cache',
    'exports',
    'imports',
    'photos',
    'attachments',
    'files',
    'tempimages'
];
foreach ($directories as $directory) {
    mkdir(GALETTE_DATA_PATH . $directory);
}

$logfile = 'galette_tests';
require_once GALETTE_BASE_PATH . 'includes/galette.inc.php';

$session_name = 'galette_tests';
$session = new \RKA\SessionMiddleware([
    'name'      => $session_name,
    'lifetime'  => 0
]);
$session->start();

$gapp =  new \Galette\Core\SlimApp();
$app = $gapp->getApp();
$app->add($session);

$zdb = new \Galette\Core\Db();
$preferences = new \Galette\Core\Preferences($zdb);

if (!defined('_CURRENT_THEME_PATH')) {
    define(
        '_CURRENT_THEME_PATH',
        GALETTE_THEMES_PATH . $preferences->pref_theme . '/'
    );
}

require_once GALETTE_BASE_PATH . 'includes/main.inc.php';
//Globals... :(
global $preferences, $emitter, $zdb;
$zdb = $container->get('zdb');
$preferences = $container->get('preferences');
$emitter = $container->get('event_manager');
$i18n->changeLanguage('en_US');

if (
    $testenv !== 'UPDATE'
    && $testenv !== 'FAIL'
) {
    //do not initialize Tiles on update nor fail tests
    $titles = new \Galette\Repository\Titles($zdb);
    $res = $titles->installInit();

    $fc = $container->get(\Galette\Entity\FieldsConfig::class);
    $categorized_fields = $fc->getCategorizedFields();
    foreach ($categorized_fields as &$fieldset) {
        foreach ($fieldset as &$field) {
            if ($field['field_id'] == 'fingerprint') {
                $field['visible'] = \Galette\Entity\FieldsConfig::ALL; //make sure fingerprint field is visible
            }
        }
    }
    $fc->setFields($categorized_fields);
}
require_once __DIR__ . '/GaletteTestCase.php';
require_once __DIR__ . '/GaletteRoutingTestCase.php';
