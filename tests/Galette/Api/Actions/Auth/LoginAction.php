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

use Galette\Api\Actions\Auth\LoginAction;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for POST /api/v1/auth/login
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LoginActionTest extends GaletteTestCase
{
    protected int $seed = 20260401030303;

    private LoginAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->action = $this->container->get(LoginAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a POST /auth/login request
     *
     * @param array<string, string> $body
     */
    private function buildRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('POST', '/api/v1/auth/login')
            ->withParsedBody($body);
    }

    /**
     * Missing login field returns 400
     */
    public function testMissingLoginReturns400(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest(['password' => 'somepassword']),
            new Response(),
            []
        );

        $this->assertSame(400, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Missing password field returns 400
     */
    public function testMissingPasswordReturns400(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest(['login' => 'somelogin']),
            new Response(),
            []
        );

        $this->assertSame(400, $result->getStatusCode());
    }

    /**
     * Empty body returns 400
     */
    public function testEmptyBodyReturns400(): void
    {
        $result = $this->action->__invoke(
            $this->buildRequest([]),
            new Response(),
            []
        );

        $this->assertSame(400, $result->getStatusCode());
    }

    /**
     * Wrong credentials return 401
     */
    public function testInvalidCredentialsReturns401(): void
    {
        $this->getMemberOne(); // ensure at least one member exists

        $result = $this->action->__invoke(
            $this->buildRequest(['login' => 'nonexistent_user_xyz', 'password' => 'wrongpassword']),
            new Response(),
            []
        );

        $this->assertSame(401, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Valid credentials return 200 with access_token and refresh_token.
     * Skipped if the api_tokens table does not exist in the test database.
     */
    public function testValidCredentialsReturnsTokens(): void
    {
        // Check that the api_tokens table exists
        try {
            $select = $this->zdb->select('api_tokens');
            $select->limit(1);
            $this->zdb->execute($select);
        } catch (\Throwable $e) {
            $this->markTestSkipped('api_tokens table not present in test database.');
        }

        $member = $this->getMemberOne();
        $data = $this->dataAdherentOne();

        $result = $this->action->__invoke(
            $this->buildRequest([
                'login'    => $data['login_adh'],
                'password' => $data['mdp_adh'],
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
        $this->assertSame('Bearer', $payload['token_type']);
        $this->assertIsInt($payload['expires_in']);
        $this->assertNotEmpty($payload['access_token']);
        $this->assertNotEmpty($payload['refresh_token']);

        // Clean up the refresh token row (outside transaction scope since
        // createRefreshToken does its own insert without the test transaction)
        $delete = $this->zdb->delete('api_tokens');
        $delete->where(['id_adh' => $member->id]);
        $this->zdb->execute($delete);
    }
}
