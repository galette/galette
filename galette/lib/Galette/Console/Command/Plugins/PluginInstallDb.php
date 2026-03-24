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

use Galette\Core\Installation\Step\DatabaseCheckStep;
use Galette\Core\Installation\Step\DatabaseInstallStep;
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

        // Database permission check (once for all plugins)
        $dbCheckStep = new DatabaseCheckStep($install);
        $dbCheckResult = $dbCheckStep->execute();
        $this->displayStepResult($io, $dbCheckResult);
        if (!$dbCheckResult->isSuccess()) {
            $io->error('Database permission check failed.');
            return Command::FAILURE;
        }

        $exitCode = Command::SUCCESS;
        foreach ($selected as $module_id => $module) {
            $io->section(sprintf('Installing database for plugin "%s"', $module_id));
            $dbInstallStep = new DatabaseInstallStep($install);
            $result = $dbInstallStep->execute(['zdb' => $zdb, 'scripts_path' => $module['root']]);
            $this->displayStepResult($io, $result);
            if ($result->isSuccess()) {
                $io->success(sprintf('Database for plugin "%s" installed', $module_id));
            } else {
                $io->error(sprintf('Database for plugin "%s" could not be installed', $module_id));
                $exitCode = Command::FAILURE;
            }
        }

        return $exitCode;
    }

    /**
     * Get relevant plugins (actives, with database) for current command
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
