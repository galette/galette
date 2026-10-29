<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette News',
    desc: 'Plugin with news',
    author: 'Johan Cwiklinski',
    version: '1.0',
    compver: GALETTE_COMPAT_VERSION,
    route: 'news',
    date: '2025-08-16',
    csrf_exclusions: ['/news_webhook/']
);
