<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Safe\DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Reads the current time from the machine.
 *
 * Exists so that code depending on "now" can be handed a clock instead of
 * calling time() itself, which makes it testable without freezing the whole
 * process. Tests inject their own implementation.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SystemClock implements ClockInterface
{
    /**
     * Current point in time
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
