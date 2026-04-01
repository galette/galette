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

use Galette\Api\Actions\Member\ListMembersAction;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for GET /api/v1/members
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListMembersActionTest extends GaletteTestCase
{
    protected int $seed = 20260402050505;

    private ListMembersAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->getMemberOne();
        $this->action = $this->container->get(ListMembersAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a GET /members request with optional query params
     *
     * @param array<string, string> $query
     */
    private function buildRequest(Login $login, array $query = []): \Psr\Http\Message\ServerRequestInterface
    {
        $uri = '/api/v1/members';
        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }
        return $this->requestFactory
            ->createServerRequest('GET', $uri)
            ->withAttribute('api_login', $login)
            ->withQueryParams($query);
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
     * Staff / superadmin can list members — returns 200 with data + pagination
     */
    public function testStaffGetsListWithPagination(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, ['page' => '1', 'per_page' => '10']),
            new Response(),
            []
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('pagination', $payload);
        $this->assertArrayHasKey('page', $payload['pagination']);
        $this->assertArrayHasKey('per_page', $payload['pagination']);
        $this->assertArrayHasKey('total', $payload['pagination']);
        $this->assertIsArray($payload['data']);
        $this->assertGreaterThanOrEqual(1, count($payload['data']));
    }

    /**
     * per_page is capped at 100
     */
    public function testPerPageCappedAt100(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, ['per_page' => '9999']),
            new Response(),
            []
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertSame(100, $payload['pagination']['per_page']);
    }
}
