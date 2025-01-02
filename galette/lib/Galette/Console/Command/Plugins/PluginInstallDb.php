<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Plugins database install console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:plugins:install-db',
    description: 'Install Galette plugins database'
)]
class PluginInstallDb extends AbstractPlugins
{
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
        global $zdb;

        $io = new SymfonyStyle($input, $output);
        $selected = $input->getArgument('plugins');
        if ($selected === [self::ALL]) {
            $selected = $this->getRelevantPlugins($io);
        } else {
            $selected = $this->getSelectedModules($io, $selected);
        }

        $errors = [];
        $install = new \Galette\Core\PluginInstall();
        $install
            ->setMode($install::INSTALL)
            ->setDbType(TYPE_DB, $errors)
            ->setDsn(HOST_DB, PORT_DB, NAME_DB, USER_DB, PWD_DB)
            ->setTablesPrefix(PREFIX_DB)
        ;

        foreach ($selected as $module_id => $module) {
            //$install->setInstalledVersion($post['previous_version'] ?? null);
            $install->executeScripts($zdb, $module['root']);
            $io->success(sprintf('Database for plugin "%s" installed', $module_id));
        }

        return Command::SUCCESS;
    }

    /**
     * Get relevant plugins (actives, with database) for current command
     *
     * @param SymfonyStyle $io Output interface
     *
     * @return array<string, array<string, string>>
     */
    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $enabled_plugins = $this->plugins->getModules();

        $relevant_plugins = [];
        foreach ($enabled_plugins as $module_id => $module) {
            if ($this->plugins->needsDatabase($module_id)) {
                $relevant_plugins[$module_id] = $module;
            } else {
                $io->writeln(
                    sprintf('Plugin "%s" does not use a database', $module_id),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }

        return $relevant_plugins;
    }

    /**
     * Get validated selected modules
     *
     * @param SymfonyStyle $io        Output interface
     * @param string[]     $requested Requested modules
     *
     * @return array<string, array<string, string>>
     */
    protected function getSelectedModules(SymfonyStyle $io, array $requested): array
    {
        $relevant = $this->getRelevantPlugins($io);
        $selected = [];
        foreach ($requested as $module_id) {
            if (isset($relevant[$module_id]) && $this->plugins->needsDatabase($module_id)) {
                $selected[$module_id] = $relevant[$module_id];
            } else {
                $io->warning(sprintf('Plugin "%s" is not relevant for this command', $module_id));
            }
        }

        return $selected;
    }
}
