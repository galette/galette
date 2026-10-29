<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette Db Upgrade Plugin',
    desc: 'Test db upgrade plugin',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'plugdbupgrade',
    date: '2026-01-01',
    acls: [],
    dbver: 0.2
);
