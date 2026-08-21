<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Analog\Analog;
use Galette\Core\AuthThrottle;
use Galette\Tests\GaletteRoutingTestCase;

use function Safe\session_id;

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
        $before_impersonate = session_id();
        $test_response = $this->app->handle(
            $this->createRequest('impersonate', ['id' => (string)$member->id])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertNotSame($before_impersonate, session_id());
        $this->assertTrue($this->session->login->isImpersonated());
        $this->assertFalse($this->session->login->isSuperAdmin());
        $this->flash_data = [];

        $before_unimpersonate = session_id();
        $test_response = $this->app->handle($this->createRequest('unimpersonate'));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertNotSame($before_unimpersonate, session_id());
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

    /**
     * The session id must change whenever the privilege level of the session
     * does, so that an identifier fixed before authentication cannot be reused.
     */
    public function testSessionIdIsRegeneratedOnLogin(): void
    {
        $member = $this->createMember($this->dataAdherentOne());

        $before = session_id();
        $this->assertNotEmpty($before);

        $request = $this->createRequest('dologin', [], 'POST')
            ->withParsedBody([
                'login' => $member->login,
                'password' => 'J^B-()f'
            ]);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertTrue($this->login->isLogged());
        $this->assertNotSame($before, session_id());
        $this->flash_data = [];
    }

    /**
     * A failed authentication must not rotate the session id: there is no
     * privilege change to protect, and rotating would let anyone reset it.
     */
    public function testSessionIdIsKeptOnFailedLogin(): void
    {
        $before = session_id();

        $request = $this->createRequest('dologin', [], 'POST')
            ->withParsedBody([
                'login' => 'does.not.exist',
                'password' => 'whatever'
            ]);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertFalse($this->login->isLogged());
        $this->assertSame($before, session_id());
        $this->expectLogEntry(Analog::WARNING, 'No entry found for login `does.not.exist`');
        $this->flash_data = [];
    }

    /**
     * Once a threshold is reached -- here the one counting an account and an
     * address together -- further attempts are refused before any credential
     * check, even one carrying the right password.
     */
    public function testFailedLoginIsThrottled(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $threshold = $this->preferences->pref_throttle_account_ip_attempts;

        for ($i = 0; $i < $threshold; $i++) {
            $test_response = $this->app->handle($this->loginRequest($member->login, 'wrong'));
            $this->assertSame(301, $test_response->getStatusCode());
            $this->expectFlashData(['error_detected' => ['Login failed.']]);
            $this->expectLogEntry(Analog::WARNING, 'Passwords mismatch for login');
        }

        //the right password no longer gets through
        $test_response = $this->app->handle($this->loginRequest($member->login, 'J^B-()f'));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertFalse($this->login->isLogged());
        $flash = $this->flash_data['slimFlash']['loginfault'][0] ?? '';
        $this->assertStringContainsString('Too many failed attempts', $flash);
        $this->flash_data = [];
    }

    /**
     * A successful authentication clears the account counter, so a member who
     * mistyped a few times is not dragging a delay behind them.
     */
    public function testSuccessfulLoginClearsThrottle(): void
    {
        $member = $this->createMember($this->dataAdherentOne());

        $this->app->handle($this->loginRequest($member->login, 'wrong'));
        $this->expectFlashData(['error_detected' => ['Login failed.']]);
        $this->expectLogEntry(Analog::WARNING, 'Passwords mismatch for login');

        $select = $this->zdb->select(\Galette\Core\AuthThrottle::TABLE);
        $select->where(
            [
                'scope' => \Galette\Core\AuthThrottle::SCOPE_ACCOUNT,
                'identifier' => $member->login
            ]
        );
        $this->assertSame(1, $this->zdb->execute($select)->count());

        $this->app->handle($this->loginRequest($member->login, 'J^B-()f'));
        $this->assertTrue($this->login->isLogged());

        $this->assertSame(0, $this->zdb->execute($select)->count());
        $this->flash_data = [];
    }

    /**
     * An unknown login must not be distinguishable from a wrong password
     */
    public function testUnknownLoginIsNotDistinguishable(): void
    {
        $member = $this->createMember($this->dataAdherentOne());

        $this->app->handle($this->loginRequest($member->login, 'wrong'));
        $known = $this->flash_data['slimFlash'];
        $this->flash_data = [];
        $this->expectLogEntry(Analog::WARNING, 'Passwords mismatch for login');

        $this->app->handle($this->loginRequest('no.such.login', 'wrong'));
        $unknown = $this->flash_data['slimFlash'];
        $this->flash_data = [];
        $this->expectLogEntry(Analog::WARNING, 'No entry found for login `no.such.login`');

        $this->assertSame($known, $unknown);
    }

    /**
     * Build a login request
     *
     * @param string $login    Login
     * @param string $password Password
     */
    private function loginRequest(string $login, string $password): \Slim\Psr7\Request
    {
        return $this->createRequest('dologin', [], 'POST')
            ->withParsedBody(['login' => $login, 'password' => $password]);
    }
    /**
     * The page listing what is refused is for administrators, and lifting a
     * counter from it lets the caller through at once
     */
    public function testAuthAttemptsPage(): void
    {
        $member = $this->createMember($this->dataAdherentOne());

        //not logged in
        $this->expectLogin($this->app->handle($this->createRequest('authAttempts')));

        //a member has no business reading logins and addresses
        $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));
        $this->expectAuthMiddlewareRefused($this->app->handle($this->createRequest('authAttempts')));
        $this->login->logOut();

        //lock the member out through the login form itself, from a session
        //that is not logged in -- posting a login while already logged in
        //takes another path entirely
        for ($i = 0; $i < $this->preferences->pref_throttle_account_ip_attempts; $i++) {
            $this->app->handle($this->loginRequest($member->login, 'wrong'));
            $this->expectFlashData(['error_detected' => ['Login failed.']]);
            $this->expectLogEntry(Analog::WARNING, 'Passwords mismatch for login');
        }

        $this->logSuperAdmin();

        $throttle = new AuthThrottle($this->zdb, $this->preferences, clean: false);
        $this->assertGreaterThan(0, $throttle->getRetryDelay($member->login));

        $test_response = $this->app->handle($this->createRequest('authAttempts'));
        $this->assertSame(200, $test_response->getStatusCode());

        $locks = $throttle->getLocks();
        $this->assertNotEmpty($locks);

        //lift them from the page, and the member is not made to wait anymore
        $request = $this->createRequest('doAuthAttempts', method: 'POST')
            ->withParsedBody(['release_all' => '1']);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(0, $throttle->getRetryDelay($member->login));
        $this->flash_data = [];
    }
    /**
     * The password recovery form answers the same thing whatever it was
     * asked: anything else is a way to find out which logins are real
     */
    public function testRecoveryAnswersTheSameForAnyLogin(): void
    {
        //the form is only reachable when mail is enabled. Set in memory, not
        //stored: storing commits the transaction the test runs in, and the
        //history entries below would outlive it
        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_PHPMAIL;

        //a member without a usable address, so that no mail is ever attempted
        $data = $this->dataAdherentOne();
        $data['email_adh'] = '';
        $member = $this->createMember($data);
        $this->assertSame('', $member->email);

        $answers = [];
        foreach ([$member->login, 'no.such.member' . $this->seed] as $login) {
            $request = $this->createRequest('retrieve-pass', method: 'POST')
                ->withParsedBody(['login' => $login]);
            $test_response = $this->app->handle($request);

            $this->assertSame(301, $test_response->getStatusCode());
            $answers[] = $this->flash_data['slimFlash'] ?? [];
            $this->flash_data = [];
        }

        //same wording, same kind of message, in both cases
        $this->assertSame($answers[0], $answers[1]);
        $this->assertArrayHasKey('success_detected', $answers[0]);
        $this->assertStringContainsString('If an account matches', $answers[0]['success_detected'][0]);

        //what actually happened is in the history, for staff to read
        $select = $this->zdb->select(\Galette\Core\History::TABLE);
        $select->where->like('action_log', '%' . $member->login . '%');
        $this->assertGreaterThan(0, $this->zdb->execute($select)->count());

        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_DISABLED;
    }
}
