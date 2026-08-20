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

    /**
     * The super administrator password lives in preferences and used to accept a
     * bare md5 hash as a fallback. Only password_verify() is allowed now.
     */
    public function testSuperAdminLegacyMd5PasswordIsRejected(): void
    {
        $password = 'Sup3r-P@ss!2026';
        $this->setAdminPass(md5($password));

        $test_response = $this->app->handle($this->createLoginRequest($password));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('login')]],
            $test_response->getHeaders()
        );
        $this->assertFalse($this->login->isLogged());
        $this->expectFlashData(['error_detected' => ['Login failed.']]);

        //a properly hashed password still works
        $this->setAdminPass(password_hash($password, PASSWORD_BCRYPT));

        $test_response = $this->app->handle($this->createLoginRequest($password));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertTrue($this->login->isLogged());
        $this->assertTrue($this->login->isSuperAdmin());
        $this->flash_data = [];
    }

    /**
     * Store a raw value as super administrator password, bypassing
     * Preferences::__set() which would hash it.
     */
    private function setAdminPass(string $hash): void
    {
        $update = $this->zdb->update(\Galette\Core\Preferences::TABLE);
        $update->set(['val_pref' => $hash])
            ->where(['nom_pref' => 'pref_admin_pass']);
        $this->zdb->execute($update);
        $this->preferences->load();
    }

    /**
     * Build a super administrator login request
     */
    private function createLoginRequest(string $password): \Slim\Psr7\Request
    {
        return $this->createRequest('dologin', [], 'POST')
            ->withParsedBody([
                'login' => $this->preferences->pref_admin_login,
                'password' => $password
            ]);
    }

    /**
     * The password recovery link must carry a token, and the value stored in
     * database must not be usable as a link on its own.
     */
    public function testPasswordRecoveryUsesADedicatedToken(): void
    {
        $member = $this->createMember($this->dataAdherentOne());

        $password = new \Galette\Core\Password($this->zdb);
        $this->assertTrue($password->generateNewPassword($member->id));
        $token = $password->getToken();
        $stored = $password->getHash();
        $this->assertNotSame($token, $stored);

        //the stored value must not open the recovery page
        $test_response = $this->app->handle(
            $this->createRequest('password-recovery', ['hash' => $stored])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('password-lost')]],
            $test_response->getHeaders()
        );
        $this->flash_data = [];

        //the token does
        $test_response = $this->app->handle(
            $this->createRequest('password-recovery', ['hash' => $token])
        );
        $this->assertSame(200, $test_response->getStatusCode());

        //and it lets the member set a new password, once
        $newpass = 'Rec0very-P@ss!2026';
        $request = $this->createRequest('do-password-recovery', [], 'POST')
            ->withParsedBody([
                'hash' => $token,
                'mdp_adh' => $newpass,
                'mdp_adh2' => $newpass
            ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Your password has been changed!']]);

        $stored_member = new \Galette\Entity\Adherent($this->zdb, $member->id);
        $this->assertTrue(password_verify($newpass, (string)$stored_member->password));

        //the token has been consumed
        $this->assertFalse($password->isTokenValid($token));
    }
}
