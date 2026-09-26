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
 * Superadmin credentials page tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdminCredentialsTest extends GaletteRoutingTestCase
{
    protected int $seed = 20260926101500;

    /** Current superadmin password */
    private const string PASSWORD = 'aV3ry-S3cret!';

    /**
     * Log in as superadmin, with a known password
     */
    private function logWithPassword(): void
    {
        $this->logSuperAdmin();
        $this->assertTrue(
            $this->preferences->setValue('pref_admin_pass', self::PASSWORD, $this->login),
            print_r($this->preferences->getErrors(), return: true)
        );
    }

    /**
     * Post the credentials form
     *
     * @param array<string, string> $post Posted values
     */
    private function post(array $post): void
    {
        $request = $this->createRequest('storeAdminCredentials', method: 'POST')
            ->withParsedBody($post + ['current_password' => self::PASSWORD]);
        $test_response = $this->app->handle($request);

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('adminCredentials')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
    }

    /**
     * Both routes are reserved to the superadmin
     */
    public function testRoutesAreSuperAdminOnly(): void
    {
        $authenticate = $this->container->get(\Galette\Middleware\Authenticate::class);

        foreach (['adminCredentials', 'storeAdminCredentials'] as $route) {
            $this->assertSame(
                'superadmin',
                $authenticate->getAclFor($route),
                $route . ' is not reserved to the superadmin'
            );
        }
    }

    /**
     * Anonymous visitors are sent to the login page
     */
    public function testPageRequiresLogin(): void
    {
        $this->expectLogin($this->app->handle($this->createRequest('adminCredentials')));
    }

    /**
     * The page shows the current login, and asks for the current password
     */
    public function testPage(): void
    {
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->createRequest('adminCredentials'));
        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('value="admin"', $body);
        $this->assertStringContainsString('name="current_password"', $body);
    }

    /**
     * Nothing changes without the current password, and the typed login is kept
     */
    public function testWrongCurrentPassword(): void
    {
        $this->logWithPassword();
        $stored_pass = $this->preferences->pref_admin_pass;

        $this->post([
            'pref_admin_login' => 'GSuperUser',
            'pref_admin_pass' => 'an0th3r_s3cr3t',
            'pref_admin_pass_check' => 'an0th3r_s3cr3t',
            'current_password' => 'wrong',
        ]);
        $this->expectLogEntry(Analog::WARNING, 'Wrong current password given to change the superadmin credentials.');
        $this->expectFlashData(['error_detected' => ['Wrong password!']]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame('admin', $prefs->pref_admin_login);
        $this->assertSame($stored_pass, $prefs->pref_admin_pass);

        $test_response = $this->app->handle($this->createRequest('adminCredentials'));
        $this->expectOK($test_response);
        $this->assertStringContainsString('value="GSuperUser"', (string)$test_response->getBody());
    }

    /**
     * A confirmation that does not match leaves the password alone
     */
    public function testPasswordsMismatch(): void
    {
        $this->logWithPassword();
        $stored_pass = $this->preferences->pref_admin_pass;

        $this->post([
            'pref_admin_login' => 'admin',
            'pref_admin_pass' => 'an0th3r_s3cr3t',
            'pref_admin_pass_check' => 'an0th3r_s3cr4t',
        ]);
        $this->expectFlashData(['error_detected' => ['Passwords mismatch']]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame($stored_pass, $prefs->pref_admin_pass);
    }

    /**
     * A refused login is reported, and nothing is stored
     */
    public function testInvalidLogin(): void
    {
        $this->logWithPassword();
        $stored_pass = $this->preferences->pref_admin_pass;

        $this->post([
            'pref_admin_login' => '',
            'pref_admin_pass' => 'an0th3r_s3cr3t',
            'pref_admin_pass_check' => 'an0th3r_s3cr3t',
        ]);
        $this->expectFlashData(['error_detected' => ['- The username must be composed of at least 4 characters!']]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame('admin', $prefs->pref_admin_login);
        $this->assertSame($stored_pass, $prefs->pref_admin_pass);
    }

    /**
     * The login can change alone, and the session follows it
     */
    public function testChangeLoginOnly(): void
    {
        $this->logWithPassword();
        $stored_pass = $this->preferences->pref_admin_pass;

        $this->post([
            'pref_admin_login' => 'GSuperUser',
            'pref_admin_pass' => '',
            'pref_admin_pass_check' => '',
        ]);
        $this->expectFlashData(['success_detected' => ['Your credentials have been saved.']]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame('GSuperUser', $prefs->pref_admin_login);
        $this->assertSame($stored_pass, $prefs->pref_admin_pass);

        $this->assertSame('GSuperUser', $this->login->login);
        $this->assertTrue($this->login->isSuperAdmin());
    }

    /**
     * Changing the password
     */
    public function testChangePassword(): void
    {
        $this->logWithPassword();

        $this->post([
            'pref_admin_login' => 'admin',
            'pref_admin_pass' => 'an0th3r_s3cr3t',
            'pref_admin_pass_check' => 'an0th3r_s3cr3t',
        ]);
        $this->expectFlashData(['success_detected' => ['Your credentials have been saved.']]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame('admin', $prefs->pref_admin_login);
        $this->assertTrue(password_verify('an0th3r_s3cr3t', $prefs->pref_admin_pass));
    }
}
