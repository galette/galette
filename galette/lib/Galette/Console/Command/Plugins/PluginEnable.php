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
 * Plugins activation console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-import-type Modules from Plugins
 */

#[AsCommand(
    name: 'galette:plugins:enable',
    description: 'Enable Galette plugins'
)]
class PluginEnable extends AbstractPlugins
{
    /**
     * Command execution
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
     * @return Modules
     */
    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $disabled_plugins = $this->plugins->getDisabledModules();

        $relevant_plugins = [];
        foreach ($disabled_plugins as $module_id => $module) {
            $cause = $this->plugins->getDisabledCause($module_id);
            if ($cause === Plugins::DISABLED_EXPLICIT) {
                $relevant_plugins[$module_id] = $module;
            } else {
                $io->writeln(
                    sprintf(
                        'Plugin "%s" is not explicitly disabled (%s)',
                        $module_id,
                        $this->getDisplayCause($cause)
                    ),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }

        return $relevant_plugins;
    }
}
