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
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.3dev 2012-12-12
 */

$basepath = null;
if ( file_exists('../galette/index.php') ) {
    $basepath = '../galette/';
} elseif ( file_exists('galette/index.php') ) {
    $basepath = 'galette/';
} else {
    die('Unable to define GALETTE_BASE_PATH :\'(');
}

define('GALETTE_BASE_PATH', $basepath);
define('GALETTE_TESTS', true);
define('GALETTE_PLUGINS_PATH', __DIR__ . '/plugins/');
define('GALETTE_TPL_SUBDIR', 'templates/default/');
$logfile = 'galette_tests';

require_once GALETTE_BASE_PATH . 'includes/galette.inc.php';
