<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

new PluginDefinition( // @phpstan-ignore new.resultUnused (missing return is what is tested)
    name: 'Galette No Return Plugin',
    desc: 'Test plugin which definition is not returned',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'noreturn'
);
