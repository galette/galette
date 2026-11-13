<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette Integrated Plugin',
    desc: 'Test plugin now part of the core',
    author: 'Johan Cwiklinski',
    version: '9.0',
    compver: '9.0.0',
    route: 'maps', //listed as integrated
);
