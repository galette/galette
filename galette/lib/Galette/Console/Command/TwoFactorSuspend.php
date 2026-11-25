<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Core\Galette;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Core\TwoFactorAuth;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Suspend two-factor authentication for the whole instance
 *
 * For when nobody can produce a code the server accepts: its clock drifted,
 * an update broke the authenticator library. Members log in with their
 * password alone until the suspension ends; their second factor stays in
 * place and is asked for again afterwards, nobody has to enrol anew - which is
 * what galette:twofactor:reset, account by account, would mean.
 *
 * The suspension always ends on its own, so it cannot be forgotten switched
 * on; it never touches the policy the instance chose. Running this command
 * requires access to the server, which stands as the authentication here.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:twofactor:suspend',
    description: 'Stop asking for a second factor for a few hours, on the whole instance'
)]
class TwoFactorSuspend extends AbstractCommand
{
    /** Default length of a suspension, in hours */
    public const int DEFAULT_HOURS = 24;

    #[Inject]
    protected Preferences $preferences;
    #[Inject]
    protected Login $login;
    #[Inject]
    protected History $history;

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'hours',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: sprintf(
                    'How long the suspension lasts, from 1 to %d hours',
                    TwoFactorAuth::SUSPEND_MAX_HOURS
                ),
                default: self::DEFAULT_HOURS
            )
            ->addOption(
                name: 'resume',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'End the running suspension now'
            );
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $preferences = $this->preferences;

        $this->io->title('Suspend two-factor authentication');

        if (Galette::isDemo()) {
            $this->io->error('Two-factor authentication cannot be suspended in demo mode.');
            return Command::FAILURE;
        }

        $resume = (bool)$input->getOption('resume');
        $hours = $input->getOption('hours');
        if (!$resume && (!is_numeric($hours) || (string)(int)$hours !== (string)$hours)) {
            $this->io->error('--hours expects a whole number of hours.');
            return Command::FAILURE;
        }
        $hours = (int)$hours;
        if (!$resume && ($hours < 1 || $hours > TwoFactorAuth::SUSPEND_MAX_HOURS)) {
            $this->io->error(sprintf(
                'A suspension lasts from 1 to %d hours; run the command again if it is not enough.',
                TwoFactorAuth::SUSPEND_MAX_HOURS
            ));
            return Command::FAILURE;
        }

        //history records who did it; access to the server stands for the
        //super administrator, second factor included
        $this->login->logAdmin($preferences->pref_admin_login, $preferences, challenge: false);

        if ($resume) {
            if (TwoFactorAuth::suspendedUntil($preferences) === null) {
                $this->io->text('Two-factor authentication is not suspended, nothing has been changed.');
                return Command::SUCCESS;
            }
            if (!$preferences->updateTwoFactorSuspension(0)) {
                $this->io->error('The suspension could not be ended.');
                return Command::FAILURE;
            }
            Analog::log('Two-factor authentication resumed from command line', Analog::INFO);
            $this->record(_T('Two-factor authentication resumed'));
            $this->io->success('Two-factor authentication is asked for again.');
            return Command::SUCCESS;
        }

        $until = time() + $hours * 3600;
        if (!$preferences->updateTwoFactorSuspension($until)) {
            $this->io->error('Two-factor authentication could not be suspended.');
            return Command::FAILURE;
        }

        $end = date('Y-m-d H:i:s', $until);
        Analog::log(
            sprintf('Two-factor authentication suspended from command line until %s', $end),
            Analog::INFO
        );
        $this->record(
            sprintf(
                //TRANS: %1$s is a date and time
                _T('Two-factor authentication suspended until %1$s'),
                $end
            )
        );

        $this->io->success(sprintf(
            'Two-factor authentication is suspended until %s: members log in with their password alone.',
            $end
        ));
        $this->io->text('Run this command again with --resume to end it sooner.');
        return Command::SUCCESS;
    }

    /**
     * Record the change in history
     *
     * The change is saved by then: a history that cannot be written must not
     * make the command report a failure the instance does not live by.
     *
     * @param string $action Entry to add
     */
    private function record(string $action): void
    {
        try {
            $this->history->add($action);
        } catch (Throwable) {
            // no log: History::add() has logged it before rethrowing
            $this->io->warning('The change is in force, but could not be recorded in the history.');
        }
    }
}
