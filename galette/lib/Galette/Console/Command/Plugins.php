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

namespace Galette\Console\Command;

use Galette\Core\CheckModules;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'galette:plugins',
    description: 'Manage Galette plugins'
)]
class Plugins extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('plugins', InputArgument::IS_ARRAY|InputArgument::OPTIONAL, 'Plugins names')
            ->addoption('enable', null, InputOption::VALUE_NONE, 'Enable plugin(s)')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Disable plugin(s)')
            ->addOption('install-db', null, InputOption::VALUE_NONE, 'Install plugin(s) database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        $output->writeln([
            '<info><href=https://galette.eu>Galette</> plugins management</info>',
            '<info>==========================</info>',
            ''
        ]);

        /** @var \Galette\Core\Plugins $plugins */
        $plugins = $container->get('plugins');
        $io = new SymfonyStyle($input, $output);

        $requested_plugins = $input->getArgument('plugins');

        $enabled_plugins = $plugins->getModules();
        $disabled_plugins = $plugins->getDisabledModules();

        if ($requested_plugins) {
            $unknown_plugins = array_diff($requested_plugins, array_keys($enabled_plugins), array_keys($disabled_plugins));
            if (count($unknown_plugins)) {
                $io->error(sprintf('Unknown plugin(s): %s', implode(', ', $unknown_plugins)));
                return Command::FAILURE;
            }
        }

        if (!count($input->getOptions())) {
            $io->error('No action specified');
            return Command::FAILURE;
        }

        if ($input->getOption('enable') && $input->getOption('disable')) {
            $io->error('You can\'t enable and disable plugins at the same time!');
            return Command::FAILURE;
        }

        if ($input->getOption('disable') && $input->getOption('install-db')) {
            $io->error('You can\'t disable and install database at the same time!');
            return Command::FAILURE;
        }

        /*if ($input->getOption('enable')) {
            $plugins->enable($requested_plugins);
            $io->success('Plugin(s) enabled');
        }*/
        /*if ($input->getOption('disable')) {
            $plugins->disable($requested_plugins);
            $io->success('Plugin(s) disabled');
        }*/
        /*if ($input->getOption('install-db')) {
            $plugins->installDatabase($requested_plugins);
            $io->success('Plugin(s) database installed');
        }*/

        return Command::SUCCESS;
    }
}