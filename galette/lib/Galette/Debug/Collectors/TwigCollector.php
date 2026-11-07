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
use Twig\Profiler\Dumper\HtmlDumper;
use Twig\Profiler\Profile;

/**
 * Twig templates collector, reading the Twig profiler
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwigCollector extends DataCollector implements Renderable, AssetProvider
{
    use HasTimeDataCollector;

    /**
     * @param Profile $profile Root profile filled by Twig's ProfilerExtension
     */
    public function __construct(private readonly Profile $profile)
    {
    }

    /**
     * Collect data
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $templates = [];
        $counts = ['block' => 0, 'macro' => 0];
        $this->walk($this->profile, $templates, $counts);

        $duration = 0.0;
        $memory = 0;
        foreach ($this->profile->getProfiles() as $root_template) {
            $duration += $root_template->getDuration();
            $memory = max($memory, $root_template->getPeakMemoryUsage());
        }

        return [
            'nb_templates' => count($templates),
            'nb_blocks' => $counts['block'],
            'nb_macros' => $counts['macro'],
            'templates' => $templates,
            'accumulated_render_time' => $duration,
            'accumulated_render_time_str' => $this->getDataFormatter()->formatDuration($duration),
            'memory_usage_str' => $this->getDataFormatter()->formatBytes($memory),
            'callgraph' => count($templates) > 0 ? (new HtmlDumper())->dump($this->profile) : '',
        ];
    }

    /**
     * Walk the profiles tree
     *
     * @param Profile                       $profile   Current profile
     * @param list<array<string, mixed>>    $templates Templates found so far
     * @param array{block: int, macro: int} $counts    Blocks and macros counts
     */
    private function walk(Profile $profile, array &$templates, array &$counts): void
    {
        foreach ($profile->getProfiles() as $child) {
            if ($child->isTemplate()) {
                $templates[] = [
                    'name' => $child->getTemplate(),
                    'render_time' => $child->getDuration(),
                    'render_time_str' => $this->getDataFormatter()->formatDuration($child->getDuration()),
                    'memory_str' => $this->getDataFormatter()->formatBytes($child->getMemoryUsage()),
                ];
                if ($this->hasTimeDataCollector()) {
                    $this->addTimeMeasure($child->getTemplate(), $child->getStartTime(), $child->getEndTime());
                }
            } elseif ($child->isBlock()) {
                ++$counts['block'];
            } elseif ($child->isMacro()) {
                ++$counts['macro'];
            }
            $this->walk($child, $templates, $counts);
        }
    }

    /**
     * Collector name
     */
    public function getName(): string
    {
        return 'twig';
    }

    /**
     * Widgets
     *
     * @return array<string, array<string, mixed>>
     */
    public function getWidgets(): array
    {
        return [
            'twig' => [
                'icon' => 'file-code',
                'widget' => 'PhpDebugBar.Widgets.TemplatesWidget',
                'map' => 'twig',
                'default' => '[]',
            ],
            'twig:badge' => [
                'map' => 'twig.nb_templates',
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
            'css' => 'widgets/templates/widget.css',
            'js' => 'widgets/templates/widget.js',
        ];
    }
}
