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

namespace Galette\Tests\Api\Actions\Group;

use Galette\Api\Actions\Group\GetGroupAction;
use Galette\Core\Login;
use Galette\Entity\Group;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for GET /api/v1/groups/{id}
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GetGroupActionTest extends GaletteTestCase
{
    protected int $seed = 20260402131313;

    private GetGroupAction $action;
    private ServerRequestFactory $requestFactory;
    private Group $group;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->group = new Group();
        $this->group->setName('GetTest Group ' . $this->seed);
        $this->assertTrue($this->group->store(), 'Group could not be stored');

        $this->action = $this->container->get(GetGroupAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        if ($this->group->getId() !== null) {
            $this->group->remove(true);
        }
        parent::tearDown();
    }

    /**
     * Helper: build a GET /groups/{id} request
     */
    private function buildRequest(Login $login, int $id): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('GET', '/api/v1/groups/' . $id)
            ->withAttribute('api_login', $login);
    }

    /**
     * Unauthenticated request returns 403
     */
    public function testUnauthenticatedReturns403(): void
    {
        $notLoggedIn = new Login($this->zdb, $this->i18n);

        $result = $this->action->__invoke(
            $this->buildRequest($notLoggedIn, $this->group->getId()),
            new Response(),
            ['id' => (string)$this->group->getId()]
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Non-existent group returns 404
     */
    public function testUnknownGroupReturns404(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, PHP_INT_MAX),
            new Response(),
            ['id' => (string)PHP_INT_MAX]
        );

        $this->assertSame(404, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Staff user gets a group with members list embedded
     */
    public function testStaffGetsGroupWithMembers(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, $this->group->getId()),
            new Response(),
            ['id' => (string)$this->group->getId()]
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertSame($this->group->getId(), $payload['id']);
        $this->assertSame($this->group->getName(), $payload['name']);
        $this->assertArrayHasKey('members', $payload);
        $this->assertIsArray($payload['members']);
    }
}
