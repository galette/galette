<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Core\Galette;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Change super administrator password console command
 *
 * The super administrator has no member row: its credentials live in the
 * preferences, and every existing way to change them requires being logged in
 * already. Running this command requires access to the server, which stands as
 * the authentication here - just like the installer.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:superadmin:password',
    description: 'Change the super administrator password'
)]
class SuperAdminPassword extends AbstractCommand
{
    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * @var Preferences $preferences
         * @var Login $login
         */
        global $preferences, $login;

        $this->io->title('Change the super administrator password');

        if (Galette::isDemo()) {
            $this->io->error('Super administrator password cannot be changed in demo mode.');
            return Command::FAILURE;
        }

        //the password is only ever read from a hidden prompt, so that it does
        //not end up in the shell history nor in the process list
        if (!$input->isInteractive()) {
            $this->io->error('This command requires an interactive terminal.');
            return Command::FAILURE;
        }

        $this->io->text(
            sprintf(
                'Super administrator login: <info>%s</info>',
                $preferences->pref_admin_login
            )
        );

        $password = $this->io->askHidden(
            'New password',
            function (?string $password): string {
                if ($password === null || trim($password) === '') {
                    throw new \RuntimeException('Super administrator password cannot be empty.');
                }
                return $password;
            }
        );

        $confirmation = $this->io->askHidden('Confirm new password');

        //Preferences::checkPasswordConfirmation() only runs on a full form
        //payload, which a single preference change is not
        if ($password !== $confirmation) {
            $this->io->error('Passwords mismatch');
            return Command::FAILURE;
        }

        //pref_admin_pass is restricted to the super administrator
        $login->logAdmin($preferences->pref_admin_login, $preferences);

        if (!$preferences->setValue('pref_admin_pass', $password, $login)) {
            $this->io->error(
                array_merge(
                    ['Super administrator password has not been changed:'],
                    $preferences->getErrors()
                )
            );
            return Command::FAILURE;
        }

        $this->io->success('Super administrator password has been changed.');
        return Command::SUCCESS;
    }
}
