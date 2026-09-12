<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\MailingQueue;
use Galette\Core\Preferences;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Process the pending mass mailing queue.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:mailing:process-queue',
    description: 'Process the pending mass mailing queue, respecting configured limits'
)]
class ProcessMailingQueue extends AbstractCommand
{
    /**
     * Configure command options
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'force',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Send without asking for confirmation (required to run unattended)'
            );
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        //spreading a sending over time is still an alpha feature: nothing goes
        //out of this command until somebody says so, or asks for it explicitly
        if (!$input->getOption('force')) {
            $this->io->warning(
                'Spreading a sending over time is an alpha feature: it works, but it has'
                . ' seen little use. Watch what actually reaches your members, and report'
                . ' anything odd.'
            );

            if (!$input->isInteractive()) {
                $this->io->error(
                    'Run this command from an interactive terminal to confirm,'
                    . ' or pass --force to run it unattended.'
                );
                return Command::FAILURE;
            }

            if (!$this->io->confirm('Process the pending queue now?', default: false)) {
                $this->io->text('Nothing was sent.');
                return Command::SUCCESS;
            }
        }

        $zdb = $container->get(Db::class);
        $preferences = $container->get(Preferences::class);
        $queue = new MailingQueue($zdb, $preferences);
        //the queue is shared: it may hold reminders, which need their own
        //collaborators to be rendered and audited
        $queue->setReminderContext(
            $container->get(History::class),
            $container->get(Login::class)
        );
        $result = $queue->drain();

        $this->io->success(
            sprintf(
                'Mailing queue processed: %d sent, %d failed.',
                $result['sent'],
                $result['failed']
            )
        );
        if ($result['rate_limited']) {
            $this->io->warning(
                'Sending rate limit reached, remaining messages will be sent on next runs.'
            );
        }

        return Command::SUCCESS;
    }
}
