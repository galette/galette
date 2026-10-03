<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use function Safe\filemtime;
use function Safe\glob;
use function Safe\realpath;
use function Safe\strtotime;
use function Safe\unlink;

/**
 * Logs
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Logs
{
    /**
     * Clean old logs (older than one month per default)
     */
    public static function cleanup(): void
    {
        $interval = strtotime('-1 month');
        $match = glob(
            realpath(GALETTE_LOGS_PATH) . '/*.log',
            GLOB_BRACE
        );

        foreach ($match as $logfile) {
            if (filemtime($logfile) <= $interval) {
                unlink($logfile);
            }
        }
    }
}
