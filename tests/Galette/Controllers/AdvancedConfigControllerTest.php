<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Tests\GaletteRoutingTestCase;

/**
 * Advanced configuration controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdvancedConfigControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20260824104500;

    /**
     * Every route of the page is reserved to the superadmin
     */
    public function testRoutesAreSuperAdminOnly(): void
    {
        $authenticate = $this->container->get(\Galette\Middleware\Authenticate::class);

        foreach (['advancedConfig', 'saveAdvancedConfig', 'resetAdvancedConfig'] as $route) {
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
        $test_response = $this->app->handle($this->createRequest('advancedConfig'));
        $this->expectLogin($test_response);
    }

    /**
     * Storing then resetting one setting, through the page
     */
    public function testSaveThenReset(): void
    {
        $this->logSuperAdmin();
        $default = $this->preferences->getDefaults()['pref_numrows'];

        $request = $this->createRequest('saveAdvancedConfig', method: 'POST')
            ->withParsedBody(['name' => 'pref_numrows', 'value' => '42']);
        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('advancedConfig')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ["Preference 'pref_numrows' has been stored."]]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame(42, $prefs->pref_numrows);

        $request = $this->createRequest('resetAdvancedConfig', method: 'POST')
            ->withParsedBody(['name' => 'pref_numrows']);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ["Preference 'pref_numrows' has been reset to its default."]]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame($default, $prefs->pref_numrows);
    }

    /**
     * A refused value comes back as an error, and nothing is stored
     */
    public function testRefusedValue(): void
    {
        $this->logSuperAdmin();
        $stored = $this->preferences->pref_card_vsize;

        $request = $this->createRequest('saveAdvancedConfig', method: 'POST')
            ->withParsedBody(['name' => 'pref_card_vsize', 'value' => '12']);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(
            ['error_detected' => ['- The card height have to be an integer between 40 and 55!']]
        );

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame($stored, $prefs->pref_card_vsize);
    }

    /**
     * The superadmin gets the whole list
     */
    public function testPageListsEverySetting(): void
    {
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->createRequest('advancedConfig'));
        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        //a setting the settings form does show
        $this->assertStringContainsString('pref_numrows', $body);
        //and one it does not
        $this->assertStringContainsString('pref_registration_uuid', $body);
        //secrets are listed but never rendered
        $this->assertStringContainsString('pref_admin_pass', $body);
        $this->assertStringNotContainsString((string)$this->preferences->pref_admin_pass, $body);
    }
}
