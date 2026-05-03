<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Core\FeatureFlagManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Feature flags status console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:feature:status',
    description: 'List all feature flags and their status'
)]
class FeatureStatus extends AbstractCommand
{
    /**
     * Command execution
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln([
            '<info><href=https://galette.eu>Galette</> Feature Flags Status</info>',
            '<info>===================================</info>',
            ''
        ]);

        $io = new SymfonyStyle($input, $output);
        $featureFlags = new FeatureFlagManager();

        // Display current mode
        $debugMode = $featureFlags->isDebugMode();
        $io->section('Environment');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Debug Mode', $debugMode ? '<info>✓ Enabled</info>' : '<error>✗ Disabled</error>'],
                ['GALETTE_DEBUG', defined('GALETTE_DEBUG') && GALETTE_DEBUG ? 'true' : 'false'],
                ['GALETTE_MODE', defined('GALETTE_MODE') ? GALETTE_MODE : '-'],
            ]
        );

        // Get all flags with their status
        $allFlags = $featureFlags->getAllFlagsWithStatus();
        $registryFlags = $featureFlags->getRegistryFlags();

        if ($allFlags === []) {
            $io->section('Available Feature Flags');
            $io->warning('No feature flags found in registry.');
            $io->writeln([
                'To add feature flags, refer to <comment>galette/doc/FEATURE_FLAGS_QUICK_REF.pd</comment>:',
            ]);
            return Command::SUCCESS;
        }

        // Display all flags from registry with their status
        $io->section('All Feature Flags');

        $tableRows = [];
        foreach ($allFlags as $flag => $info) {
            $status = $info['enabled']
                ? '<info>✓ ENABLED</info>'
                : '<error>✗ DISABLED</error>';

            $badges = [];
            if ($info['declared']) {
                $badges[] = '<comment>[active]</comment>';
            }
            if ($info['accessed']) {
                $badges[] = '<fg=cyan>[used]</>';
            }
            if (!isset($registryFlags[$flag])) {
                $badges[] = '<fg=red>[⚠ not registered]</>';
            }

            $badges_str = $badges !== [] ? implode(' ', $badges) : '<fg=gray>[available]</>';

            $reason = '';
            if (!$debugMode && $info['declared']) {
                $reason = '<comment>(requires debug mode)</comment>';
            } elseif (!empty($info['requires']) && !$info['dependencies_satisfied']) {
                $missing = $info['requires'];
                // Filter to show only missing dependencies
                $actuallyMissing = array_filter($missing, fn($dep) => !($allFlags[$dep]['enabled'] ?? false));
                if ($actuallyMissing !== []) {
                    $reason = sprintf('<error>(missing: %s)</error>', implode(', ', $actuallyMissing));
                }
            } elseif (!$info['declared'] && !isset($registryFlags[$flag])) {
                $reason = '<error>unregistered</error>';
            } elseif (!$info['declared']) {
                $reason = '<comment>not enabled</comment>';
            }

            // Show dependencies if any
            $dependencies = !empty($info['requires'])
                ? '<fg=yellow>→ ' . implode(', ', $info['requires']) . '</>'
                : '';

            $tableRows[] = [
                $flag,
                $status,
                $info['description'],
                $dependencies,
                $badges_str,
                $reason
            ];
        }

        $io->table(
            ['Flag Name', 'Status', 'Description', 'Dependencies', 'State', 'Note'],
            $tableRows
        );

        // Display statistics
        $enabledCount = count(array_filter($allFlags, fn($info) => $info['enabled']));
        $declaredCount = count(array_filter($allFlags, fn($info) => $info['declared']));
        $accessedCount = count(array_filter($allFlags, fn($info) => $info['accessed']));
        $registeredCount = count($registryFlags);

        $io->writeln([
            '',
            '<info>Statistics:</info>',
            sprintf('  - Total registered flags: %d', $registeredCount),
            sprintf('  - Enabled flags: %d', $enabledCount),
            sprintf('  - Declared in config: %d', $declaredCount),
            sprintf('  - Used in code: %d', $accessedCount),
            ''
        ]);

        // Display warnings if any
        $unregistered = array_filter($allFlags, fn($info) => !isset($registryFlags[array_search($info, $allFlags)]));
        if ($unregistered !== []) {
            $io->warning(
                sprintf(
                    'Found %d unregistered flag(s) used in code. Please register them',
                    count($unregistered)
                )
            );
        }

        // Display usage information
        if (!$debugMode) {
            $io->warning(
                'Feature flags are only enabled when GALETTE_DEBUG is set to true. '
                . 'They are automatically disabled in production mode for security.'
            );
        } elseif ($enabledCount > 0) {
            $io->success(sprintf('%d feature flag(s) currently active.', $enabledCount));
        } else {
            $io->note('No flags are currently enabled. Enable them in behavior.inc.php to use experimental features.');
        }

        return Command::SUCCESS;
    }
}
