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

namespace Galette\Tests\Api\Actions\Member;

use Galette\Api\Actions\Member\CreateMemberAction;
use Galette\Core\Login;
use Galette\Repository\Members;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for POST /api/v1/members
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CreateMemberActionTest extends GaletteTestCase
{
    protected int $seed = 20260402060606;

    private CreateMemberAction $action;
    private ServerRequestFactory $requestFactory;

    /** @var int[] IDs of members created during tests that need manual cleanup */
    private array $createdMemberIds = [];

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->action = $this->container->get(CreateMemberAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        if ($this->createdMemberIds !== []) {
            (new Members())->removeMembers($this->createdMemberIds);
        }
        parent::tearDown();
    }

    /**
     * Helper: build a POST /members request
     *
     * @param array<string, mixed> $body
     */
    private function buildRequest(Login $login, array $body): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('POST', '/api/v1/members')
            ->withAttribute('api_login', $login)
            ->withParsedBody($body);
    }

    /**
     * Unauthenticated / non-admin request returns 403
     */
    public function testNonAdminReturns403(): void
    {
        $notLoggedIn = new Login($this->zdb, $this->i18n);

        $result = $this->action->__invoke(
            $this->buildRequest($notLoggedIn, []),
            new Response(),
            []
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Empty body returns 422 (validation errors)
     */
    public function testEmptyBodyReturns422(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, []),
            new Response(),
            []
        );

        $this->assertSame(422, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('errors', $payload);
        $this->assertNotEmpty($payload['errors']);
    }

    /**
     * Valid body returns 201 with member data
     */
    public function testValidBodyReturns201(): void
    {
        $this->logSuperAdmin();

        $data = $this->dataAdherentOne();
        // Use a unique login to avoid conflicts with getMemberOne()
        $data['login_adh'] = 'create_test_' . $this->seed;
        $data['email_adh'] = 'create_' . $this->seed . '@example.com';

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, $data),
            new Response(),
            []
        );

        $this->assertSame(201, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('login', $payload);
        $this->assertIsInt($payload['id']);

        $this->createdMemberIds[] = $payload['id'];
    }
}
