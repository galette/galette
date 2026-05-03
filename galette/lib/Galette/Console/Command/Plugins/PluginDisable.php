<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
     * @return array<string, array<string, string>>
     */
    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        return $this->plugins->getModules();
    }
}
