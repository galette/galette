<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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

        $phpok = !version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<'); //@phpstan-ignore booleanNot.alwaysTrue
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
            !$phpok //@phpstan-ignore booleanNot.alwaysFalse
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
