<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition( // @phpstan-ignore argument.missing (missing information is what is tested)
    name: 'Galette Invalid Plugin',
    desc: 'Test invalid plugin',
    author: 'Johan Cwiklinski'
);
