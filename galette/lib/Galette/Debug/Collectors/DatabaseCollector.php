<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug\Collectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\HasTimeDataCollector;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataFormatter\QueryFormatter;
use Galette\Debug\QueryProfiler;

/**
 * SQL queries collector, fed by the Laminas profiler
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DatabaseCollector extends DataCollector implements Renderable, AssetProvider
{
    use HasTimeDataCollector;

    /** Duration, in seconds, from which a query is flagged as slow */
    private const float SLOW_THRESHOLD = 0.1;

    /**
     * @param QueryProfiler $profiler Profiler attached to database adapters
     */
    public function __construct(private readonly QueryProfiler $profiler)
    {
    }

    /**
     * Collect data
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $formatter = new QueryFormatter();
        $statements = [];
        $total = 0.0;
        $failed = 0;

        foreach ($this->profiler->getProfiles() as $profile) {
            $finished = $profile['end'] !== null;
            $success = $finished && $profile['error'] === null;
            $duration = $finished ? $profile['end'] - $profile['start'] : 0.0;
            $total += $duration;
            if (!$success) {
                ++$failed;
            }
            $source = $profile['source'];

            $statements[] = [
                'sql' => $formatter->formatSql($profile['sql']),
                'params' => (object)[],
                'duration' => $duration,
                'duration_str' => $finished ? $this->getDataFormatter()->formatDuration($duration) : 'n/a',
                'is_success' => $success,
                'error_message' => $success ? null : ($profile['error'] ?? 'Query failed, see messages'),
                'filename' => $source !== null ? $formatter->formatSource((object)$source, short: true) : null,
                'xdebug_link' => $source !== null ? $this->getXdebugLink($source['file'], $source['line']) : null,
                'slow' => $duration >= self::SLOW_THRESHOLD,
            ];

            if ($this->hasTimeDataCollector()) {
                $this->addTimeMeasure($profile['sql'], $profile['start'], $profile['end'] ?? $profile['start']);
            }
        }

        return [
            'nb_statements' => count($statements),
            'nb_failed_statements' => $failed,
            'accumulated_duration' => $total,
            'accumulated_duration_str' => $this->getDataFormatter()->formatDuration($total),
            'statements' => $statements,
        ];
    }

    /**
     * Collector name
     */
    public function getName(): string
    {
        return 'queries';
    }

    /**
     * Widgets
     *
     * @return array<string, array<string, mixed>>
     */
    public function getWidgets(): array
    {
        return [
            'database' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'queries',
                'default' => '[]',
            ],
            'database:badge' => [
                'map' => 'queries.nb_statements',
                'default' => 0,
            ],
        ];
    }

    /**
     * Assets
     *
     * @return array<string, string>
     */
    public function getAssets(): array
    {
        return [
            'css' => 'widgets/sqlqueries/widget.css',
            'js' => 'widgets/sqlqueries/widget.js',
        ];
    }
}
