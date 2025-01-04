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
 * Plugins activation console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

#[AsCommand(
    name: 'galette:plugins:enable',
    description: 'Enable Galette plugins'
)]
class PluginEnable extends AbstractPlugins
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
            $this->plugins->activateModule($module_id);
            $io->success(sprintf('Plugin "%s" enabled', $module_id));
        }

        return Command::SUCCESS;
    }

    /**
     * Get relevant plugins (disabled ones) for current command
     *
     * @param SymfonyStyle $io Output interface
     *
     * @return array<string, array<string, string>>
     */
    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $disabled_plugins = $this->plugins->getDisabledModules();

        $relevant_plugins = [];
        foreach ($disabled_plugins as $module_id => $module) {
            if ($module['cause'] == \Galette\Core\Plugins::DISABLED_EXPLICIT) {
                $relevant_plugins[$module_id] = $module;
            } else {
                switch ($module['cause']) {
                    case \Galette\Core\Plugins::DISABLED_COMPAT:
                        $module['cause'] = 'Not compatible';
                        break;
                    case \Galette\Core\Plugins::DISABLED_MISS:
                        $module['cause'] = 'Miss a required file';
                        break;
                }
                $io->writeln(
                    sprintf('Plugin "%s" is not explicitly disabled (%s)', $module_id, $module['cause']),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }

        return $relevant_plugins;
    }
}
