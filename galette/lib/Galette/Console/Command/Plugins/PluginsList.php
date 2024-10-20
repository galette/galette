<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Console\Command\Plugins;

use Galette\Console\Command\AbstractCommand;
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
     *
     * @return void
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
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        $output->writeln([
            '<info><href=https://galette.eu>Galette</> plugins</info>',
            '<info>===============</info>',
            ''
        ]);

        /** @var \Galette\Core\Plugins $plugins */
        $plugins = $container->get('plugins');
        $io = new SymfonyStyle($input, $output);

        $definitions = [];
        if (!$input->getOption('enabled') && !$input->getOption('disabled') || $input->getOption('enabled')) {
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

        if (!$input->getOption('disabled') && !$input->getOption('enabled') || $input->getOption('disabled')) {
            foreach ($plugins->getDisabledModules() as $module_id => $module) {
                if ($input->getOption('complete')) {
                    switch ($module['cause']) {
                        case \Galette\Core\Plugins::DISABLED_COMPAT:
                            $module['cause'] = 'Not compatible';
                            break;
                        case \Galette\Core\Plugins::DISABLED_MISS:
                            $module['cause'] = 'Miss a required file';
                            break;
                        case \Galette\Core\Plugins::DISABLED_EXPLICIT:
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

        if (!$input->getOption('complete')) {
            $io->listing($definitions);
        }

        return Command::SUCCESS;
    }
}
