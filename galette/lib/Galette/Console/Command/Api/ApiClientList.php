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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * List API OAuth2 clients command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'api:client:list',
    description: 'List all registered OAuth2 API clients'
)]
class ApiClientList extends AbstractCommand
{
    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        global $zdb;

        $select  = $zdb->select(ApiClient::TABLE);
        $results = $zdb->execute($select);

        if ($results->count() === 0) {
            $io->info('No API clients registered.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($results as $row) {
            $rows[] = [
                (string)$row->client_id,
                (string)$row->client_name,
                $row->is_trusted ? 'Yes' : 'No',
                (string)$row->created_at,
            ];
        }

        $io->table(['Client ID', 'Name', 'Trusted', 'Created at'], $rows);

        return Command::SUCCESS;
    }
}
