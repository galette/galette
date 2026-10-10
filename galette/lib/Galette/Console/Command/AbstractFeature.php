<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Analog\Analog;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Turn preview features on or off from the command line
 *
 * Preview features are turned on from the advanced configuration page; these
 * commands do the same without it, and are the way back should a feature break
 * that page or keep its users out. Running them requires access to the server,
 * which stands as the authentication here - just like the installer.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractFeature extends AbstractCommand
{
    /**
     * Add the option every feature command takes
     */
    protected function addForceOption(): void
    {
        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::VALUE_NONE,
            description: 'Do not ask for confirmation (required to run unattended)'
        );
    }

    /**
     * Ask for confirmation, unless --force has been passed
     *
     * @param InputInterface $input    Input
     * @param string         $question Question to ask
     */
    protected function confirm(InputInterface $input, string $question): bool
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

    /**
     * Store the preview features turned on, and record the change
     *
     * @param array<string> $flags   Every preview feature to keep turned on
     * @param array<string> $changed Features turned on or off by this command
     * @param bool          $turn_on Whether they were turned on
     */
    protected function store(array $flags, array $changed, bool $turn_on): int
    {
        global $container;

        $preferences = $container->get(Preferences::class);
        if (!$preferences->storeFeatureFlags($flags)) {
            $this->io->error('Preview features could not be stored.');
            return Command::FAILURE;
        }

        //history records who did it; access to the server stands for the
        //super administrator
        $container->get(Login::class)->logAdmin($preferences->pref_admin_login, $preferences, challenge: false);
        $container->get(History::class)->add(
            $turn_on ? _T("Preview feature turned on") : _T("Preview feature turned off"),
            implode(', ', $changed)
        );
        Analog::log(
            sprintf(
                'Preview features turned %s from command line: %s',
                $turn_on ? 'on' : 'off',
                implode(', ', $changed)
            ),
            Analog::INFO
        );

        $this->io->success($turn_on ? 'Preview features have been turned on.' : 'Preview features have been turned off.');
        return Command::SUCCESS;
    }
}
