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

namespace Galette\Console\Command;

use Galette\Core\Installation\Step\CheckStep;
use Galette\Core\Installation\Step\DatabaseCheckStep;
use Galette\Core\Installation\Step\DatabaseInstallStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Safe\define;

/**
 * Install console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:install',
    description: 'Install Galette'
)]
class Install extends AbstractCommand
{
    /**
     * Database types
     *
     * @var array<string>
     */
    private array $db_types = ['mysql', 'pgsql'];

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addoption('dbtype', null, InputOption::VALUE_REQUIRED, 'Database type (' . implode(', ', $this->db_types) . ')')
            ->addOption('dbhost', null, InputOption::VALUE_REQUIRED, 'Database hostname or IP address')
            ->addOption('dbport', null, InputOption::VALUE_REQUIRED, 'Database port')
            ->addOption('dbname', null, InputOption::VALUE_REQUIRED, 'Database schema name')
            ->addOption('dbprefix', null, InputOption::VALUE_OPTIONAL, 'Database table prefix')
            ->addOption('dbuser', null, InputOption::VALUE_REQUIRED, 'Database user')
            ->addOption('dbpass', null, InputOption::VALUE_OPTIONAL, 'Database password')
            ->addOption('admin', null, InputOption::VALUE_REQUIRED, 'Administrator username')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Administrator password')
            ->addOption('ignore-config', null, InputOption::VALUE_NONE, 'Ignore existing configuration file')
            ->addOption('write-config', 'w', InputOption::VALUE_NONE, 'Write configuration file (incompatible with --ignore-config)')
        ;
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $installer;

        //set a flag saying we work from installer
        //that way, in galette.inc.php, we'll only include relevant parts
        $installer = true;
        if (!defined('GALETTE_INSTALLER')) {
            define('GALETTE_INSTALLER', true);
        }

        $output->writeln([
            '<info>Welcome to <href=https://galette.eu>Galette</> installer!</info>',
            '<info>=============================</info>',
            ''
        ]);

        $io = new SymfonyStyle($input, $output);

        $errors = [];
        $install = new \Galette\Core\Install();

        $use_config = !$input->getOption('ignore-config');
        $config_exists = file_exists($this->basepath . 'config/config.inc.php');
        if ($use_config && $config_exists) {
            $install->loadExistingConfig([], $errors);
        }

        $db_type = $input->getOption('dbtype');
        if ($db_type === null) {
            if ($use_config && $install->getDbType() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database type</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_type = (string)$install->getDbType();
            } else {
                $db_type = (string)$io->choice(
                    'Database type',
                    $this->db_types
                );
            }
        }

        $db_name = $input->getOption('dbname');
        if ($db_name === null) {
            if ($use_config && $install->getDbName() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database name</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_name = (string)$install->getDbName();
            } else {
                $db_name = (string)$io->ask('Database name', 'galette');
            }
        }

        $db_prefix = $input->getOption('dbprefix');
        if ($db_prefix === null) {
            if ($use_config && $install->getTablesPrefix() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database prefix</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_prefix = (string)$install->getTablesPrefix();
            } else {
                $db_prefix = (string)$io->ask('Database prefix', 'galette_');
            }
        }

        $db_host = $input->getOption('dbhost');
        if ($db_host === null) {
            if ($use_config && $install->getDbHost() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database host</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_host = (string)$install->getDbHost();
            } else {
                $db_host = (string)$io->ask('Database host', 'localhost');
            }
        }

        $db_port = $input->getOption('dbport');
        if ($db_port === null) {
            if ($use_config && $install->getDbPort() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database port</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_port = (string)$install->getDbPort();
            } else {
                $db_port = (string)$io->ask('Database port', $db_type === 'mysql' ? '3306' : '5432');
            }
        }

        $db_user = $input->getOption('dbuser');
        if ($db_user === null) {
            if ($use_config && $install->getDbUser() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database user</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_user = USER_DB;
            } else {
                $db_user = (string)$io->ask('Database user', 'galette');
            }
        }

        $db_pass = $input->getOption('dbpass');
        if ($db_pass === null) {
            $db_pass = $io->askHidden('Database password');
        }

        $displayed_db_pass = 'None';
        if ($db_pass !== null) {
            $displayed_db_pass = str_pad('', mb_strlen($db_pass), '*');
        }

        $galette_sa = $input->getOption('admin');
        if ($galette_sa === null) {
            $galette_sa = $io->ask('Superadmin name', 'admin');
        }

        $galette_sa_pass = $input->getOption('password');
        if ($galette_sa_pass === null) {
            if ($input->getOption('no-interaction')) {
                throw new \RuntimeException('Superadmin password is required.');
            }
            $galette_sa_pass = $io->askHidden(
                'Superadmin password',
                function (?string $password) {
                    if ($password === null) {
                        throw new \RuntimeException('Galette super user password cannot be empty.');
                    }
                    return $password;
                }
            );
        }

        $displayed_sa_pass = str_pad('', mb_strlen($galette_sa_pass), '*');

        $io->definitionList(
            'Database information',
            ['Type' => $db_type],
            ['Name' => $db_name],
            ['Prefix' => $db_prefix],
            ['Host' => $db_host],
            ['Port' => $db_port],
            ['User' => $db_user],
            ['Password' => $displayed_db_pass],
            new \Symfony\Component\Console\Helper\TableSeparator(),
            'Superadmin information',
            ['Name' => $galette_sa],
            ['Password' => $displayed_sa_pass]
        );

        if (
            $config_exists
            && $install->getDbType() == $db_type
            && $install->getDbHost() == $db_host
            && $install->getDbPort() == $db_port
            && !$input->getOption('no-interaction')
        ) {
            $io->warning("Configuration file already exists and matches the provided database information.\nAll existing data will be lost if you continue.");
            if (!$io->confirm('Do you want to continue?', false)) {
                $io->writeln('Aborted.');
                return Command::FAILURE;
            }
        }

        $install
            ->setMode(\Galette\Core\Install::INSTALL)
            ->setDbType($db_type, $errors)
            ->setDsn($db_host, $db_port, $db_name, $db_user, $db_pass)
            ->setTablesPrefix($db_prefix)
        ;
        $install->initDbConstants();

        // System requirements check
        $io->section('System requirements check');
        $checkStep = new CheckStep($install);
        $checkResult = $checkStep->execute();
        $this->displayStepResult($io, $checkResult);
        if (!$checkResult->isSuccess()) {
            $io->error('System requirements check failed.');
            return Command::FAILURE;
        }

        // Database access and permissions check
        $io->section('Database access and permissions');
        $dbCheckStep = new DatabaseCheckStep($install);
        $dbCheckResult = $dbCheckStep->execute();
        $this->displayStepResult($io, $dbCheckResult);
        if (!$dbCheckResult->isSuccess()) {
            $io->error('Database permission check failed.');
            return Command::FAILURE;
        }

        global $zdb;
        $zdb = new \Galette\Core\Db();

        // Database installation
        $io->section('Database installation');
        $dbInstallStep = new DatabaseInstallStep($install);
        $dbInstallResult = $dbInstallStep->execute(['zdb' => $zdb]);
        $this->displayStepResult($io, $dbInstallResult);
        if (!$dbInstallResult->isSuccess()) {
            $io->error('Database installation failed.');
            return Command::FAILURE;
        }

        if ($input->getOption('write-config')) {
            $io->info('Writing configuration, please wait...');
            $config_file_ok = $install->writeConfFile();
            if (!$config_file_ok) {
                $io->warning('Configuration file could not be written :(');
                $io->info('Please copy the following content to config/config.inc.php:');
                $io->block($install->getConfigFileContents());
            }
        }

        $install->setAdminInfos($galette_sa, $galette_sa_pass);

        $io->info('Initializing data, please wait...');
        $i18n = new \Galette\Core\I18n();
        $init_ok = $install->initObjects(
            $i18n,
            $zdb,
            new \Galette\Core\Login($zdb, $i18n)
        );
        if (!$init_ok) {
            $io->warning('Data initialization has failed :(');
        }

        $io->success('Galette installation is complete!');
        return Command::SUCCESS;
    }

}
