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

namespace Galette\Console\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
     *
     * @return void
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
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $installer;

        $output->writeln([
            '<info>Welcome to <href=https://galette.eu>Galette</> installer!</info>',
            '<info>=============================</info>',
            ''
        ]);

        //set a flag saying we work from installer
        //that way, in galette.inc.php, we'll only include relevant parts
        $installer = true;

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
                $db_type = $install->getDbType();
            } else {
                $db_type = $io->choice(
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
                $db_name = $install->getDbName();
            } else {
                $db_name = $io->ask('Database name', 'galette');
            }
        }

        $db_prefix = $input->getOption('dbprefix');
        if ($db_prefix === null) {
            if ($use_config && $install->getTablesPrefix() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database prefix</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_prefix = $install->getTablesPrefix();
            } else {
                $db_prefix = $io->ask('Database prefix', 'galette_');
            }
        }

        $db_host = $input->getOption('dbhost');
        if ($db_host === null) {
            if ($use_config && $install->getDbHost() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database host</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_host = $install->getDbHost();
            } else {
                $db_host = $io->ask('Database host', 'localhost');
            }
        }

        $db_port = $input->getOption('dbport');
        if ($db_port === null) {
            if ($use_config && $install->getDbPort() !== null) {
                $io->writeln(
                    '<comment>Using existing configuration for database port</comment>',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $db_port = $install->getDbPort();
            } else {
                $db_port = $io->ask('Database port', $db_type === 'mysql' ? '3306' : '5432');
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
                $db_user = $io->ask('Database user', 'galette');
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
            new TableSeparator(),
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

        if (!$install->testDbConnexion()) {
            throw new \RuntimeException('Database connection failed');
        }

        global $zdb;
        $zdb = new \Galette\Core\Db(
            [
                'TYPE_DB' => $db_type,
                'HOST_DB' => $db_host,
                'PORT_DB' => $db_port,
                'USER_DB' => $db_user,
                'PWD_DB' => $db_pass,
                'NAME_DB' => $db_name,
                'PREFIX_DB' => $db_prefix
            ]
        );

        /** When tables already exists and DROP not allowed at this time
         * the showed error is about CREATE, whenever CREATE is allowed */
        //We delete the table if exists, no error at this time
        $zdb->dropTestTable();

        $results = $zdb->grantCheck($install->getMode());
        $sql_messages = [];
        $sql_error = false;

        //test returned values
        if ($results['create'] instanceof Exception) {
            $sql_messages[] = '<error>❌ CREATE operation not allowed</error>';
            $sql_error = true;
        } elseif ($results['create'] != '') {
            $sql_messages[] = '<info>✔️ CREATE operation allowed</info>';
        }

        if ($results['insert'] instanceof Exception) {
            $sql_messages[] = '<error>❌ INSERT operation not allowed</error>';
            $sql_error = true;
        } elseif ($results['insert'] != '') {
            $sql_messages[] = '<info>✔️ INSERT operation allowed</info>';
        }

        if ($results['update'] instanceof Exception) {
            $sql_messages[] = '<error>❌ UPDATE operation not allowed</error>';
            $sql_error = true;
        } elseif ($results['update'] != '') {
            $sql_messages[] = '<info>✔️ UPDATE operation allowed</info>';
        }

        if ($results['select'] instanceof Exception) {
            $sql_messages[] = '<error>❌ SELECT operation not allowed</error>';
            $sql_error = true;
        } elseif ($results['select'] != '') {
            $sql_messages[] = '<info>✔️ SELECT operation allowed</info>';
        }

        if ($results['delete'] instanceof Exception) {
            $sql_messages[] = '<error>❌ DELETE operation not allowed</error>';
            $sql_error = true;
        } elseif ($results['delete'] != '') {
            $sql_messages[] = '<info>✔️ DELETE operation allowed</info>';
        }

        if ($results['drop'] instanceof Exception) {
            $sql_messages[] = '<error>❌ DROP operation not allowed</error>';
            $sql_error = true;
        } elseif ($results['drop'] != '') {
            $sql_messages[] = '<info>✔️ DROP operation allowed</info>';
        }

        $io->listing($sql_messages);

        if ($sql_error) {
            $io->error('SQL operations check failed :/');
            return Command::FAILURE;
        }

        $io->info('Installing database, please wait...');
        $installed = $install->executeScripts($zdb);
        if (!$installed) {
            $io->error('Database has not been installed');
            return Command::FAILURE;
        }

        $install->initDbConstants();

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
        if (!defined('GALETTE_INSTALLER')) {
            define('GALETTE_INSTALLER', true);
        }
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
