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
     * Impersonating must not stop at a challenge: the second factor identifies
     * the operator, and the target's is neither theirs to produce nor the way
     * back -- unimpersonate sits behind the authentication middleware
     */
    public function testImpersonateIgnoresTheTargetSecondFactor(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $this->enrol($member->id);

        $this->logSuperAdmin();

        //the super administrator holds one too, so both ends are covered: the
        //session is already past it, being logged in
        $superadmin = new \Galette\Core\TwoFactorSuperAdmin($preferences);
        $superadmin->create('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $superadmin->enable();

        $test_response = $this->app->handle(
            $this->createRequest('impersonate', ['id' => (string)$member->id])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertTrue($this->session->login->isImpersonated());
        $this->assertTrue($this->session->login->isLogged());
        $this->assertFalse($this->session->login->isTwoFactorPending());
        $this->flash_data = [];

        //and no challenge on the way back either
        $test_response = $this->app->handle($this->createRequest('unimpersonate'));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertTrue($this->session->login->isSuperAdmin());
        $this->assertTrue($this->session->login->isLogged());
        $this->assertFalse($this->session->login->isTwoFactorPending());
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
     * An enrolled member is sent to the challenge, and stays locked out of
     * everything until they produce a code. This is the test that matters: the
     * intermediate state must be inert.
     */
    public function testEnrolledMemberIsHeldAtTheChallenge(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $secret = $this->enrol($member->id);

        $test_response = $this->app->handle($this->loginRequest($member->login, 'J^B-()f'));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('two-factor')]],
            $test_response->getHeaders()
        );
        $this->assertFalse($this->login->isLogged());
        $this->assertTrue($this->login->isTwoFactorPending());

        //nothing behind the middleware opens up while the factor is owed. The
        //assertion is on the refusal itself, not merely on "not 200": an
        //unrelated 500 would satisfy that and prove nothing.
        $test_response = $this->app->handle($this->createRequest('me'));
        $this->expectLogin($test_response);

        //the challenge itself is reachable, it has to be
        $test_response = $this->app->handle($this->createRequest('two-factor'));
        $this->assertSame(200, $test_response->getStatusCode());

        //a wrong code keeps the door shut
        $test_response = $this->app->handle($this->challengeRequest('000000'));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertFalse($this->login->isLogged());
        $this->expectFlashData(['error_detected' => ['Two-factor authentication failed.']]);

        //the right one opens it, and rotates the session id as a login does
        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);
        $before = session_id();
        $test_response = $this->app->handle($this->challengeRequest($tfa->getCodeAt($secret->getSecret())));

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isTwoFactorPending());
        $this->assertNotSame($before, session_id());
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
     * A recovery code gets a member in who no longer has their device, and
     * works once
     */
    public function testRecoveryCodeGetsThroughTheChallenge(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $secret = $this->enrol($member->id);
        $codes = $secret->generateRecoveryCodes();

        $this->app->handle($this->loginRequest($member->login, 'J^B-()f'));
        $this->assertTrue($this->login->isTwoFactorPending());

        $this->app->handle($this->challengeRequest($codes[0]));
        $this->assertTrue($this->login->isLogged());
        $this->flash_data = [];

        //and that code is spent
        $reloaded = new \Galette\Core\TwoFactorSecret($this->zdb);
        $reloaded->load($member->id);
        $this->assertSame(count($codes) - 1, $reloaded->countRemainingCodes());
    }

    /**
     * With nothing pending, the challenge is not a page anyone can sit on
     */
    public function testChallengeNeedsAPendingSession(): void
    {
        foreach (['two-factor'] as $route) {
            $test_response = $this->app->handle($this->createRequest($route));
            $this->assertSame(301, $test_response->getStatusCode());
            $this->assertSame(
                ['Location' => [$this->routeparser->urlFor('slash')]],
                $test_response->getHeaders()
            );
        }

        $test_response = $this->app->handle($this->challengeRequest('000000'));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
    }

    /**
     * Enrol a member with a known secret
     *
     * @param int $id_adh Member identifier
     */
    private function enrol(int $id_adh): \Galette\Core\TwoFactorSecret
    {
        $secret = new \Galette\Core\TwoFactorSecret($this->zdb);
        $secret->create($id_adh, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $secret->enable();
        return $secret;
    }

    /**
     * Name of the cookie silencing the invitation for an account
     *
     * @param int $id_adh Member identifier, 0 for the super administrator
     */
    private function suggestionCookie(int $id_adh): string
    {
        return \Galette\Controllers\AuthController::TFA_SUGGESTION_COOKIE . '_' . $id_adh;
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

    /**
     * Build a second factor challenge request
     *
     * @param string $code Code to submit
     */
    private function challengeRequest(string $code): \Slim\Psr7\Request
    {
        return $this->createRequest('do-two-factor', [], 'POST')
            ->withParsedBody(['code' => $code]);
    }

    /**
     * A member enrols on their own: the page hands out a secret, the code they
     * read back from their application turns it on, and recovery codes are
     * shown once
     */
    public function testMemberCanEnrolOnTheirOwn(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));

        //nothing enabled yet
        $test_response = $this->app->handle($this->createRequest('two-factor-manage'));
        $this->assertSame(200, $test_response->getStatusCode());

        //opening the enrolment page stores a pending, unusable secret
        $test_response = $this->app->handle($this->createRequest('two-factor-enrol'));
        $this->assertSame(200, $test_response->getStatusCode());

        $secret = new \Galette\Core\TwoFactorSecret($this->zdb);
        $this->assertTrue($secret->load($member->id));
        $this->assertFalse($secret->isEnabled());
        $pending = $secret->getSecret();

        //reloading must not hand out a different one, or a scanned QR would break
        $this->app->handle($this->createRequest('two-factor-enrol'));
        $secret->load($member->id);
        $this->assertSame($pending, $secret->getSecret());

        //a wrong code leaves it off
        $test_response = $this->app->handle($this->enrolRequest('000000'));
        $this->assertSame(301, $test_response->getStatusCode());
        $secret->load($member->id);
        $this->assertFalse($secret->isEnabled());
        $this->flash_data = [];

        //the right one turns it on and shows the recovery codes
        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);
        $test_response = $this->app->handle($this->enrolRequest($tfa->getCodeAt($pending)));

        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('tfa_recovery_codes', $body);
        $this->assertMatchesRegularExpression('/<code>[0-9A-F]{5}-[0-9A-F]{5}<\/code>/', $body);

        $secret->load($member->id);
        $this->assertTrue($secret->isEnabled());
        $this->assertSame(\Galette\Core\TwoFactorSecret::CODES_COUNT, $secret->countRemainingCodes());
        $this->flash_data = [];
    }

    /**
     * Turning it off needs a code: a hijacked session must not be able to
     * remove the very thing standing in its way
     */
    public function testDisablingNeedsAProof(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $secret = $this->enrol($member->id);
        $this->login->logIn($member->login, 'J^B-()f');
        $this->login->validateTwoFactor();

        //no code, or a wrong one, changes nothing
        foreach (['', '000000'] as $code) {
            $this->app->handle($this->postTo('do-two-factor-disable', $code));
            $reloaded = new \Galette\Core\TwoFactorSecret($this->zdb);
            $this->assertTrue($reloaded->load($member->id));
            $this->assertTrue($reloaded->isEnabled());
            $this->flash_data = [];
        }

        //a valid code does
        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);
        $this->app->handle($this->postTo('do-two-factor-disable', $tfa->getCodeAt($secret->getSecret())));

        $reloaded = new \Galette\Core\TwoFactorSecret($this->zdb);
        $this->assertFalse($reloaded->load($member->id));
        $this->flash_data = [];
    }

    /**
     * Renewing recovery codes voids the previous set
     */
    public function testRenewingCodesVoidsThePreviousOnes(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $secret = $this->enrol($member->id);
        $old = $secret->generateRecoveryCodes();
        $this->login->logIn($member->login, 'J^B-()f');
        $this->login->validateTwoFactor();

        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);
        $test_response = $this->app->handle(
            $this->postTo('do-two-factor-codes', $tfa->getCodeAt($secret->getSecret()))
        );

        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('tfa_recovery_codes', (string)$test_response->getBody());

        $reloaded = new \Galette\Core\TwoFactorSecret($this->zdb);
        $reloaded->load($member->id);
        $this->assertSame(\Galette\Core\TwoFactorSecret::CODES_COUNT, $reloaded->countRemainingCodes());
        $this->assertFalse($reloaded->consumeRecoveryCode($old[0]));
        $this->flash_data = [];
    }

    /**
     * Management is behind authentication, like any account page
     */
    public function testManagementNeedsToBeLoggedIn(): void
    {
        foreach (['two-factor-manage', 'two-factor-enrol'] as $route) {
            $test_response = $this->app->handle($this->createRequest($route));
            $this->expectLogin($test_response);
        }
    }

    /**
     * The super administrator manages its own second factor like anyone else,
     * but nothing may be written to the member tables on its behalf: it has no
     * row there, and the foreign key would reject one keyed on its id of 0.
     */
    public function testSuperAdminUsesThePreferenceStore(): void
    {
        global $preferences;

        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $this->logSuperAdmin();

        foreach (['two-factor-manage', 'two-factor-enrol'] as $route) {
            $test_response = $this->app->handle($this->createRequest($route));
            $this->assertSame(200, $test_response->getStatusCode(), $route);
            $this->flash_data = [];
        }

        //the secret went to the preferences
        $store = new \Galette\Core\TwoFactorSuperAdmin($preferences);
        $this->assertTrue($store->isLoaded());

        //and not a single row to the member tables
        $this->assertSame(0, $this->zdb->execute($this->zdb->select(\Galette\Core\TwoFactorSecret::TABLE))->count());
        $this->assertSame(
            0,
            $this->zdb->execute($this->zdb->select(\Galette\Core\TwoFactorSecret::CODES_TABLE))->count()
        );
    }

    /**
     * Build an enrolment confirmation request
     *
     * @param string $code Code to submit
     */
    private function enrolRequest(string $code): \Slim\Psr7\Request
    {
        return $this->postTo('do-two-factor-enrol', $code);
    }

    /**
     * Build a POST carrying a code
     *
     * @param string $route Route name
     * @param string $code  Code to submit
     */
    private function postTo(string $route, string $code): \Slim\Psr7\Request
    {
        return $this->createRequest($route, [], 'POST')
            ->withParsedBody(['code' => $code]);
    }

    /**
     * When the policy requires a second factor, a member without one is sent to
     * enrolment and kept there until they comply
     */
    public function testRequiredPolicyForcesEnrolment(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_REQUIRED_ALL;
        $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));
        //no factor owed yet: none is enrolled, so credentials alone log in
        $this->assertTrue($this->login->isLogged());

        //'me' renders for a member when nothing stands in the way, so a
        //redirect here really is the middleware acting
        $test_response = $this->app->handle($this->createRequest('me'));
        $this->assertSame(302, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('two-factor-enrol')]],
            $test_response->getHeaders()
        );
        $this->flash_data = [];

        //enrolment itself, and the way out, stay reachable
        foreach (['two-factor-enrol', 'two-factor-manage'] as $route) {
            $test_response = $this->app->handle($this->createRequest($route));
            $this->assertSame(200, $test_response->getStatusCode(), $route);
            $this->flash_data = [];
        }

        //once enrolled, it opens up again
        $this->enrol($member->id);
        $test_response = $this->app->handle($this->createRequest('me'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->flash_data = [];
    }

    /**
     * The super administrator is held to the policy like anyone else, and the
     * enrolment it is sent to must actually work for it -- otherwise the two
     * pages bounce it back and forth and the one account that must always get
     * in is locked out
     */
    public function testSuperAdminIsSentToAWorkingEnrolment(): void
    {
        global $preferences;

        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_REQUIRED_ALL;
        $this->logSuperAdmin();

        //diverted, since it holds none
        $test_response = $this->app->handle($this->createRequest('preferences'));
        $this->assertSame(302, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('two-factor-enrol')]],
            $test_response->getHeaders()
        );
        $this->flash_data = [];

        //and the page it lands on renders rather than bouncing it back
        $test_response = $this->app->handle($this->createRequest('two-factor-enrol'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->flash_data = [];

        $store = new \Galette\Core\TwoFactorSuperAdmin($preferences);
        $this->assertTrue($store->isLoaded());
        $this->assertFalse($store->isEnabled());

        //confirming turns it on, without recovery codes: it gets a redirect
        //rather than the codes page
        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);
        $test_response = $this->app->handle($this->postTo('do-two-factor-enrol', $tfa->getCodeAt($store->getSecret())));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertTrue($store->isEnabled());
        $this->flash_data = [];

        //and the way is clear again
        $test_response = $this->app->handle($this->createRequest('preferences'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->flash_data = [];
    }

    /**
     * Once enrolled, the super administrator is asked for its code at login,
     * even though it has no member row
     */
    public function testSuperAdminIsChallengedAtLogin(): void
    {
        global $preferences;

        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $store = new \Galette\Core\TwoFactorSuperAdmin($preferences);
        $store->create('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $store->enable();

        $test_response = $this->app->handle(
            $this->loginRequest($preferences->pref_admin_login, 'admin')
        );

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('two-factor')]],
            $test_response->getHeaders()
        );
        $this->assertFalse($this->login->isLogged());
        $this->assertTrue($this->login->isTwoFactorPending());
        $this->flash_data = [];

        //a wrong code keeps it out
        $this->app->handle($this->postTo('do-two-factor', '000000'));
        $this->assertFalse($this->login->isLogged());
        $this->flash_data = [];

        //the right one lets it in, and the slice is remembered against replay
        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);
        $code = $tfa->getCodeAt($store->getSecret());
        $this->app->handle($this->postTo('do-two-factor', $code));

        $this->assertTrue($this->login->isLogged());
        $this->assertTrue($this->login->isSuperAdmin());
        $this->assertNotNull($store->getLastTimeslice());
        $this->flash_data = [];
    }

    /**
     * Every account holding no second factor is invited to enrol, since
     * nothing makes it any more -- but only when there is something to enrol
     * into, and only until it says otherwise
     */
    public function testAccountsAreInvitedToEnrol(): void
    {
        global $preferences;

        $admin_data = $this->dataAdherentOne();
        $admin_data['bool_admin_adh'] = true;
        $admin = $this->createMember($admin_data);
        $this->login->logOut();
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;

        $this->app->handle($this->loginRequest($admin->login, 'J^B-()f'));
        $this->assertTrue($this->login->isLogged());
        //the invitation carries the name of the cookie that silences it, for
        //this account alone
        $this->expectFlashData(['suggest_two_factor' => [$this->suggestionCookie($admin->id)]]);
        $this->login->logOut();

        //a plain member too: their own file is their civil status, their
        //address and their contributions
        $member = $this->createMember($this->dataAdherentTwo());
        $this->app->handle($this->loginRequest($member->login, 'T.u!IbKOi|06'));
        $this->assertTrue($this->login->isLogged());
        $this->expectFlashData(['suggest_two_factor' => [$this->suggestionCookie($member->id)]]);
        $this->login->logOut();

        //and the super administrator, whose store is not the members' one. Its
        //fixture password is one the rules reject, so it also gets told about
        //that: only the invitation is of interest here
        $this->app->handle($this->loginRequest($preferences->pref_admin_login, 'admin'));
        $this->assertTrue($this->login->isSuperAdmin());
        $this->assertSame(
            [$this->suggestionCookie(0)],
            $this->flash_data['slimFlash']['suggest_two_factor'] ?? []
        );
        $this->flash_data = [];
        $this->login->logOut();

        //nothing to suggest while the instance does not use it
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_DISABLED;
        $this->app->handle($this->loginRequest($admin->login, 'J^B-()f'));
        $this->expectFlashData([]);
        $this->login->logOut();

        //nor when the policy already requires it: that is the middleware's job
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_REQUIRED_STAFF;
        $this->app->handle($this->loginRequest($admin->login, 'J^B-()f'));
        $this->expectFlashData([]);
        $this->login->logOut();

        //nor once they have said they do not want to hear about it -- and that
        //answer is theirs alone: on a shared computer, the next account to log
        //in is still invited
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $cookie = $this->suggestionCookie($admin->id);
        $_COOKIE[$cookie] = '1';
        try {
            $this->app->handle($this->loginRequest($admin->login, 'J^B-()f'));
            $this->expectFlashData([]);
            $this->login->logOut();

            $this->app->handle($this->loginRequest($member->login, 'T.u!IbKOi|06'));
            $this->expectFlashData(['suggest_two_factor' => [$this->suggestionCookie($member->id)]]);
        } finally {
            unset($_COOKIE[$cookie]);
        }
        $this->login->logOut();

        //and an account already holding one is not asked twice: it is held at
        //the challenge instead
        $this->enrol($admin->id);
        $this->app->handle($this->loginRequest($admin->login, 'J^B-()f'));
        $this->assertTrue($this->login->isTwoFactorPending());
        $this->expectFlashData([]);
    }

    /**
     * Without the flag, a stored mandatory policy drives nobody to enrolment:
     * that middleware is the one that can put a whole association outside its
     * own instance
     */
    public function testMandatoryPolicyDoesNotDivertWithoutTheFlag(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_REQUIRED_ALL;
        $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));
        $this->assertTrue($this->login->isLogged());

        //with the flag, an unenrolled member cannot go anywhere else
        $test_response = $this->app->handle($this->createRequest('me'));
        $this->assertSame(302, $test_response->getStatusCode());
        $this->flash_data = [];

        try {
            \Galette\Core\TwoFactorAuth::forceRequiredAvailable(available: false);

            $test_response = $this->app->handle($this->createRequest('me'));
            $this->assertSame(200, $test_response->getStatusCode());
        } finally {
            \Galette\Core\TwoFactorAuth::forceRequiredAvailable(available: true);
        }

        $this->login->logOut();
        $this->flash_data = [];
    }

    /**
     * The secret must not reach the preferences page, which collects every
     * single preference into its template data
     */
    public function testSuperAdminSecretIsNotHandedToTheTemplate(): void
    {
        global $preferences;

        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;

        //logged in first: logSuperAdmin() asserts isLogged(), which an enabled
        //second factor legitimately makes false
        $this->logSuperAdmin();
        $store = new \Galette\Core\TwoFactorSuperAdmin($preferences);
        $store->create('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $store->enable();

        $test_response = $this->app->handle($this->createRequest('preferences'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringNotContainsString(
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            (string)$test_response->getBody()
        );
        $this->flash_data = [];
    }

    /**
     * Staff clear the second factor of a member who lost both their device and
     * their recovery codes; a plain member cannot
     */
    public function testStaffCanResetAMemberSecondFactor(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $this->enrol($member->id);

        //a plain member is refused
        $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));
        $this->login->validateTwoFactor();
        $test_response = $this->app->handle(
            $this->createRequest('do-two-factor-reset', ['id' => (string)$member->id], 'POST')
        );
        $this->expectAuthMiddlewareRefused($test_response);

        $reloaded = new \Galette\Core\TwoFactorSecret($this->zdb);
        $this->assertTrue($reloaded->load($member->id));
        $this->login->logOut();

        //the super administrator is
        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->createRequest('do-two-factor-reset', ['id' => (string)$member->id], 'POST')
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertFalse($reloaded->load($member->id));
        $this->flash_data = [];
    }

    /**
     * Resetting somebody else's factor only ever goes downwards: one's own goes
     * through the page that asks for a code, and a peer's not at all
     */
    public function testResetOnlyGoesDownwards(): void
    {
        global $preferences;

        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;

        $admin_data = $this->dataAdherentOne();
        $admin_data['bool_admin_adh'] = true;
        $admin = $this->createMember($admin_data);
        $this->enrol($admin->id);

        $this->logSuperAdmin();

        //an administrator resetting an administrator is refused, itself included
        $this->login->logOut();
        $this->assertTrue($this->login->logIn($admin->login, 'J^B-()f'));
        $this->login->validateTwoFactor();

        $test_response = $this->app->handle(
            $this->createRequest('do-two-factor-reset', ['id' => (string)$admin->id], 'POST')
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $flash = $this->flash_data['slimFlash']['error_detected'][0] ?? '';
        $this->assertStringContainsString('own two-factor authentication page', $flash);
        $this->flash_data = [];
        $this->expectLogEntry(Analog::WARNING, 'Refused a second factor reset');

        $still_there = new \Galette\Core\TwoFactorSecret($this->zdb);
        $this->assertTrue($still_there->load($admin->id));
        $this->assertTrue($still_there->isEnabled());

        //nor may staff take an administrator's away: staff can already change
        //that password, and the second factor was what stood between the two
        $this->login->logOut();
        $staff = $this->getStaffMember($this->createMember($this->dataAdherentTwo()));
        $this->assertTrue($this->login->logIn($staff->login, 'T.u!IbKOi|06'));
        $this->assertTrue($this->login->isStaff());
        $this->assertFalse($this->login->isAdmin());

        $test_response = $this->app->handle(
            $this->createRequest('do-two-factor-reset', ['id' => (string)$admin->id], 'POST')
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $flash = $this->flash_data['slimFlash']['error_detected'][0] ?? '';
        $this->assertStringContainsString('Only the super administrator', $flash);
        $this->flash_data = [];
        $this->expectLogEntry(Analog::WARNING, 'Refused a second factor reset');
        $this->assertTrue($still_there->load($admin->id));

        //and the super administrator may
        $this->login->logOut();
        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->createRequest('do-two-factor-reset', ['id' => (string)$admin->id], 'POST')
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertFalse($still_there->load($admin->id));
        $this->flash_data = [];
    }

    /**
     * Nothing about the second factor is offered while the policy is disabled:
     * a secret enrolled then would never be asked for, and would come back to
     * life the day the policy is turned on
     */
    public function testNothingIsOfferedWhileThePolicyIsDisabled(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_DISABLED;
        $this->assertTrue($this->login->logIn($member->login, 'J^B-()f'));

        foreach (['two-factor-manage', 'two-factor-enrol'] as $route) {
            $test_response = $this->app->handle($this->createRequest($route));
            $this->assertSame(301, $test_response->getStatusCode());
            $this->assertSame(
                ['Location' => [$this->routeparser->urlFor('slash')]],
                $test_response->getHeaders()
            );
            $flash = $this->flash_data['slimFlash']['warning_detected'][0] ?? '';
            $this->assertStringContainsString('not enabled on this instance', $flash);
            $this->flash_data = [];
        }

        //and no secret has been created along the way
        $secret = new \Galette\Core\TwoFactorSecret($this->zdb);
        $this->assertFalse($secret->load($member->id));

        $test_response = $this->app->handle($this->postTo('do-two-factor-enrol', '000000'));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertFalse($secret->load($member->id));
        $this->flash_data = [];
    }

    /**
     * The two ways the challenge used to let an attempt pass uncounted: a code
     * that is not a string at all, and a factor removed mid-challenge
     */
    public function testEveryChallengeAttemptIsCounted(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $secret = $this->enrol($member->id);

        $this->app->handle($this->loginRequest($member->login, 'J^B-()f'));
        $this->assertTrue($this->login->isTwoFactorPending());
        $this->flash_data = [];

        //an array where a code is expected used to be cast to a string, which
        //the error handler turns into a 500 -- and a 500 counts for nothing
        $test_response = $this->app->handle(
            $this->createRequest('do-two-factor', [], 'POST')->withParsedBody(['code' => ['0', '0']])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertFalse($this->login->isLogged());
        $this->assertTrue($this->login->isTwoFactorPending());
        $this->expectFlashData(['error_detected' => ['Two-factor authentication failed.']]);
        $this->assertSame(1, $this->countFailures(\Galette\Core\AuthThrottle::SCOPE_SECOND_FACTOR, $member->login));

        //the factor going away mid-challenge closes the session instead of
        //offering the form again for ever
        $secret->remove();
        $test_response = $this->app->handle($this->postTo('do-two-factor', '000000'));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertFalse($this->login->isLogged());
        $this->assertFalse($this->login->isTwoFactorPending());
        $this->assertSame(2, $this->countFailures(\Galette\Core\AuthThrottle::SCOPE_SECOND_FACTOR, $member->login));
        $this->flash_data = [];
        $this->expectLogEntry(Analog::WARNING, 'No enabled second factor for');
    }

    /**
     * Reaching the challenge means holding the password, so the code must not
     * be open to unlimited guessing: past the threshold even the right code is
     * refused
     */
    public function testChallengeIsThrottled(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $secret = $this->enrol($member->id);
        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);

        $this->app->handle($this->loginRequest($member->login, 'J^B-()f'));
        $this->assertTrue($this->login->isTwoFactorPending());
        $this->flash_data = [];

        for ($i = 0; $i < $preferences->pref_throttle_account_attempts; $i++) {
            $this->app->handle($this->postTo('do-two-factor', '000000'));
            $this->assertFalse($this->login->isLogged());
            $this->flash_data = [];
        }

        //the right code no longer gets through
        $this->app->handle($this->postTo('do-two-factor', $tfa->getCodeAt($secret->getSecret())));
        $this->assertFalse($this->login->isLogged());
        $flash = $this->flash_data['slimFlash']['error_detected'][0] ?? '';
        $this->assertStringContainsString('Too many failed attempts', $flash);
        $this->flash_data = [];

        //and it is counted apart from the password stage
        $select = $this->zdb->select(\Galette\Core\AuthThrottle::TABLE);
        $select->where(
            [
                'scope' => \Galette\Core\AuthThrottle::SCOPE_SECOND_FACTOR,
                'identifier' => $member->login
            ]
        );
        $this->assertSame(1, $this->zdb->execute($select)->count());

        $select = $this->zdb->select(\Galette\Core\AuthThrottle::TABLE);
        $select->where(
            [
                'scope' => \Galette\Core\AuthThrottle::SCOPE_ACCOUNT,
                'identifier' => $member->login
            ]
        );
        $this->assertSame(0, $this->zdb->execute($select)->count());
    }

    /**
     * An authentication is only complete once the second factor is produced, so
     * that is where the counters of both stages are cleared. Without this, a
     * member who mistyped their password would carry the count around until it
     * expired.
     */
    public function testPassingTheChallengeClearsBothCounters(): void
    {
        global $preferences;

        $member = $this->createMember($this->dataAdherentOne());
        $preferences->pref_2fa_mode = \Galette\Core\TwoFactorAuth::MODE_OPTIONAL;
        $secret = $this->enrol($member->id);
        $tfa = new \Galette\Core\TwoFactorAuth($preferences, new \Galette\Core\SystemClock(), $this->zdb);

        //two typos on the password, then one on the code
        foreach (['wrong', 'wrong-again'] as $bad) {
            $this->app->handle($this->loginRequest($member->login, $bad));
            $this->expectFlashData(['error_detected' => ['Login failed.']]);
            $this->expectLogEntry(Analog::WARNING, 'Passwords mismatch for login');
        }
        $this->app->handle($this->loginRequest($member->login, 'J^B-()f'));
        $this->flash_data = [];
        $this->app->handle($this->postTo('do-two-factor', '000000'));
        $this->flash_data = [];

        $this->assertSame(2, $this->countFailures(\Galette\Core\AuthThrottle::SCOPE_ACCOUNT, $member->login));
        $this->assertSame(1, $this->countFailures(\Galette\Core\AuthThrottle::SCOPE_SECOND_FACTOR, $member->login));
        //only the two password typos are on the address: a wrong code must not
        //count there, or members fumbling codes behind one shared address would
        //lock the password stage for everybody behind it
        $this->assertSame(2, $this->countFailures(\Galette\Core\AuthThrottle::SCOPE_IP, '127.0.0.1'));

        //then the right code, which wipes both
        $this->app->handle($this->postTo('do-two-factor', $tfa->getCodeAt($secret->getSecret())));
        $this->assertTrue($this->login->isLogged());
        $this->flash_data = [];

        $this->assertSame(0, $this->countFailures(\Galette\Core\AuthThrottle::SCOPE_ACCOUNT, $member->login));
        $this->assertSame(0, $this->countFailures(\Galette\Core\AuthThrottle::SCOPE_SECOND_FACTOR, $member->login));
    }

    /**
     * Failures counted on a scope for an identifier
     *
     * @param string $scope      Scope name
     * @param string $identifier Identifier
     */
    private function countFailures(string $scope, string $identifier): int
    {
        $select = $this->zdb->select(\Galette\Core\AuthThrottle::TABLE);
        $select->where(['scope' => $scope, 'identifier' => $identifier]);
        $results = $this->zdb->execute($select);
        return $results->count() === 0 ? 0 : (int)$results->current()->failures;
    }
}
