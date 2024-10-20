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

declare(strict_types=1);

/**
 * External libraries versions
 * Defines various library versions, to avoid use of problematic symlinks under windows or via FTP.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

define('GALETTE_PHP_MIN', '8.1');
define('GALETTE_MYSQL_MIN', '5.7');
define('GALETTE_MARIADB_MIN', '10.4');
define('GALETTE_PGSQL_MIN', '11');
define('GALETTE_NIGHTLY', false);
define('GALETTE_VERSION', 'v1.1.4');
define('GALETTE_COMPAT_VERSION', '1.1.0');
define('GALETTE_DB_VERSION', '1.100');
