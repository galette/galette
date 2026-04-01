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

namespace Galette\Tests\Console\Command\Api;

use Galette\Console\Command\Api\ApiClientList;
use Galette\Tests\GaletteTestCase;
use Safe\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for api:client:list console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiClientListTest extends GaletteTestCase
{
    protected int $seed = 20260401060606;

    /** @var string[] Client IDs to clean up after each test */
    private array $createdClientIds = [];

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();

        try {
            $probe = $this->zdb->select('api_client');
            $probe->limit(1);
            $this->zdb->execute($probe);
        } catch (\Throwable) {
            $this->markTestSkipped('api_client table not present in test database.');
        }
    }

    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        if ($this->createdClientIds !== []) {
            $delete = $this->zdb->delete('api_client');
            $delete->where->in('client_id', $this->createdClientIds);
            $this->zdb->execute($delete);
        }
        parent::tearDown();
    }

    /**
     * Helper — insert a minimal client row directly
     *
     * @param string $clientId Client identifier
     */
    private function insertClient(string $clientId): void
    {
        $insert = $this->zdb->insert('api_client');
        $insert->values([
            'client_id'          => $clientId,
            'client_secret_hash' => password_hash('secret', PASSWORD_BCRYPT),
            'client_name'        => 'Test ' . $clientId,
            'is_trusted'         => false,
            'created_at'         => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
        $this->zdb->execute($insert);
        $this->createdClientIds[] = $clientId;
    }

    /**
     * Empty table shows the "No API clients" message
     */
    public function testListEmptyShowsMessage(): void
    {
        // Ensure the table is empty for this test
        $existing = $this->zdb->select('api_client');
        if ($this->zdb->execute($existing)->count() > 0) {
            $this->markTestSkipped('api_client table has existing rows — cannot test empty state.');
        }

        $tester = new CommandTester(new ApiClientList(''));
        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('No API clients registered', $tester->getDisplay());
    }

    /**
     * Registered clients appear in the table output
     */
    public function testListShowsRegisteredClients(): void
    {
        $clientId = 'list_test_' . $this->seed;
        $this->insertClient($clientId);

        $tester = new CommandTester(new ApiClientList(''));
        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);

        $output = $tester->getDisplay();
        $this->assertStringContainsString($clientId, $output);
        $this->assertStringContainsString('Client ID', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Trusted', $output);
    }
}
