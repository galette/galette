<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Db;

use Laminas\Db\Adapter\Platform\Postgresql;

/**
 * Postgresql platform that quotes PHP booleans
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PostgresqlPlatform extends Postgresql
{
    use BooleanQuoting;
}
