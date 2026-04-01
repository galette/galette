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

use Galette\Api\Actions\Member\UpdateMemberAction;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for PUT /api/v1/members/{id}
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpdateMemberActionTest extends GaletteTestCase
{
    protected int $seed = 20260402070707;

    private UpdateMemberAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->getMemberOne();
        $this->action = $this->container->get(UpdateMemberAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a PUT /members/{id} request
     *
     * @param array<string, mixed> $body
     */
    private function buildRequest(Login $login, int $id, array $body): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('PUT', '/api/v1/members/' . $id)
            ->withAttribute('api_login', $login)
            ->withParsedBody($body);
    }

    /**
     * Non-admin request returns 403
     */
    public function testNonAdminReturns403(): void
    {
        $notLoggedIn = new Login($this->zdb, $this->i18n);

        $result = $this->action->__invoke(
            $this->buildRequest($notLoggedIn, $this->adh->id, []),
            new Response(),
            ['id' => (string)$this->adh->id]
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Non-existent member returns 404
     */
    public function testUnknownMemberReturns404(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, PHP_INT_MAX, []),
            new Response(),
            ['id' => (string)PHP_INT_MAX]
        );

        $this->assertSame(404, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Invalid body returns 422
     */
    public function testInvalidBodyReturns422(): void
    {
        $this->logSuperAdmin();

        // Pass an empty body — Adherent::check() will report missing required fields
        $result = $this->action->__invoke(
            $this->buildRequest($this->login, $this->adh->id, []),
            new Response(),
            ['id' => (string)$this->adh->id]
        );

        $this->assertSame(422, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('errors', $payload);
    }

    /**
     * Valid update returns 200 with updated data
     */
    public function testValidUpdateReturns200(): void
    {
        $this->logSuperAdmin();

        $data = $this->dataAdherentOne();
        $data['ville_adh'] = 'UpdatedCity_' . $this->seed;

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, $this->adh->id, $data),
            new Response(),
            ['id' => (string)$this->adh->id]
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertSame($this->adh->id, $payload['id']);
        $this->assertSame('UpdatedCity_' . $this->seed, $payload['town']);
    }
}
