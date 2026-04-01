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

use Galette\Api\Repository\ApiTokenRepository;
use Galette\Console\Command\Api\ApiClientRevoke;
use Galette\Tests\GaletteTestCase;
use Safe\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for api:client:revoke console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiClientRevokeTest extends GaletteTestCase
{
    protected int $seed = 20260401070707;

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

        try {
            $probe = $this->zdb->select('api_tokens');
            $probe->limit(1);
            $this->zdb->execute($probe);
        } catch (\Throwable) {
            $this->markTestSkipped('api_tokens table not present in test database.');
        }
    }

    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        if ($this->createdClientIds !== []) {
            $deleteTokens = $this->zdb->delete('api_tokens');
            $deleteTokens->where->in('client_id', $this->createdClientIds);
            $this->zdb->execute($deleteTokens);

            $deleteClients = $this->zdb->delete('api_client');
            $deleteClients->where->in('client_id', $this->createdClientIds);
            $this->zdb->execute($deleteClients);
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
     * Revoking tokens for an existing client succeeds
     */
    public function testRevokeExistingClientSuccess(): void
    {
        $clientId = 'revoke_test_' . $this->seed;
        $this->insertClient($clientId);

        // Create two active refresh tokens for this client
        $repo = new ApiTokenRepository($this->zdb);
        $repo->createRefreshToken(null, $clientId, 'raw_token_a_' . $this->seed, [], 3600);
        $repo->createRefreshToken(null, $clientId, 'raw_token_b_' . $this->seed, [], 3600);

        $tester = new CommandTester(new ApiClientRevoke(''));
        $status = $tester->execute(['client_id' => $clientId]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('revoked', $tester->getDisplay());

        // Tokens must now be invalid
        $this->assertNull($repo->verifyAndRotate('raw_token_a_' . $this->seed, $clientId));
        $this->assertNull($repo->verifyAndRotate('raw_token_b_' . $this->seed, $clientId));
    }

    /**
     * Revoking a non-existent client returns FAILURE
     */
    public function testRevokeUnknownClientFails(): void
    {
        $tester = new CommandTester(new ApiClientRevoke(''));
        $status = $tester->execute(['client_id' => 'nonexistent_client_xyz']);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    /**
     * Revoking a client with no tokens still succeeds (idempotent)
     */
    public function testRevokeClientWithNoTokensSucceeds(): void
    {
        $clientId = 'revoke_empty_' . $this->seed;
        $this->insertClient($clientId);

        $tester = new CommandTester(new ApiClientRevoke(''));
        $status = $tester->execute(['client_id' => $clientId]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('revoked', $tester->getDisplay());
    }
}
