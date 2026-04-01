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

namespace Galette\Tests\Api\Actions\Auth;

use Galette\Api\Actions\Auth\RefreshAction;
use Galette\Api\Repository\ApiTokenRepository;
use Galette\Tests\GaletteTestCase;
use Safe\DateTime;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for POST /api/v1/auth/refresh (token rotation)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RefreshActionTest extends GaletteTestCase
{
    protected int $seed = 20260402040404;

    private RefreshAction $action;
    private ServerRequestFactory $requestFactory;
    private ApiTokenRepository $tokenRepo;

    /** @var string[] Client IDs to clean up */
    private array $createdClientIds = [];

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();

        try {
            $probe = $this->zdb->select('api_tokens');
            $probe->limit(1);
            $this->zdb->execute($probe);
        } catch (\Throwable) {
            $this->markTestSkipped('api_tokens table not present in test database.');
        }

        try {
            $probe = $this->zdb->select('api_client');
            $probe->limit(1);
            $this->zdb->execute($probe);
        } catch (\Throwable) {
            $this->markTestSkipped('api_client table not present in test database.');
        }

        $this->action = $this->container->get(RefreshAction::class);
        $this->requestFactory = new ServerRequestFactory();
        $this->tokenRepo = new ApiTokenRepository($this->zdb);
    }

    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        $deleteTokens = $this->zdb->delete('api_tokens');
        $deleteTokens->where->like('token_hash', '%refresh_action_test%');
        $this->zdb->execute($deleteTokens);

        if ($this->createdClientIds !== []) {
            $deleteClients = $this->zdb->delete('api_client');
            $deleteClients->where->in('client_id', $this->createdClientIds);
            $this->zdb->execute($deleteClients);
        }
        parent::tearDown();
    }

    /**
     * Helper: build a POST /auth/refresh request
     *
     * @param array<string, mixed> $body
     */
    private function buildRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('POST', '/api/v1/auth/refresh')
            ->withParsedBody($body);
    }

    /**
     * Helper: insert a minimal client row
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
     * Missing refresh_token returns 400
     */
    public function testMissingRefreshTokenReturns400(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest([]),
            new Response(),
            []
        );

        $this->assertSame(400, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Unknown / invalid refresh token returns 401
     */
    public function testInvalidRefreshTokenReturns401(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest(['refresh_token' => 'totally_unknown_token_xyz']),
            new Response(),
            []
        );

        $this->assertSame(401, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Valid client refresh token returns new access + refresh tokens
     */
    public function testValidClientRefreshTokenRotates(): void
    {
        $clientId = 'refresh_action_client_' . $this->seed;
        $this->insertClient($clientId);

        $rawToken = 'refresh_action_test_' . $this->seed;
        $this->tokenRepo->createRefreshToken(null, $clientId, $rawToken, [], 3600);

        $result = $this->action->__invoke(
            $this->buildRequest([
                'refresh_token' => $rawToken,
                'client_id'     => $clientId,
            ]),
            new Response(),
            []
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('access_token', $payload);
        $this->assertArrayHasKey('refresh_token', $payload);
        $this->assertArrayHasKey('token_type', $payload);
        $this->assertArrayHasKey('expires_in', $payload);
        $this->assertNotEmpty($payload['access_token']);
        $this->assertNotEmpty($payload['refresh_token']);

        // Old token must be revoked — cannot rotate again
        $rotated = $this->tokenRepo->verifyAndRotate($rawToken, $clientId);
        $this->assertNull($rotated);
    }
}
