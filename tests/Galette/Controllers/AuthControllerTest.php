<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Analog\Analog;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Galette authentication controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AuthControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20260820151200;

    /**
     * Unimpersonate grants super administrator rights back: it must be refused
     * from any session that is not actually impersonating someone.
     */
    public function testUnimpersonateRequiresImpersonatedSession(): void
    {
        $request = $this->createRequest('unimpersonate');

        //not logged-in: refused by authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //simple member: must not be able to gain super administrator rights
        $member = $this->createMember($this->dataAdherentOne());
        $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isSuperAdmin());
        $this->assertFalse($this->login->isImpersonated());

        $test_response = $this->app->handle($request);

        $this->assertSame(403, $test_response->getStatusCode());
        $this->assertFalse($this->login->isSuperAdmin());
        $this->expectLogEntry(Analog::WARNING, 'Trying to unimpersonate while not impersonating!');
        $this->login->logOut();

        //super administrator that is not impersonating: refused as well
        $this->logSuperAdmin();
        $this->assertFalse($this->login->isImpersonated());

        $test_response = $this->app->handle($request);

        $this->assertSame(403, $test_response->getStatusCode());
        $this->expectLogEntry(Analog::WARNING, 'Trying to unimpersonate while not impersonating!');
    }

    /**
     * The legitimate flow must keep working: impersonate, then unimpersonate
     * and get super administrator rights back.
     */
    public function testUnimpersonateFromImpersonatedSession(): void
    {
        $member = $this->createMember($this->dataAdherentOne());

        //impersonate through its own route, so the whole flow is exercised
        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->createRequest('impersonate', ['id' => (string)$member->id])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertTrue($this->session->login->isImpersonated());
        $this->assertFalse($this->session->login->isSuperAdmin());
        $this->flash_data = [];

        $test_response = $this->app->handle($this->createRequest('unimpersonate'));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertTrue($this->session->login->isSuperAdmin());
        $this->assertFalse($this->session->login->isImpersonated());
        $this->expectFlashData(['success_detected' => ['Impersonating ended']]);
    }
}
