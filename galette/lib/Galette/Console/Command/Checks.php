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

use Galette\Core\CheckModules;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:checks',
    description: 'Check Galette requirements'
)]
class Checks extends AbstractCommand
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
        $output->writeln([
            '<info><href=https://galette.eu>Galette</> requirements checks</info>',
            '<info>===========================</info>',
            ''
        ]);

        $io = new SymfonyStyle($input, $output);
        $cm = new CheckModules(false);

        $check_messages = [];

        $phpok = !version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<'); //@phpstan-ignore-line
        if (!$phpok) { //@phpstan-ignore-line
            $check_messages [] = sprintf(
                '<error>❌ PHP version %s is too old: %s minimum required</error>',
                PHP_VERSION,
                GALETTE_PHP_MIN
            );
        } else {
            $check_messages [] = sprintf(
                '<info>✔️ PHP version: %s</info>',
                PHP_VERSION
            );
            require_once GALETTE_ROOT . '/vendor/autoload.php';
            $cm->doCheck(false); //do not load with translations!

            $modules_missing = $cm->getMissings();
            foreach ($modules_missing as $m) {
                $check_messages [] = sprintf(
                    '<error>❌ Missing  %s</error>',
                    $m
                );
            }

            $modules_goods = $cm->getGoods();
            foreach ($modules_goods as $m) {
                $check_messages [] = sprintf(
                    '<info>✔️ %s</info>',
                    $m
                );
            }
            $modules_should = $cm->getShoulds();
            foreach ($modules_should as $m) {
                $check_messages [] = sprintf(
                    '<comment>⚠️ Recommended %s not installed</comment>',
                    $m
                );
            }
        }

        $io->listing($check_messages);

        if (
            !$phpok //@phpstan-ignore-line
            || !$cm->isValid()
        ) {
            $io->error('Something is wrong with your setup :(');
            return Command::FAILURE;
        }
        $io->writeln('<comment>Directories rights are not checked from the command line, it is not reliable enough</comment>');
        $io->success('Everything is OK :)');
        return Command::SUCCESS;
    }
}
