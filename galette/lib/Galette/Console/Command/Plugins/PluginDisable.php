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
 * Plugins deactivation console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

#[AsCommand(
    name: 'galette:plugins:disable',
    description: 'Disable Galette plugins'
)]
class PluginDisable extends AbstractPlugins
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
        $io = new SymfonyStyle($input, $output);
        $selected = $input->getArgument('plugins');
        if ($selected === [self::ALL]) {
            $selected = array_keys($this->getRelevantPlugins($io));
        }

        foreach ($selected as $module_id) {
            $this->plugins->deactivateModule($module_id);
            $io->success(sprintf('Plugin "%s" disabled', $module_id));
        }

        return Command::SUCCESS;
    }

    /**
     * Get relevant plugins (enabled ones) for current command
     *
     * @param SymfonyStyle $io Output interface
     *
     * @return array<string, array<string, string>>
     */
    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $enabled_plugins = $this->plugins->getModules();
        return $enabled_plugins;
    }
}
