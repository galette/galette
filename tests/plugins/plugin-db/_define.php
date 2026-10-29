<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette Db Plugin',
    desc: 'Test db plugin',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'plugdb',
    date: '2015-01-30',
    acls: [
        'plugdb_root'   => 'member',
        'plugdb_admin'  => 'staff'
    ],
    dbver: 0.1
);
