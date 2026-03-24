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
        $io->writeln('<comment>Directories rights are not checked from the command line, it is not reliable enough</comment>');

        $checkStep = new CheckStep(new \Galette\Core\Install());
        $result = $checkStep->execute(['skip_permissions' => true]);
        $this->displayStepResult($io, $result);

        if (!$result->isSuccess()) {
            $io->error('Something is wrong with your setup :(');
            return Command::FAILURE;
        }
        $io->success('Everything is OK :)');
        return Command::SUCCESS;
    }
}
