<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette Test1 Plugin',
    desc: 'Test one plugin',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'plugin1',
    date: '2016-10-19',
    acls: [
        'plugin1_root'  => 'groupmanager'
    ],
    repository: 'galette/plugin-test-one',
    csrf_exclusions: ['/plugin1_webhook/']
);
