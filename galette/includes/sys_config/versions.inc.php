<?php

/**
 * Copyright © 2003-2026 The Galette Team
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
 * Components versions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
 */

define('GALETTE_PHP_MIN', '8.2'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_MYSQL_MIN', '8.0'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_MARIADB_MIN', '10.5'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_PGSQL_MIN', '13'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_NIGHTLY', false); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_VERSION', 'v1.2.1'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_COMPAT_VERSION', '1.2.0'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_DB_VERSION', '1.220'); //@phpstan-ignore theCodingMachineSafe.function
