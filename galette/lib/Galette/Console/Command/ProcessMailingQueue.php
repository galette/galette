<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Core\Db;
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
        $delay = (int)$preferences->pref_mail_batch_delay;

        $sent = 0;
        $failed = 0;
        $rate_limited = false;

        //drain the queue batch after batch until it is empty or rate-limited
        do {
            $progress = $queue->processBatch();
            $sent += (int)$progress['batch_sent'];
            $failed += (int)$progress['batch_failed'];

            if ($progress['done'] === true) {
                break;
            }
            if ($progress['rate_limited'] === true) {
                $rate_limited = true;
                break;
            }
            if ($delay > 0) {
                sleep($delay);
            }
        } while (true);

        $this->io->success(
            sprintf('Mailing queue processed: %d sent, %d failed.', $sent, $failed)
        );
        if ($rate_limited) {
            $this->io->warning(
                'Sending rate limit reached, remaining messages will be sent on next runs.'
            );
        }

        return Command::SUCCESS;
    }
}
