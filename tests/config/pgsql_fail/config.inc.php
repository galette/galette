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
 * PostgreSQL's configuration file for tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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
    define("USER_DB", "galette_tests");
}
if (!defined('PWD_DB')) {
    define("PWD_DB", "g@l3tte");
}
if (!defined('NAME_DB')) {
    define("NAME_DB", "galette_fail_tests");
}
if (!defined('PREFIX_DB')) {
    define("PREFIX_DB", "galette_");
}
