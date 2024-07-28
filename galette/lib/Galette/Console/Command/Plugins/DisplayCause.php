<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command\Plugins;

use Galette\Core\Plugins;

/**
 * Display cause trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait DisplayCause
{
    /**
     * Get display cause for a disabled plugin
     */
    protected function getDisplayCause(int $cause): string
    {
        return match ($cause) {
            Plugins::DISABLED_COMPAT => 'Incompatible with current version',
            Plugins::DISABLED_MISS => 'A required file is missing',
            Plugins::DISABLED_EXPLICIT => 'Explicitly disabled',
            Plugins::DISABLED_DBVERSION => 'Database version missing',
            Plugins::DISABLED_NOT_INSTALLED => 'Not installed',
            Plugins::DISABLED_NOT_UP2DATE => 'Need update',
            default => 'Unknown cause (' . $cause . ')',
        };
    }
}
