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
 * @phpstan-type ModuleId string
 * @phpstan-import-type Module from Plugins
 */
#[AsCommand(
    name: 'galette:plugins:list',
    description: 'List existing Galette plugins'
)]
class PluginsList extends AbstractCommand
{
    use DisplayCause;

    private Plugins $plugins;

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'complete',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Display complete information'
            )
            ->addOption(
                name: 'enabled',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Display enabled plugins'
            )
            ->addOption(
                name: 'disabled',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Display disabled plugins'
            )
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

        $this->plugins = $container->get(Plugins::class);
        $io = new SymfonyStyle($input, $output);

        $definitions = [];
        if (!$input->getOption('enabled') && !$input->getOption('disabled') || $input->getOption('enabled')) {
            $this->listEnabledPlugins($input, $io, $definitions);
        }

        if (!$input->getOption('disabled') && !$input->getOption('enabled') || $input->getOption('disabled')) {
            $this->listDisabledPlugins($input, $io, $definitions);
        }

        if (!$input->getOption('complete')) {
            $io->listing($definitions);
        }

        return Command::SUCCESS;
    }

    /**
     * List of enabled plugins
     *
     * @param InputInterface $input       Console input
     * @param SymfonyStyle   $io          Console style
     * @param string[]       $definitions Definitions (for simple output)
     */
    private function listEnabledPlugins(
        InputInterface $input,
        SymfonyStyle $io,
        array &$definitions
    ): void {
        foreach ($this->plugins->getActiveModules() as $module_id => $module) {
            $this->pluginDefinition(
                input: $input,
                io: $io,
                module_id: $module_id,
                module: $module,
                definitions: $definitions
            );
        }
    }

    /**
     * List of disabled plugins
     *
     * @param InputInterface $input       Console input
     * @param SymfonyStyle   $io          Console style
     * @param string[]       $definitions Definitions (for simple output)
     */
    private function listDisabledPlugins(
        InputInterface $input,
        SymfonyStyle $io,
        array &$definitions
    ): void {
        foreach ($this->plugins->getDisabledModules() as $module_id => $module) {
            $this->pluginDefinition(
                input: $input,
                io: $io,
                module_id: $module_id,
                module: $module,
                definitions: $definitions
            );
        }
    }

    /**
     * @param Module   $module
     * @param string[] $definitions Definitions (for simple output)
     */
    private function pluginDefinition(
        InputInterface $input,
        SymfonyStyle $io,
        string $module_id,
        array $module,
        array &$definitions
    ): void {
        $name = $module['name'];
        $desc = $module['desc'];
        $version = $module['version'];
        $author = $module['author'];
        $date = $module['date'] ?? 'N/A';

        $tag = $this->plugins->isDisabled($module_id) ? 'error' : 'info';
        if ($input->getOption('complete')) {
            $active = 'Yes';
            if ($this->plugins->isDisabled($module_id)) {
                $active = sprintf(
                    'No (%s)',
                    $this->getDisplayCause($this->plugins->getDisabledCause($module_id))
                );
            }
            $io->definitionList(
                sprintf('<%1$s>%2$s (%3$s)</%1$s>', $tag, $name, $module_id),
                ['Active' => $active],
                ['ID' => $module_id],
                ['Name' => $name],
                ['Description' => $desc],
                ['Version' => $version],
                ['Author' => $author],
                ['Date' => $date],
                ['Has database' => $this->plugins->needsDatabase($module_id) ? 'Yes' : 'No']
            );
        } else {
            $definitions[] = sprintf(
                '<%1$s>%2$s %3$s (%4$s)</%1$s>%5$s',
                $tag,
                $name,
                $version,
                $module_id,
                $this->plugins->isDisabled($module_id) ? ' ' . $this->getDisplayCause($this->plugins->getDisabledCause($module_id)) : ''
            );
        }
    }
}
