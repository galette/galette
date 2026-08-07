<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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

        foreach ($selected as $module_id => $module) {
            $install = new \Galette\Core\PluginInstall();
            $install
                ->setDbType(TYPE_DB, $errors)
                ->setDsn(
                    host: HOST_DB,
                    port: PORT_DB,
                    name: NAME_DB,
                    user: USER_DB,
                    pass: PWD_DB
                )
                ->setTablesPrefix(PREFIX_DB)
            ;

            $disabled_cause = $this->plugins->getDisabledCause($module_id);

            if ($disabled_cause === Plugins::DISABLED_NOT_UP2DATE) {
                $installed_version = $this->plugins->getInstalledDbVersion($module_id);
                $install
                    ->setMode($install::UPDATE)
                    ->setInstalledVersion($installed_version)
                ;
                $install->executeScripts($zdb, $module['root']);
                $install->setPluginInstalled($zdb, $this->plugins, $module_id);
                $io->success(sprintf('Database for plugin "%s" upgraded', $module_id));
            } else {
                $install->setMode($install::INSTALL);
                $install->executeScripts($zdb, $module['root']);
                $install->setPluginInstalled($zdb, $this->plugins, $module_id);
                $io->success(sprintf('Database for plugin "%s" installed', $module_id));
            }
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
        $relevant = $this->getRelevantPlugins($io);
        foreach (array_diff($requested, array_keys($relevant)) as $module_id) {
            $io->warning(sprintf('Invalid command for plugin "%s". Check its state.', $module_id));
        }
        return array_intersect_key($relevant, array_flip($requested));
    }
}
