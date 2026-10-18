<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Analog\Analog;
use Galette\Core\AuthThrottle;
use Galette\Core\Db;
use Galette\Core\Galette;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Core\TwoFactorAuth;
use Galette\Core\TwoFactorSecret;
use Galette\Core\TwoFactorStore;
use Galette\Core\TwoFactorSuperAdmin;
use Galette\Entity\Adherent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reset two-factor authentication
 *
 * The super administrator has no recovery codes and nobody above it to reset
 * its second factor from the interface: losing its device used to mean writing
 * preferences in database by hand. Running this command requires access to the
 * server, which stands as the authentication here - just like the installer.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:twofactor:reset',
    description: 'Reset two-factor authentication of the super administrator or of a member'
)]
class TwoFactorReset extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'login',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: 'Login of the member to reset; the super administrator when omitted'
            )
            ->addOption(
                name: 'clock',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Keep the second factor, only forget the last used code'
                    . ' (after the server clock went backwards)'
            )
            ->addOption(
                name: 'policy-off',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Disable two-factor authentication for the whole instance;'
                    . ' second factors are kept'
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
        $zdb = $container->get(Db::class);

        $this->io->title('Reset two-factor authentication');

        if (Galette::isDemo()) {
            $this->io->error('Two-factor authentication cannot be reset in demo mode.');
            return Command::FAILURE;
        }

        $member_login = $input->getOption('login');
        $policy_off = (bool)$input->getOption('policy-off');
        if ($policy_off && ($member_login !== null || $input->getOption('clock'))) {
            $this->io->error('--policy-off cannot be combined with --login nor --clock.');
            return Command::FAILURE;
        }

        if ($policy_off) {
            return $this->disablePolicy($preferences, $login, $input);
        }

        if ($member_login !== null) {
            //login only: Adherent::loadFromLoginOrMail() also matches an email address
            $select = $zdb->select(Adherent::TABLE);
            $select->columns([Adherent::PK])->where(['login_adh' => $member_login])->limit(1);
            $results = $zdb->execute($select);
            if ($results->count() === 0) {
                $this->io->error(sprintf('No member with login "%s".', $member_login));
                return Command::FAILURE;
            }
            $member = new Adherent($zdb, (int)$results->current()->{Adherent::PK});
            $store = new TwoFactorSecret($zdb);
            $store->load($member->id);
            $owner = $member->sname;
            $throttled = $member->login;
        } else {
            $store = new TwoFactorSuperAdmin($preferences);
            $owner = $preferences->pref_admin_login;
            $throttled = $preferences->pref_admin_login;
        }

        $this->io->text(sprintf('Account: <info>%s</info>', $owner));

        if (!$store->isLoaded()) {
            $this->io->warning('This account has no two-factor authentication.');
            return Command::SUCCESS;
        }

        $clock = (bool)$input->getOption('clock');
        $question = $clock
            ? 'Forget the last code used by this account?'
            : 'Remove the second factor of this account? It will log in with its password alone.';
        if (!$this->confirm($input, $question)) {
            return Command::FAILURE;
        }

        //history records who did it; access to the server stands for the
        //super administrator, second factor included
        $login->logAdmin($preferences->pref_admin_login, $preferences, challenge: false);

        if ($clock) {
            $this->forget($store, $owner);
        } else {
            $this->remove($store, $owner, $container->get(History::class));
        }

        //whatever locked the account out at the second factor no longer stands
        (new AuthThrottle($zdb, $preferences))->clearEvent(AuthThrottle::SCOPE_SECOND_FACTOR, $throttled);

        $this->io->success(
            $clock
                ? 'The last code used has been forgotten; the next valid one will be accepted.'
                : 'Two-factor authentication has been reset.'
        );
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

    /**
     * Forget the last used code
     *
     * @param TwoFactorStore $store Second factor
     * @param string         $owner Account name, for logs
     */
    private function forget(TwoFactorStore $store, string $owner): void
    {
        $store->forgetTimeslice();
        Analog::log(
            sprintf('Last two-factor time slice forgotten for %s from command line', $owner),
            Analog::INFO
        );
    }

    /**
     * Remove the second factor
     *
     * @param TwoFactorStore $store   Second factor
     * @param string         $owner   Account name, for logs
     * @param History        $history History
     */
    private function remove(TwoFactorStore $store, string $owner, History $history): void
    {
        $store->remove();
        $history->add(
            sprintf(
                //TRANS: %1$s is the member name
                _T('Two-factor authentication reset for %1$s'),
                $owner
            )
        );
        Analog::log(
            sprintf('Two-factor authentication reset for %s from command line', $owner),
            Analog::INFO
        );
    }

    /**
     * Disable two-factor authentication for the whole instance
     *
     * @param Preferences    $preferences Preferences
     * @param Login          $login       Login
     * @param InputInterface $input       Input
     */
    private function disablePolicy(Preferences $preferences, Login $login, InputInterface $input): int
    {
        if (TwoFactorAuth::modeFrom($preferences) === TwoFactorAuth::MODE_DISABLED) {
            $this->io->warning('Two-factor authentication is already disabled.');
            return Command::SUCCESS;
        }

        if (
            !$this->confirm(
                $input,
                'Disable two-factor authentication for everyone? Members keep their second factor,'
                . ' and are asked for it again once a policy is set.'
            )
        ) {
            return Command::FAILURE;
        }

        $login->logAdmin($preferences->pref_admin_login, $preferences, challenge: false);
        if (!$preferences->setValue('pref_2fa_mode', TwoFactorAuth::MODE_DISABLED, $login)) {
            $this->io->error(
                array_merge(
                    ['Two-factor authentication has not been disabled:'],
                    $preferences->getErrors()
                )
            );
            return Command::FAILURE;
        }

        Analog::log('Two-factor authentication disabled from command line', Analog::INFO);
        $this->io->success('Two-factor authentication has been disabled.');
        return Command::SUCCESS;
    }
}
