<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * External libraries versions
 * Defines various library versions, to avoid use of problematic symlinks under windows or via FTP.
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
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
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-13
 */

define('GALETTE_PHP_MIN', '8.1');
define('GALETTE_MYSQL_MIN', '5.7');
define('GALETTE_MARIADB_MIN', '10.4');
define('GALETTE_PGSQL_MIN', '11')  ;
define('GALETTE_NIGHTLY', false);
define('GALETTE_VERSION', 'v1.0.3');
define('GALETTE_COMPAT_VERSION', '1.0.0');
define('GALETTE_DB_VERSION', '0.960');
