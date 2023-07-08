<?php

/**
 * Test bootstrap
 *
 * PHP version 5
 *
 * @category  Tests
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.3dev 2012-12-12
 */

$basepath = null;
if (file_exists('../galette/index.php')) {
    $basepath = '../galette/';
} elseif (file_exists('galette/index.php')) {
    $basepath = 'galette/';
} else {
    die('Unable to define GALETTE_BASE_PATH :\'(');
}

$db = 'mysql';
$dbenv = getenv('DB');
if (
    $dbenv === 'pgsql'
    || substr($dbenv, 0, strlen('postgres')) === 'postgres'
) {
    $db = 'pgsql';
}

define('GALETTE_CONFIG_PATH', __DIR__ . '/config/' . $db . '/');
define('GALETTE_BASE_PATH', $basepath);
define('GALETTE_TESTS', true);
define('GALETTE_TESTS_PATH', __DIR__);
define('GALETTE_MODE', 'PROD');
define('GALETTE_PLUGINS_PATH', GALETTE_TESTS_PATH . '/plugins/');
define('GALETTE_TPL_SUBDIR', 'templates/default/');
define('GALETTE_THEME', 'themes/default/');
define('GALETTE_DATA_PATH', GALETTE_TESTS_PATH . '/tests-data/');
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

require_once GALETTE_BASE_PATH . '/includes/dependencies.php';
//Globals... :(
global $preferences, $emitter, $zdb;
$zdb = $container->get('zdb');
$preferences = $container->get('preferences');
$emitter = $container->get('event_manager');
$i18n->changeLanguage('en_US');

if (!defined('_CURRENT_THEME_PATH')) {
    define(
        '_CURRENT_THEME_PATH',
        GALETTE_THEMES_PATH . $preferences->pref_theme . '/'
    );
}

$updateenv = getenv('UPDATE');
if (
    $updateenv !== 'UPDATE'
) {
    //do not initialize Ttiles on update tests
    $titles = new \Galette\Repository\Titles($zdb);
    $res = $titles->installInit($zdb);
}

require_once __DIR__ . '/GaletteTestCase.php';
