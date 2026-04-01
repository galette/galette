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

use Galette\Console\Command\Api\ApiClientCreate;
use Galette\Entity\ApiClient;
use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for api:client:create console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiClientCreateTest extends GaletteTestCase
{
    protected int $seed = 20260401050505;

    /** @var string[] IDs of clients created during tests that need manual cleanup */
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
     * Creating a new client succeeds and prints the secret
     */
    public function testCreateClientSuccess(): void
    {
        $clientId = 'test_create_' . $this->seed;
        $this->createdClientIds[] = $clientId;

        $tester = new CommandTester(new ApiClientCreate(''));
        $status = $tester->execute([
            'client_id'   => $clientId,
            'client_name' => 'Test Client',
        ]);

        $this->assertSame(Command::SUCCESS, $status);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('created successfully', $output);
        // Secret must appear in output (shown once)
        $this->assertMatchesRegularExpression('/[0-9a-f]{64}/', $output);

        // Verify the row is in the DB
        $client = new ApiClient($clientId);
        $this->assertTrue($client->isLoaded());
    }

    /**
     * Creating a client with --trusted sets the trusted flag
     */
    public function testCreateTrustedClient(): void
    {
        $clientId = 'test_trusted_' . $this->seed;
        $this->createdClientIds[] = $clientId;

        $tester = new CommandTester(new ApiClientCreate(''));
        $tester->execute([
            'client_id'   => $clientId,
            'client_name' => 'Trusted Client',
            '--trusted'   => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('trusted', strtolower($output));
    }

    /**
     * Creating a client with --secret uses the provided secret
     */
    public function testCreateClientWithExplicitSecret(): void
    {
        $clientId = 'test_secret_' . $this->seed;
        $this->createdClientIds[] = $clientId;
        $secret = 'my_explicit_secret_value';

        $tester = new CommandTester(new ApiClientCreate(''));
        $tester->execute([
            'client_id'    => $clientId,
            'client_name'  => 'Secret Client',
            '--secret'     => $secret,
        ]);

        $output = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString($secret, $output);
    }

    /**
     * Attempting to create a duplicate client_id returns FAILURE
     */
    public function testCreateDuplicateClientFails(): void
    {
        $clientId = 'test_dup_' . $this->seed;
        $this->createdClientIds[] = $clientId;

        $command = new ApiClientCreate('');

        // First creation — must succeed
        $tester = new CommandTester($command);
        $tester->execute([
            'client_id'   => $clientId,
            'client_name' => 'Original',
        ]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Second creation — must fail
        $tester2 = new CommandTester(new ApiClientCreate(''));
        $status = $tester2->execute([
            'client_id'   => $clientId,
            'client_name' => 'Duplicate',
        ]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('already exists', $tester2->getDisplay());
    }
}
