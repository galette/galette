<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette Test2 Plugin',
    desc: 'Test two plugin',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'plugin2',
    date: '2013-12-15',
    acls: [
        'plugin2_root'  => 'member',
        'plugin2_admin' => 'staff'
    ]
);
