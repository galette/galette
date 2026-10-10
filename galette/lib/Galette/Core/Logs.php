<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Throwable;
use WeakMap;

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

    /**
     * Exceptions already written to the log, to avoid repeating traces
     * when an exception is logged again while bubbling up
     *
     * @var ?WeakMap<Throwable, true>
     */
    private static ?WeakMap $logged = null;

    /**
     * Log an exception with everything needed to understand it without debug mode:
     * class, location, previous exceptions chain, request and stack trace.
     *
     * When the exception (or one of its previous) has already been logged,
     * only a reminder line is written.
     *
     * Below WARNING (expected cases: bad user input, not found...), a single line
     * is written, and the exception is not marked as logged: it will be detailed
     * if it ends up as a real error.
     *
     * @param Throwable $e       Exception to log
     * @param string    $message Context message (what was being done)
     * @param int       $level   Analog log level
     */
    public static function exception(Throwable $e, string $message = '', int $level = Analog::ERROR): void
    {
        self::$logged ??= new WeakMap();

        $headline = ($message !== '' ? $message . ' | ' : '') . $e->getMessage();

        if ($level > Analog::WARNING) {
            $request = self::requestLine();
            Analog::log(
                $headline . ' (' . $e::class . ')' . ($request !== null ? ' | Request: ' . $request : ''),
                $level
            );
            return;
        }

        $already_logged = false;
        $chain = [];
        $current = $e;
        do {
            $chain[] = $current;
            if (isset(self::$logged[$current])) {
                $already_logged = true;
            }
        } while ($current = $current->getPrevious());

        foreach ($chain as $current) {
            self::$logged[$current] = true;
        }

        if ($already_logged) {
            Analog::log(
                $headline . ' (' . $e::class . ', details already logged)',
                $level
            );
            return;
        }

        $lines = [$headline];
        foreach ($chain as $i => $current) {
            $lines[] = ($i === 0 ? 'Exception: ' : 'Caused by: ')
                . $current::class
                . ($i === 0 ? '' : ': ' . $current->getMessage())
                . ' at ' . $current->getFile() . ':' . $current->getLine();
        }

        $request = self::requestLine();
        if ($request !== null) {
            $lines[] = 'Request: ' . $request;
        }

        $root = $chain[array_key_last($chain)];
        $lines[] = 'Stack trace' . (count($chain) > 1 ? ' (' . $root::class . ')' : '') . ':';
        $lines[] = self::trace($root);

        Analog::log(implode("\n", $lines), $level);
    }

    /**
     * Current HTTP request, without query string
     */
    private static function requestLine(): ?string
    {
        if (PHP_SAPI === 'cli' || !isset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])) {
            return null;
        }

        return $_SERVER['REQUEST_METHOD'] . ' ' . explode('?', (string)$_SERVER['REQUEST_URI'])[0];
    }

    /**
     * Stack trace without call arguments, they may contain passwords or personal data
     *
     * @param Throwable $e Exception
     */
    private static function trace(Throwable $e): string
    {
        $lines = [];
        foreach ($e->getTrace() as $i => $frame) {
            $lines[] = sprintf(
                '#%d %s%s%s()',
                $i,
                isset($frame['file']) ? $frame['file'] . '(' . ($frame['line'] ?? '?') . '): ' : '[internal function]: ',
                isset($frame['class']) ? $frame['class'] . ($frame['type'] ?? '::') : '',
                $frame['function']
            );
        }
        $lines[] = '#' . count($lines) . ' {main}';

        return implode("\n", $lines);
    }
}
