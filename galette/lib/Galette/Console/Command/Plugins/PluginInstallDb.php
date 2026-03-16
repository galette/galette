<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

use Galette\Core\Plugins;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Plugins database install console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-import-type Modules from Plugins
 */
#[AsCommand(
    name: 'galette:plugins:install-db',
    description: 'Install Galette plugins database'
)]
class PluginInstallDb extends AbstractPlugins
{
    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $zdb;

        $io = new SymfonyStyle($input, $output);
        $selected = $input->getArgument('plugins');
        $selected = $selected === [self::ALL] ? $this->getRelevantPlugins($io) : $this->getSelectedModules($io, $selected);

        $errors = [];
        $install = new \Galette\Core\PluginInstall();
        $install
            ->setMode($install::INSTALL)
            ->setDbType(TYPE_DB, $errors)
            ->setDsn(HOST_DB, PORT_DB, NAME_DB, USER_DB, PWD_DB)
            ->setTablesPrefix(PREFIX_DB)
        ;

        foreach ($selected as $module_id => $module) {
            $install->executeScripts($zdb, $module['root']);
            $io->success(sprintf('Database for plugin "%s" installed', $module_id));
            $install->setPluginInstalled($zdb, $this->plugins, $module_id);
        }

        return Command::SUCCESS;
    }

    /**
     * Get relevant plugins (inactives that require a database) for current command
     *
     * @return Modules
     */
    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $plugins = $this->plugins->getDisabledModules();

        $relevant_plugins = [];
        foreach ($plugins as $module_id => $module) {
            if (!$this->plugins->needsDatabase($module_id)) {
                $io->writeln(
                    sprintf('Plugin "%s" does not use a database', $module_id),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                continue;
            }

            // Only consider plugins that are disabled because they are not installed
            // or not up to date. Other disabled causes are not suitable for running installation scripts.
            $disabled_cause = $this->plugins->getDisabledCause($module_id);
            $allowed_causes = [
                Plugins::DISABLED_NOT_INSTALLED,
                Plugins::DISABLED_NOT_UP2DATE,
            ];

            if (!in_array($disabled_cause, $allowed_causes, true)) {
                $io->writeln(
                    sprintf(
                        'Plugin "%s" is disabled (%s); skipping database installation',
                        $module_id,
                        $this->getDisplayCause($disabled_cause)
                    ),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                continue;
            }

            $relevant_plugins[$module_id] = $module;
        }

        return $relevant_plugins;
    }

    /**
     * Get validated selected modules
     *
     * @param SymfonyStyle $io        Output interface
     * @param string[]     $requested Requested modules
     *
     * @return Modules
     */
    protected function getSelectedModules(SymfonyStyle $io, array $requested): array
    {
        //TODO: can maybe be simplified
        $relevant = $this->getRelevantPlugins($io);
        $selected = [];
        foreach ($requested as $module_id) {
            if (isset($relevant[$module_id]) && $this->plugins->needsDatabase($module_id)) {
                $selected[$module_id] = $relevant[$module_id];
            } else {
                $io->warning(sprintf('Invalid command for plugin "%s". Check its state.', $module_id));
            }
        }

        return $selected;
    }
}
