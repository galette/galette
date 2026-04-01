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

use Galette\Api\Actions\Contribution\GetContributionAction;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for GET /api/v1/contributions/{id}
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GetContributionActionTest extends GaletteTestCase
{
    protected int $seed = 20260402101010;

    private GetContributionAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->getMemberOne();
        $this->createContribution();
        $this->action = $this->container->get(GetContributionAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a GET /contributions/{id} request
     */
    private function buildRequest(Login $login, int $id): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('GET', '/api/v1/contributions/' . $id)
            ->withAttribute('api_login', $login);
    }

    /**
     * Unauthenticated request returns 403
     */
    public function testUnauthenticatedReturns403(): void
    {
        $notLoggedIn = new Login($this->zdb, $this->i18n);

        $result = $this->action->__invoke(
            $this->buildRequest($notLoggedIn, $this->contrib->id),
            new Response(),
            ['id' => (string)$this->contrib->id]
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Non-existent contribution returns 404
     */
    public function testUnknownContributionReturns404(): void
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
     * Staff user can retrieve an existing contribution — returns 200 with all fields
     */
    public function testStaffGetsExistingContribution(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, $this->contrib->id),
            new Response(),
            ['id' => (string)$this->contrib->id]
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertSame($this->contrib->id, $payload['id']);
        $this->assertSame($this->adh->id, $payload['id_adh']);
        $this->assertArrayHasKey('amount', $payload);
        $this->assertArrayHasKey('date', $payload);
        $this->assertArrayHasKey('begin_date', $payload);
        $this->assertArrayHasKey('end_date', $payload);
    }
}
