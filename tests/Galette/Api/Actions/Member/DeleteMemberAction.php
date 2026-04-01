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

use Galette\Api\Actions\Member\DeleteMemberAction;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for DELETE /api/v1/members/{id}
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DeleteMemberActionTest extends GaletteTestCase
{
    protected int $seed = 20260402080808;

    private DeleteMemberAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->action = $this->container->get(DeleteMemberAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a DELETE /members/{id} request
     */
    private function buildRequest(Login $login, int $id): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('DELETE', '/api/v1/members/' . $id)
            ->withAttribute('api_login', $login);
    }

    /**
     * Non-admin request returns 403
     */
    public function testNonAdminReturns403(): void
    {
        $member = $this->getMemberOne();
        $notLoggedIn = new Login($this->zdb, $this->i18n);

        $result = $this->action->__invoke(
            $this->buildRequest($notLoggedIn, $member->id),
            new Response(),
            ['id' => (string)$member->id]
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
            $this->buildRequest($this->login, PHP_INT_MAX),
            new Response(),
            ['id' => (string)PHP_INT_MAX]
        );

        $this->assertSame(404, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * Valid deletion returns 204 and the member no longer exists
     */
    public function testValidDeleteReturns204(): void
    {
        // Create a fresh member so GaletteTestCase tearDown doesn't try to delete a missing row
        $member = $this->getMemberTwo();
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, $member->id),
            new Response(),
            ['id' => (string)$member->id]
        );

        $this->assertSame(204, $result->getStatusCode());
    }
}
