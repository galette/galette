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

namespace Galette\Tests\Api\Actions\Contribution;

use Galette\Api\Actions\Contribution\ListContributionsAction;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for GET /api/v1/contributions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListContributionsActionTest extends GaletteTestCase
{
    protected int $seed = 20260402090909;

    private ListContributionsAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->getMemberOne();
        $this->createContribution();
        $this->action = $this->container->get(ListContributionsAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a GET /contributions request
     *
     * @param array<string, string> $query
     */
    private function buildRequest(Login $login, array $query = []): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('GET', '/api/v1/contributions')
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
     * Staff user gets contributions list with pagination envelope
     */
    public function testStaffGetsListWithPagination(): void
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
        $this->assertArrayHasKey('pagination', $payload);
        $this->assertIsArray($payload['data']);
        $this->assertGreaterThanOrEqual(1, count($payload['data']));

        // Each item must have the expected keys
        $item = $payload['data'][0];
        foreach (['id', 'id_adh', 'id_type', 'amount', 'date', 'begin_date', 'end_date', 'payment', 'info'] as $key) {
            $this->assertArrayHasKey($key, $item, "Missing key: $key");
        }
    }

    /**
     * Filter by id_adh restricts results to that member
     */
    public function testFilterByMemberId(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, ['id_adh' => (string)$this->adh->id]),
            new Response(),
            []
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        foreach ($payload['data'] as $item) {
            $this->assertSame($this->adh->id, $item['id_adh']);
        }
    }
}
