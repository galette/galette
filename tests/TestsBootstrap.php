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
 * @version   SVN: $Id$
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

$db = getenv('DB');
if ($db === false || $db !== 'pgsql') {
    $db = 'mysql';
}

define('GALETTE_CONFIG_PATH', __DIR__ . '/config/' . $db . '/');
define('GALETTE_BASE_PATH', $basepath);
define('GALETTE_TESTS', true);
define('GALETTE_MODE', 'PROD');
define('GALETTE_PLUGINS_PATH', __DIR__ . '/plugins/');
define('GALETTE_TPL_SUBDIR', 'templates/default/');
define('GALETTE_THEME', 'themes/default/');
define('GALETTE_TEMPIMAGES_PATH', __DIR__ . '/tests-data');
if (is_dir(GALETTE_TEMPIMAGES_PATH)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            GALETTE_TEMPIMAGES_PATH,
            RecursiveDirectoryIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir(GALETTE_TEMPIMAGES_PATH);
}
mkdir(GALETTE_TEMPIMAGES_PATH);
$logfile = 'galette_tests';

require_once GALETTE_BASE_PATH . 'includes/galette.inc.php';
//require_once GALETTE_BASE_PATH . 'includes/i18n.inc.php';

//Globals... :(
global $preferences;
$zdb = new \Galette\Core\Db();
$preferences = new \Galette\Core\Preferences($zdb);

/**
 * Maps _T Galette's function
 *
 * @param string $string String to translate
 *
 * @return string
 */
function _T($string)
{
    return $string;
}

/**
 * Maps __ Galette's function
 *
 * @param string $string String to translate
 *
 * @return string
 */
function __($string)
{
    return $string;
}
