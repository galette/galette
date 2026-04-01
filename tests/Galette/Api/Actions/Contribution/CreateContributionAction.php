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

use Galette\Api\Actions\Contribution\CreateContributionAction;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for POST /api/v1/contributions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CreateContributionActionTest extends GaletteTestCase
{
    protected int $seed = 20260402111111;

    private CreateContributionAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->getMemberOne();
        $this->action = $this->container->get(CreateContributionAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a POST /contributions request
     *
     * @param array<string, mixed> $body
     */
    private function buildRequest(Login $login, array $body): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('POST', '/api/v1/contributions')
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
            $this->buildRequest($notLoggedIn, []),
            new Response(),
            []
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Empty body returns 422
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
    }

    /**
     * Valid body returns 201 with contribution data
     */
    public function testValidBodyReturns201(): void
    {
        $this->logSuperAdmin();

        $data = $this->getContribData();

        $result = $this->action->__invoke(
            $this->buildRequest($this->login, $data),
            new Response(),
            []
        );

        $this->assertSame(201, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('id_adh', $payload);
        $this->assertArrayHasKey('amount', $payload);
        $this->assertSame($this->adh->id, $payload['id_adh']);
    }
}
