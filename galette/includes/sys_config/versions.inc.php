<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Components versions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
 */

define('GALETTE_PHP_MIN', '8.3'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_MYSQL_MIN', '8.0'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_MARIADB_MIN', '10.5'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_PGSQL_MIN', '13'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_NIGHTLY', false); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_VERSION', 'v1.2.1'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_COMPAT_VERSION', '1.2.0'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_DB_VERSION', '1.220'); //@phpstan-ignore theCodingMachineSafe.function
