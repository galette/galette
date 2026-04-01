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

use Galette\Api\Actions\Auth\TokenAction;
use Galette\Tests\GaletteTestCase;
use Safe\DateTime;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for POST /api/v1/auth/token (client_credentials)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TokenActionTest extends GaletteTestCase
{
    protected int $seed = 20260402030303;

    private TokenAction $action;
    private ServerRequestFactory $requestFactory;

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

        $this->action = $this->container->get(TokenAction::class);
        $this->requestFactory = new ServerRequestFactory();
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
     * Helper: build a POST /auth/token request
     *
     * @param array<string, string> $body
     */
    private function buildRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('POST', '/api/v1/auth/token')
            ->withParsedBody($body);
    }

    /**
     * Helper: insert a minimal client row directly
     */
    private function insertClient(string $clientId, string $secret): void
    {
        $insert = $this->zdb->insert('api_client');
        $insert->values([
            'client_id'          => $clientId,
            'client_secret_hash' => password_hash($secret, PASSWORD_BCRYPT),
            'client_name'        => 'Test ' . $clientId,
            'is_trusted'         => false,
            'created_at'         => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
        $this->zdb->execute($insert);
        $this->createdClientIds[] = $clientId;
    }

    /**
     * Wrong grant_type returns 400
     */
    public function testWrongGrantTypeReturns400(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest(['grant_type' => 'password', 'client_id' => 'x', 'client_secret' => 'y']),
            new Response(),
            []
        );

        $this->assertSame(400, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Missing client_id or client_secret returns 400
     */
    public function testMissingCredentialsReturns400(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest(['grant_type' => 'client_credentials']),
            new Response(),
            []
        );

        $this->assertSame(400, $result->getStatusCode());
    }

    /**
     * Invalid client secret returns 401
     */
    public function testInvalidSecretReturns401(): void
    {
        $clientId = 'token_test_bad_' . $this->seed;
        $this->insertClient($clientId, 'correct_secret');

        $result = $this->action->__invoke(
            $this->buildRequest([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => 'wrong_secret',
            ]),
            new Response(),
            []
        );

        $this->assertSame(401, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Unknown client_id returns 401
     */
    public function testUnknownClientReturns401(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest([
                'grant_type'    => 'client_credentials',
                'client_id'     => 'nonexistent_client_xyz',
                'client_secret' => 'any',
            ]),
            new Response(),
            []
        );

        $this->assertSame(401, $result->getStatusCode());
    }

    /**
     * Valid client credentials return 200 with an access token
     */
    public function testValidCredentialsReturnsAccessToken(): void
    {
        $clientId = 'token_test_ok_' . $this->seed;
        $secret = 'valid_secret_' . $this->seed;
        $this->insertClient($clientId, $secret);

        $result = $this->action->__invoke(
            $this->buildRequest([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $secret,
            ]),
            new Response(),
            []
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('access_token', $payload);
        $this->assertArrayHasKey('token_type', $payload);
        $this->assertArrayHasKey('expires_in', $payload);
        $this->assertSame('Bearer', $payload['token_type']);
        $this->assertIsInt($payload['expires_in']);
        $this->assertNotEmpty($payload['access_token']);
    }
}
