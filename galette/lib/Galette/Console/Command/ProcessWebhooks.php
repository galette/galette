<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Webhooks\Dispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Send pending webhooks deliveries
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:webhooks:process',
    description: 'Send pending webhooks deliveries, and retry failed ones that are due'
)]
class ProcessWebhooks extends AbstractCommand
{
    /**
     * Configure command options
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'limit',
                shortcut: 'l',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Maximum number of deliveries to send',
                default: Dispatcher::BATCH_SIZE
            );
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        $limit = (int)$input->getOption('limit');
        if ($limit < 1) {
            $this->io->error('Limit must be a positive number.');
            return Command::INVALID;
        }

        $report = $container->get(Dispatcher::class)->process($limit);
        $this->io->success(
            sprintf(
                'Webhooks processed: %1$d delivered, %2$d to be retried, %3$d failed.',
                $report->getDelivered(),
                $report->getRetried(),
                $report->getFailed()
            )
        );

        return Command::SUCCESS;
    }
}
