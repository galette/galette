<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Controllers\AdvancedConfigController;
use Galette\Tests\GaletteRoutingTestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Safe\define;

/**
 * Advanced configuration controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdvancedConfigControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20260824104500;

    /** Password used by the tests confirming access to the page */
    private const string PASSWORD = 'aV3ry-S3cret!';

    /**
     * Log in as superadmin and get through the password confirmation
     */
    private function reachPage(): void
    {
        $this->logSuperAdmin();
        $this->assertTrue(
            $this->preferences->setValue('pref_admin_pass', self::PASSWORD, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $request = $this->createRequest('confirmAdvancedConfig', method: 'POST')
            ->withParsedBody(['password' => self::PASSWORD]);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('advancedConfig')]],
            $test_response->getHeaders()
        );
    }

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
        $this->reachPage();
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
        $this->reachPage();
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
        $this->reachPage();

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

    /**
     * A value Galette maintains is shown, but offers no way to change it
     */
    public function testReadOnlyRowsOfferNoControl(): void
    {
        $this->reachPage();
        $uuid = $this->preferences->generateUUID('instance');

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();

        //listed, with its value
        $this->assertStringContainsString('pref_instance_uuid', $body);
        $this->assertStringContainsString($uuid, $body);
        //but no field to change it
        $this->assertStringNotContainsString('cfg_pref_instance_uuid', $body);
        //while an editable one does have its form
        $this->assertStringContainsString('cfg_pref_numrows', $body);
    }

    /**
     * The page asks for the password before showing anything
     */
    public function testPasswordIsRequired(): void
    {
        $this->logSuperAdmin();

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $this->assertStringContainsString('name="password"', $body);
        //no setting leaks through the confirmation screen
        $this->assertStringNotContainsString('pref_numrows', $body);

        $this->reachPage();

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $this->assertStringNotContainsString('name="password"', $body);
        $this->assertStringContainsString('pref_numrows', $body);
    }

    /**
     * A wrong password says so, and is logged
     */
    public function testWrongPasswordIsRefused(): void
    {
        $this->logSuperAdmin();

        $request = $this->createRequest('confirmAdvancedConfig', method: 'POST')
            ->withParsedBody(['password' => 'not the one']);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['Wrong password!']]);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Wrong password given to reach the advanced configuration page.'
        );

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $this->assertStringContainsString('name="password"', $body);
    }

    /**
     * The confirmation expires, and writing is refused once it has
     */
    public function testConfirmationExpires(): void
    {
        $this->reachPage();

        //pretend the confirmation happened just beyond its lifetime
        $lifetime = \Galette\Controllers\AdvancedConfigController::CONFIRM_LIFETIME;
        $this->session->advanced_config_confirmed_at = time() - $lifetime - 1;

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $this->assertStringContainsString('name="password"', $body);

        $request = $this->createRequest('saveAdvancedConfig', method: 'POST')
            ->withParsedBody(['name' => 'pref_numrows', 'value' => '42']);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['Please confirm your password again.']]);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertNotSame(42, $prefs->pref_numrows);
    }

    /**
     * A colour is the same colour whatever the case it was typed in
     */
    public function testColourCaseIsNotAChange(): void
    {
        $this->reachPage();

        $default = $this->preferences->getDefaults()['pref_card_tcol'];
        $this->assertSame('#FFFFFF', $default);

        $this->assertTrue(
            $this->preferences->setValue('pref_card_tcol', '#ffffff', $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        //stored as typed, validateValue() does not touch the case
        $this->assertSame('#ffffff', $this->preferences->pref_card_tcol);

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $row = $this->rowFor($body, 'pref_card_tcol');
        $this->assertStringContainsString('>' . _T("default") . '<', $row);
        $this->assertStringNotContainsString('>' . _T("modified") . '<', $row);

        //a genuinely different colour is still reported as modified
        $this->preferences->setValue('pref_card_tcol', '#123456', $this->login);
        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $row = $this->rowFor($body, 'pref_card_tcol');
        $this->assertStringContainsString('>' . _T("modified") . '<', $row);

        $this->preferences->resetValue('pref_card_tcol', $this->login);
    }

    /**
     * Every status the table can show is explained in the legend
     */
    public function testLegendCoversEveryStatus(): void
    {
        $this->reachPage();

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();

        $this->assertStringContainsString('id="legende"', $body);
        $this->assertStringContainsString('show_legend', $body);

        foreach (['default', 'modified', 'read-only', 'secret', 'locked', 'unknown'] as $status) {
            $this->assertStringContainsString(
                '>' . _T($status) . '<',
                $body,
                'status ' . $status . ' missing from the legend'
            );
        }
    }

    /**
     * A preference a plugin declares is listed, editable, and credited to it
     */
    public function testPluginPreferenceIsEditable(): void
    {
        $this->reachPage();

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $row = $this->rowFor($body, 'pref_plugin1_label');

        $this->assertStringContainsString('>plugin1<', $row, 'owning plugin not shown');
        $this->assertStringContainsString('name="value"', $row, 'not offered for edition');
    }

    /**
     * A row nothing describes is still rendered
     *
     * Such rows exist in the field, left by an older version or by a plugin
     * that has been removed, and they carry none of what the table shows for
     * a described one.
     */
    public function testUnknownRowIsRendered(): void
    {
        $this->reachPage();

        $insert = $this->zdb->insert(\Galette\Core\Preferences::TABLE);
        $insert->values(['nom_pref' => 'pref_left_over', 'val_pref' => 'from an older version']);
        $this->zdb->execute($insert);
        //the instance the page renders from was loaded before that row existed
        $this->preferences->load();

        try {
            $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
            $row = $this->rowFor($body, 'pref_left_over');

            $this->assertStringContainsString('>' . _T("unknown") . '<', $row);
            $this->assertStringContainsString('from an older version', $row);
            $this->assertStringNotContainsString('name="value"', $row, 'offered for edition');

            //the table reads those outside of any "known" guard, so every entry
            //has to carry them. strict_variables follows GALETTE_DEBUG, so a
            //missing one only breaks the page on a debugging instance
            $method = new \ReflectionMethod(AdvancedConfigController::class, 'getEntries');
            $entries = $method->invoke($this->container->get(AdvancedConfigController::class));
            foreach ($entries as $entry) {
                foreach (['name', 'known', 'plugin', 'value'] as $key) {
                    $this->assertArrayHasKey($key, $entry, $entry['name'] . ' has no ' . $key);
                }
            }
        } finally {
            $delete = $this->zdb->delete(\Galette\Core\Preferences::TABLE);
            $delete->where(['nom_pref' => 'pref_left_over']);
            $this->zdb->execute($delete);
        }
    }

    /**
     * Extract the table row describing one preference
     */
    private function rowFor(string $body, string $name): string
    {
        $start = strpos($body, 'data-name="' . $name . '"');
        $this->assertNotFalse($start, $name . ' not found in the page');
        $end = strpos($body, '</tr>', $start);
        $this->assertNotFalse($end);

        return substr($body, $start, $end - $start);
    }

    /**
     * Constants only settable in behavior.inc.php are listed too
     */
    public function testFileOnlyConstantsAreListed(): void
    {
        $this->reachPage();

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();

        foreach (\Galette\Core\BehaviorConstants::getStatus() as $constant) {
            $this->assertStringContainsString($constant['name'], $body);
            $this->assertNotEmpty($constant['description'], $constant['name'] . ' has no description');
        }

        //GALETTE_MODE is always defined, so its value shows
        $this->assertStringContainsString('GALETTE_MODE', $body);
        $this->assertStringContainsString(GALETTE_MODE, $body);

        //constants a preference supersedes are listed too, flagged as such
        //a constant a preference supersedes only shows up while it is declared
        foreach (\Galette\Core\PreferencesSchema::getConstants() as $superseded) {
            $this->assertSame(
                defined($superseded),
                str_contains($body, $superseded),
                $superseded . ' should be listed only when declared'
            );
        }
    }

    /**
     * The session duration is editable, not reported as locked
     *
     * main.inc.php used to define GALETTE_TIMEOUT from the preference, which
     * made pref_session_timeout look overridden by behavior.inc.php on every
     * single instance, and hid its value behind a read-only cell.
     */
    public function testSessionTimeoutIsEditable(): void
    {
        $this->reachPage();
        $this->assertFalse(defined('GALETTE_TIMEOUT'));

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $row = $this->rowFor($body, 'pref_session_timeout');

        $this->assertStringNotContainsString('>' . _T("locked") . '<', $row);
        $this->assertStringContainsString('cfg_pref_session_timeout', $row);
        $this->assertStringContainsString('type="number"', $row);
    }
    /**
     * A locked row shows the value that applies, not the one in database
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLockedRowShowsTheConstantValue(): void
    {
        $this->reachPage();

        $this->assertTrue(
            $this->preferences->setValue('pref_galette_url', 'https://stored.example.com', $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        define('GALETTE_URI', 'https://from-the-file.example.com');

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $row = $this->rowFor($body, 'pref_galette_url');

        $this->assertStringContainsString('>' . _T("locked") . '<', $row);
        $this->assertStringContainsString('https://from-the-file.example.com', $row);
        $this->assertStringNotContainsString('https://stored.example.com', $row);
    }

    /**
     * A secret is never reported as modified
     *
     * It is stored hashed, so comparing it to the shipped default says nothing
     * and used to read as modified on every single instance.
     */
    public function testSecretsHaveTheirOwnStatus(): void
    {
        $this->reachPage();

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $row = $this->rowFor($body, 'pref_admin_pass');

        $this->assertStringContainsString('>' . _T("secret") . '<', $row);
        $this->assertStringNotContainsString('>' . _T("modified") . '<', $row);
        $this->assertStringNotContainsString('>' . _T("default") . '<', $row);
        //still says whether one is set, and offers no field
        $this->assertStringContainsString(_T("set"), $row);
        $this->assertStringNotContainsString('cfg_pref_admin_pass', $row);
    }

    /**
     * A deprecated constant links to the setting replacing it
     *
     * Built in the template rather than inside the description: Twig escapes
     * what comes from PHP, so an anchor put in the translated string shows up
     * as markup instead of a link.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDeprecatedConstantsLinkToTheirSetting(): void
    {
        $this->reachPage();

        //every preference row carries the anchor a constant would point at
        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        foreach (array_keys(\Galette\Core\PreferencesSchema::getConstants()) as $preference) {
            $this->assertStringContainsString('id="' . $preference . '"', $body);
        }

        //no constant is declared here, so none is listed and none links
        $this->assertStringNotContainsString('href="#pref_galette_url"', $body);

        define('GALETTE_URI', 'https://from-the-file.example.com');

        $body = (string)$this->app->handle($this->createRequest('advancedConfig'))->getBody();
        $this->assertStringContainsString('href="#pref_galette_url"', $body);
        $this->assertStringContainsString('https://from-the-file.example.com', $body);

        //no escaped markup left in a description
        $this->assertStringNotContainsString('&lt;a href=', $body);
    }
}
