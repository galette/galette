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

use Galette\Api\Actions\Member\GetMemberAction;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function Safe\json_decode;

/**
 * Tests for GET /api/v1/members/{id}
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GetMemberActionTest extends GaletteTestCase
{
    protected int $seed = 20260401020202;

    private GetMemberAction $action;
    private ServerRequestFactory $requestFactory;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->action = $this->container->get(GetMemberAction::class);
        $this->requestFactory = new ServerRequestFactory();
    }

    /**
     * Helper: build a GET request for /members/{id} with a given Login
     */
    private function buildRequest(int $id, Login $login): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestFactory
            ->createServerRequest('GET', '/api/v1/members/' . $id)
            ->withAttribute('api_login', $login);
    }

    /**
     * Staff user can retrieve any member — returns 200 with full data
     */
    public function testStaffGetsExistingMember(): void
    {
        $member = $this->getMemberOne();
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest($member->id, $this->login),
            new Response(),
            ['id' => (string)$member->id]
        );

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));

        $payload = json_decode((string)$result->getBody(), true);
        $this->assertSame($member->id, $payload['id']);
        $this->assertSame($member->email, $payload['email']);
        $this->assertArrayHasKey('login', $payload);
        $this->assertArrayHasKey('active', $payload);
    }

    /**
     * Requesting a non-existent member returns 404
     */
    public function testReturns404ForUnknownMember(): void
    {
        $this->logSuperAdmin();

        $result = $this->action->__invoke(
            $this->buildRequest(PHP_INT_MAX, $this->login),
            new Response(),
            ['id' => (string)PHP_INT_MAX]
        );

        $this->assertSame(404, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('error', $payload);
    }

    /**
     * A non-authenticated request (login not logged in) returns 403,
     * unless the ID matches the current user's ID.
     */
    public function testReturns403WhenNotAuthenticated(): void
    {
        $member = $this->getMemberOne();
        // Fresh Login instance: isLogged() === false
        $notLoggedIn = new Login($this->zdb, $this->i18n);

        $result = $this->action->__invoke(
            $this->buildRequest($member->id, $notLoggedIn),
            new Response(),
            ['id' => (string)$member->id]
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * A regular (non-staff) member cannot access another member's profile — 403
     */
    public function testRegularMemberCannotAccessOtherProfile(): void
    {
        $memberOne = $this->getMemberOne();
        $memberTwo = $this->getMemberTwo();

        // Log in as memberTwo (non-staff, non-admin)
        $regularLogin = $this->getMockBuilder(Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();
        $regularLogin->method('isLogged')->willReturn(true);
        $regularLogin->method('isStaff')->willReturn(false);
        $regularLogin->method('isAdmin')->willReturn(false);
        $regularLogin->method('isSuperAdmin')->willReturn(false);
        // Set id to memberTwo's id so it's accessing memberOne's profile (not own)
        $idProp = new \ReflectionProperty(Login::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($regularLogin, $memberTwo->id);

        $result = $this->action->__invoke(
            $this->buildRequest($memberOne->id, $regularLogin),
            new Response(),
            ['id' => (string)$memberOne->id]
        );

        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * A member accessing their own profile (isOwnProfile) returns 200
     */
    public function testMemberCanAccessOwnProfile(): void
    {
        $member = $this->getMemberOne();

        $selfLogin = $this->getMockBuilder(Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();
        $selfLogin->method('isLogged')->willReturn(true);
        $selfLogin->method('isStaff')->willReturn(false);
        $selfLogin->method('isAdmin')->willReturn(false);
        $selfLogin->method('isSuperAdmin')->willReturn(false);
        $idProp = new \ReflectionProperty(Login::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($selfLogin, $member->id);

        $result = $this->action->__invoke(
            $this->buildRequest($member->id, $selfLogin),
            new Response(),
            ['id' => (string)$member->id]
        );

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string)$result->getBody(), true);
        $this->assertSame($member->id, $payload['id']);
    }
}
