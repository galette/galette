<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command\Plugins;

use Galette\Console\Command\AbstractCommand;
use Galette\Core\Plugins;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Plugins list console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:plugins:list',
    description: 'List existing Galette plugins'
)]
class PluginsList extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addOption('complete', null, InputOption::VALUE_NONE, 'Display complete information')
            ->addOption('enabled', null, InputOption::VALUE_NONE, 'Display enabled plugins')
            ->addOption('disabled', null, InputOption::VALUE_NONE, 'Display disabled plugins')
        ;
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        $output->writeln([
            '<info><href=https://galette.eu>Galette</> plugins</info>',
            '<info>===============</info>',
            ''
        ]);

        /** @var Plugins $plugins */
        $plugins = $container->get(Plugins::class);
        $io = new SymfonyStyle($input, $output);

        $definitions = [];
        if (!$input->getOption('enabled') && !$input->getOption('disabled') || $input->getOption('enabled')) {
            $this->listEnabledPlugins($plugins, $input, $io, $definitions);
        }

        if (!$input->getOption('disabled') && !$input->getOption('enabled') || $input->getOption('disabled')) {
            $this->listDisabledPlugins($plugins, $input, $io, $definitions);
        }

        if (!$input->getOption('complete')) {
            $io->listing($definitions);
        }

        return Command::SUCCESS;
    }

    /**
     * List of enabled plugins
     *
     * @param Plugins        $plugins     Plugins instance
     * @param InputInterface $input       Console input
     * @param SymfonyStyle   $io          Console style
     * @param string[]       $definitions Definitions (for simple output)
     */
    private function listEnabledPlugins(
        Plugins $plugins,
        InputInterface $input,
        SymfonyStyle $io,
        array &$definitions
    ): void {
        foreach ($plugins->getModules() as $module_id => $module) {
            if ($input->getOption('complete')) {
                $io->definitionList(
                    sprintf('%s (%s)', $module['name'], $module_id),
                    ['Active' => 'Yes'],
                    ['ID' => $module_id],
                    ['Name' => $module['name']],
                    ['Description' => $module['desc']],
                    ['Version' => $module['version']],
                    ['Author' => $module['author']],
                    ['Date' => $module['date']],
                    ['Has database' => $plugins->needsDatabase($module_id) ? 'Yes' : 'No']
                );
            } else {
                $definitions[] = sprintf('%s (%s)', $module['name'], $module['version']);
            }
        }
    }

    /**
     * List of disabled plugins
     *
     * @param Plugins        $plugins     Plugins instance
     * @param InputInterface $input       Console input
     * @param SymfonyStyle   $io          Console style
     * @param string[]       $definitions Definitions (for simple output)
     */
    private function listDisabledPlugins(
        Plugins $plugins,
        InputInterface $input,
        SymfonyStyle $io,
        array &$definitions
    ): void {
        foreach ($plugins->getDisabledModules() as $module_id => $module) {
            if ($input->getOption('complete')) {
                switch ($module['cause']) {
                    case Plugins::DISABLED_COMPAT:
                        $module['cause'] = 'Not compatible';
                        break;
                    case Plugins::DISABLED_MISS:
                        $module['cause'] = 'Miss a required file';
                        break;
                    case Plugins::DISABLED_EXPLICIT:
                        $module['cause'] = 'Explicitly disabled';
                        break;
                }
                $io->definitionList(
                    $module_id,
                    ['Active' => 'No'],
                    ['Cause' => $module['cause']]
                );
            } else {
                $definitions[] = sprintf('%s (disabled)', $module_id);
            }
        }
    }
}
