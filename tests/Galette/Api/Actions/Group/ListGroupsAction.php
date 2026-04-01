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

use Galette\Api\Actions\Group\ListGroupsAction;
use Galette\Core\Login;
use Galette\Entity\Group;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for GET /api/v1/groups
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListGroupsActionTest extends GaletteTestCase
{
    protected int $seed = 20260402121212;

    private ListGroupsAction $action;
    private ServerRequestFactory $requestFactory;
    private Group $group;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->group = new Group();
        $this->group->setName('ListTest Group ' . $this->seed);
        $this->assertTrue($this->group->store(), 'Group could not be stored');

        $this->action = $this->container->get(ListGroupsAction::class);
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
     * Helper: build a GET /groups request
     */
    private function buildRequest(Login $login): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('GET', '/api/v1/groups')
            ->withAttribute('api_login', $login);
    }

    /**
     * Unauthenticated request returns 403
     */
    public function testUnauthenticatedReturns403(): void
    {
        $notLoggedIn = new Login($this->zdb, $this->i18n);

        $result = $this->action->__invoke(
            $this->buildRequest($notLoggedIn),
            new Response(),
            []
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Staff user gets groups list wrapped in a data key
     */
    public function testStaffGetsGroupsList(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login),
            new Response(),
            []
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsArray($payload['data']);
        $this->assertGreaterThanOrEqual(1, count($payload['data']));

        // Each item must have the expected keys
        $item = $payload['data'][0];
        foreach (['id', 'name', 'parent_id', 'member_count'] as $key) {
            $this->assertArrayHasKey($key, $item, "Missing key: $key");
        }
    }
}
