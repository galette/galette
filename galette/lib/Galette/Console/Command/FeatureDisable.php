<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Analog\Analog;
use Galette\Core\FeatureFlagManager;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Turn off preview features
 *
 * Preview features are turned on from the advanced configuration page. Should
 * one of them break that page, or keep its users out, this is the way back
 * that does not go through the interface. Running this command requires access
 * to the server, which stands as the authentication here - just like the
 * installer.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:feature:disable',
    description: 'Turn off preview features turned on from the advanced configuration page'
)]
class FeatureDisable extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'flag',
                mode: InputArgument::OPTIONAL,
                description: 'Feature flag to turn off'
            )
            ->addOption(
                name: 'all',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Turn off every preview feature'
            )
            ->addOption(
                name: 'force',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Do not ask for confirmation (required to run unattended)'
            );
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        $preferences = $container->get(Preferences::class);
        $login = $container->get(Login::class);
        $flags = new FeatureFlagManager($preferences);

        $this->io->title('Turn off preview features');

        $flag = $input->getArgument('flag');
        $all = (bool)$input->getOption('all');
        if (($flag === null) === !$all) {
            $this->io->error('Give either a feature flag or --all.');
            return Command::FAILURE;
        }

        if ($all) {
            //whatever is stored goes, including flags that are no longer
            //preview features and are ignored anyway
            $removed = $preferences->getFeatureFlags();
            $remaining = [];
        } else {
            try {
                $change = $flags->computeTurnOff((string)$flag);
            } catch (\DomainException $e) {
                $this->io->error($e->getMessage());
                return Command::FAILURE;
            }
            $removed = $change['removed'];
            $remaining = $change['flags'];
        }

        if ($removed === []) {
            $this->io->warning('No such preview feature is turned on.');
            return Command::SUCCESS;
        }

        $this->io->text(sprintf('Features to turn off: <info>%s</info>', implode(', ', $removed)));
        if (!$this->confirm($input, 'Turn them off?')) {
            return Command::FAILURE;
        }

        if (!$preferences->storeFeatureFlags($remaining)) {
            $this->io->error('Preview features could not be stored.');
            return Command::FAILURE;
        }

        //history records who did it; access to the server stands for the
        //super administrator
        $login->logAdmin($preferences->pref_admin_login, $preferences, challenge: false);
        $container->get(History::class)->add(_T("Preview feature turned off"), implode(', ', $removed));
        Analog::log(
            sprintf('Preview features turned off from command line: %s', implode(', ', $removed)),
            Analog::INFO
        );

        $this->io->success('Preview features have been turned off.');
        return Command::SUCCESS;
    }

    /**
     * Ask for confirmation, unless --force has been passed
     *
     * @param InputInterface $input    Input
     * @param string         $question Question to ask
     */
    private function confirm(InputInterface $input, string $question): bool
    {
        if ($input->getOption('force')) {
            return true;
        }

        if (!$input->isInteractive()) {
            $this->io->error('Run this command from an interactive terminal to confirm, or pass --force.');
            return false;
        }

        if (!$this->io->confirm($question, default: false)) {
            $this->io->text('Nothing has been changed.');
            return false;
        }

        return true;
    }
}
