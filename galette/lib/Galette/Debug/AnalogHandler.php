<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug;

use Analog\Analog;
use DebugBar\DataCollector\MessagesCollector;

/**
 * Analog handler copying log entries to the debug bar
 *
 * Entries still reach the regular handler, unchanged.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AnalogHandler
{
    private const array LEVELS = [
        Analog::URGENT => 'emergency',
        Analog::ALERT => 'alert',
        Analog::CRITICAL => 'critical',
        Analog::ERROR => 'error',
        Analog::WARNING => 'warning',
        Analog::NOTICE => 'notice',
        Analog::INFO => 'info',
        Analog::DEBUG => 'debug',
    ];

    /**
     * @param object|callable   $next      Regular handler
     * @param MessagesCollector $collector Debug bar messages collector
     */
    public function __construct(
        private readonly mixed $next,
        private readonly MessagesCollector $collector
    ) {
    }

    /**
     * Log an entry
     *
     * @param array{machine: string, date: string, level: int, message: string} $info Log entry
     */
    public function log(array $info): mixed
    {
        $this->collector->addMessage(
            (string)$info['message'],
            self::LEVELS[$info['level']] ?? 'info'
        );

        if (is_object($this->next) && method_exists($this->next, 'log')) {
            return $this->next->log($info);
        }
        return is_callable($this->next) ? ($this->next)($info) : null;
    }
}
