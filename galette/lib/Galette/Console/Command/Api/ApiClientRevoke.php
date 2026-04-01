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

use Galette\Api\Repository\ApiTokenRepository;
use Galette\Console\Command\AbstractCommand;
use Galette\Entity\ApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Revoke all tokens of an API OAuth2 client command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'api:client:revoke',
    description: 'Revoke all active refresh tokens for an OAuth2 API client'
)]
class ApiClientRevoke extends AbstractCommand
{
    /**
     * Configure command arguments
     */
    protected function configure(): void
    {
        $this->addArgument('client_id', InputArgument::REQUIRED, 'Client identifier');
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId = (string)$input->getArgument('client_id');

        $client = new ApiClient($clientId);
        if (!$client->isLoaded()) {
            $io->error(sprintf('No API client found with id "%s".', $clientId));
            return Command::FAILURE;
        }

        global $zdb;

        $repo = new ApiTokenRepository($zdb);
        $repo->revokeAllForClient($clientId);

        $io->success(sprintf('All refresh tokens for client "%s" have been revoked.', $clientId));

        return Command::SUCCESS;
    }
}
