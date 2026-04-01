<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Console\Command\Api;

use Galette\Console\Command\AbstractCommand;
use Galette\Entity\ApiClient;
use Safe\DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Create API OAuth2 client command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'api:client:create',
    description: 'Register a new OAuth2 API client'
)]
class ApiClientCreate extends AbstractCommand
{
    /**
     * Configure command arguments and options
     */
    protected function configure(): void
    {
        $this
            ->addArgument('client_id', InputArgument::REQUIRED, 'Unique client identifier (e.g. my_app)')
            ->addArgument('client_name', InputArgument::REQUIRED, 'Human-readable client name')
            ->addOption(
                'secret',
                null,
                InputOption::VALUE_REQUIRED,
                'Client secret (auto-generated if omitted)'
            )
            ->addOption(
                'trusted',
                null,
                InputOption::VALUE_NONE,
                'Grant admin-level access to this client'
            );
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId   = (string)$input->getArgument('client_id');
        $clientName = (string)$input->getArgument('client_name');
        $secret     = $input->getOption('secret') !== null
            ? (string)$input->getOption('secret')
            : bin2hex(random_bytes(32));
        $trusted = (bool)$input->getOption('trusted');

        // Ensure the client_id is not already taken
        $existing = new ApiClient($clientId);
        if ($existing->isLoaded()) {
            $io->error(sprintf('An API client with id "%s" already exists.', $clientId));
            return Command::FAILURE;
        }

        $client = new ApiClient();
        $client
            ->setClientId($clientId)
            ->setClientName($clientName)
            ->setClientSecret($secret)
            ->setTrusted($trusted)
            ->setCreatedAt(new DateTime());

        try {
            $client->save();
        } catch (Throwable $e) {
            $io->error('Failed to create client: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('API client "%s" created successfully.', $clientId));
        $io->comment('Client secret (shown once — store it in a safe place):');
        $io->writeln('  ' . $secret);
        if ($trusted) {
            $io->note('This client has been granted admin-level (trusted) access.');
        }

        return Command::SUCCESS;
    }
}
