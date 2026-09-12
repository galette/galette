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
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

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
