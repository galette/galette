<?php

/**
 * PostgreSQL configuration file for tests
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

define("TYPE_DB", "pgsql");

if (file_exists(__DIR__ . '/local_config.inc.php')) {
    include_once __DIR__ . '/local_config.inc.php';
}
if (!defined('HOST_DB')) {
    define("HOST_DB", "localhost");
}
if (!defined('PORT_DB')) {
    define("PORT_DB", "5432");
}
if (!defined('USER_DB')) {
    define("USER_DB", "postgres");
}
if (!defined('PWD_DB')) {
    define("PWD_DB", "");
}
if (!defined('NAME_DB')) {
    define("NAME_DB", "galette_tests");
}
if (!defined('PREFIX_DB')) {
    define("PREFIX_DB", "galette_");
}
