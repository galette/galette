<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command\Plugins;

use Galette\Core\I18n;
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
     * Constructor
     *
     * @param string  $basepath Base path to Galette installation
     * @param Plugins $plugins  Plugins manager
     * @param I18n    $i18n     Current language, plugin default data is created with
     */
    public function __construct(string $basepath, Plugins $plugins, private readonly I18n $i18n)
    {
        parent::__construct($basepath, $plugins);
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $zdb;

        $io = new SymfonyStyle($input, $output);
        if (!$this->validatePlugins($input, $io)) {
            return Command::FAILURE;
        }
        $selected = $input->getArgument('plugins');
        $selected = $selected === [self::ALL] ? $this->getRelevantPlugins($io) : $this->getSelectedModules($io, $selected);

        $errors = [];
        $failed = [];

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

            $install->setPlugin($this->plugins, $module_id, $this->i18n);

            $disabled_cause = $this->plugins->getDisabledCause($module_id);

            if ($disabled_cause === Plugins::DISABLED_NOT_UP2DATE) {
                $installed_version = $this->plugins->getInstalledDbVersion($module_id);
                $install
                    ->setMode($install::UPDATE)
                    ->setInstalledVersion($installed_version)
                ;
                if (!$install->executeScripts($zdb, $module['root'])) {
                    $failed[] = $module_id;
                    $io->error(sprintf('Unable to upgrade database for plugin "%s"', $module_id));
                    continue;
                }
                $install->setPluginInstalled($zdb, $this->plugins, $module_id);
                $io->success(sprintf('Database for plugin "%s" upgraded', $module_id));
            } else {
                $install->setMode($install::INSTALL);
                if (!$install->executeScripts($zdb, $module['root'])) {
                    $failed[] = $module_id;
                    $io->error(sprintf('Unable to install database for plugin "%s"', $module_id));
                    continue;
                }
                if (!$install->initPlugin($this->plugins, $zdb)) {
                    //tables are there: the plugin is usable, without its default data
                    $failed[] = $module_id;
                    $io->warning(sprintf('Unable to create default data for plugin "%s"', $module_id));
                }
                $install->setPluginInstalled($zdb, $this->plugins, $module_id);
                $io->success(sprintf('Database for plugin "%s" installed', $module_id));
            }
        }

        return $failed === [] ? Command::SUCCESS : Command::FAILURE;
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

            if (!in_array($disabled_cause, $allowed_causes, strict: true)) {
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
