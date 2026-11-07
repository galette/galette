<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug;

use Laminas\Db\Adapter\Profiler\ProfilerInterface;
use Laminas\Db\Adapter\StatementContainerInterface;

/**
 * SQL profiler for the debug bar
 *
 * Laminas' own profiler overwrites a query that never finished with the next
 * one, so failed queries would vanish; this one keeps them, and remembers which
 * Galette code sent each query.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @phpstan-type Profile array{
 *     sql: string,
 *     start: float,
 *     end: ?float,
 *     error: ?string,
 *     source: ?array{file: string, line: int}
 * }
 */
class QueryProfiler implements ProfilerInterface
{
    /** @var list<Profile> */
    private array $profiles = [];
    private ?int $current = null;

    /**
     * Start profiling a query
     *
     * @param string|StatementContainerInterface $target Query being run
     */
    public function profilerStart($target): self // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- untyped in the interface
    {
        $this->profiles[] = [
            'sql' => $target instanceof StatementContainerInterface ? (string)$target->getSql() : (string)$target,
            'start' => microtime(as_float: true),
            'end' => null,
            'error' => null,
            'source' => $this->findSource(),
        ];
        //a query left open threw outside of Db::execute(): it stays recorded without an end
        $this->current = array_key_last($this->profiles);
        return $this;
    }

    /**
     * Stop profiling current query
     */
    public function profilerFinish(): self
    {
        if ($this->current !== null) {
            $this->profiles[$this->current]['end'] = microtime(as_float: true);
            $this->current = null;
        }
        return $this;
    }

    /**
     * Get a mark of profiles recorded so far, for profilerFailed()
     */
    public function getMark(): int
    {
        return count($this->profiles);
    }

    /**
     * Stop profiling current query, which failed
     *
     * Only a query started after the mark gets the error: an operation can
     * fail before reaching the database, and the previous query succeeded.
     *
     * @param string $message Error message
     * @param int    $mark    Mark taken before the failed operation
     */
    public function profilerFailed(string $message, int $mark): self
    {
        //without an exception from PDO, the driver already finished the query before failing
        $index = $this->current ?? array_key_last($this->profiles);
        if ($index !== null && $index >= $mark) {
            $this->profiles[$index]['error'] = $message;
        }
        return $this->profilerFinish();
    }

    /**
     * Get recorded profiles
     *
     * A profile with an error failed; a profile without an end failed too,
     * outside of Db::execute(), so its duration is unknown
     *
     * @return list<Profile>
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * Find the first caller outside of the database layer and vendor code
     *
     * @return ?array{file: string, line: int}
     */
    private function findSource(): ?array
    {
        $vendor = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
        $skipped = [__FILE__, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Db.php'];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $frame) {
            $file = $frame['file'] ?? null;
            if ($file === null || in_array($file, $skipped, strict: true)) {
                continue;
            }
            if (str_starts_with($file, $vendor)) {
                continue;
            }
            return ['file' => $file, 'line' => $frame['line'] ?? 0];
        }

        return null;
    }
}
