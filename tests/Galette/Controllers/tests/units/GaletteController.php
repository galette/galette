<?php

/**
* Copyright © 2003-2025 The Galette Team
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

namespace Galette\Controllers\test\units;

use Galette\GaletteRoutingTestCase;

/**
* Galette controller tests
*
* @author Johan Cwiklinski <johan@x-tnd.be>
*/
class GaletteController extends GaletteRoutingTestCase
{
    protected int $seed = 20250802103040;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->cleanContributions();
        $this->cleanMembers();
    }

    /**
     * Test main route (redirections)
     *
     * @return void
     */
    public function testSlash(): void
    {
        $request = $this->createRequest('slash');

        //non logged-in users gets redirected to login page
        $test_response = $this->app->handle($request);
        $this->assertEquals(301, $test_response->getStatusCode());
        $this->assertSame(['Location' => [$this->routeparser->urlFor('login')]], $test_response->getHeaders());

        //superadmin user will be redirected to dashboard
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertEquals(301, $test_response->getStatusCode());
        $this->assertSame(['Location' => [$this->routeparser->urlFor('dashboard')]], $test_response->getHeaders());
    }

    /**
     * Test system information route
     *
     * @return void
     */
    public function testSystemInformation(): void
    {
        $request = $this->createRequest('sysinfos');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        $this->logSuperAdmin();
        $_SERVER['HTTP_USER_AGENT'] = 'Galette test suite';

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Dashboard', $body);
        $this->assertMatchesRegularExpression(
            '/Browser:\.+ Galette test suite/',
            $body
        );
        $this->assertMatchesRegularExpression(
            '/PHP version:\.+ ' . preg_quote(phpversion()) . '/',
            $body
        );
    }

    /**
     * Test dashboard route
     *
     * @return void
     */
    public function testDashboard(): void
    {
        $request = $this->createRequest('dashboard');
        $request = $request->withCookieParams(['show_galette_dashboard' => 'true']);

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get dashboard displayed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Dashboard', $body);
    }

    /**
     * Test preferences route
     *
     * @return void
     */
    public function testPreferences(): void
    {
        $request = $this->createRequest('preferences');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Settings', $body);
        $this->assertStringContainsString('<input type="text" name="pref_nom" id="pref_nom" value="Galette"', $body);

        //simulate error while storing, values are kept in session
        $this->session->entered_preferences = ['pref_nom' => 'Name from test suite'];
        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Settings', $body);
        $this->assertStringContainsString('<input type="text" name="pref_nom" id="pref_nom" value="Name from test suite"', $body);
    }

    /**
     * Test store preferences
     *
     * @return void
     */
    public function testStorePreferences(): void
    {
        $request = $this->createRequest('store-preferences', [], 'POST');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin can store preferences
        $this->logSuperAdmin();

        $request = $request->withParsedBody(['pref_nom' => 'Name changed from test suite', 'valid' => 1] + $this->preferences->getDefaults());
        $test_response = $this->app->handle($request);
        $this->assertEquals(301, $test_response->getStatusCode());
        $this->assertSame(['Location' => [$this->routeparser->urlFor('preferences')]], $test_response->getHeaders());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' =>  ['Preferences has been saved.']], $this->flash_data['slimFlash']);

        //check for change
        $preferences = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame('Name changed from test suite', $preferences->pref_nom);

        //restore
        $request = $request->withParsedBody(['pref_nom' => 'Galette', 'valid' => 1] + $this->preferences->getDefaults());
        $test_response = $this->app->handle($request);
        $this->assertEquals(301, $test_response->getStatusCode());
        $preferences = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame('Galette', $preferences->pref_nom);
    }

    /**
     * Test email test route
     *
     * @return void
     */
    public function testTestEmail(): void
    {
        $request = $this->createRequest('testEmail');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin can test email
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('preferences')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You asked Galette to send a test email, but email has been disabled in the preferences.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_SMTP;

        //test invalid test email
        $invalid_request = $request->withQueryParams(['adress' => 'invalidemail']);
        $test_response = $this->app->handle($invalid_request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('preferences')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Invalid email adress!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //standard working test email - no real email provider setup so gives an error
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('preferences')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['No email sent to mail@domain.com']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //ajax test email - no real email provider setup so gives an error
        $json_request = $request->withHeader('X-Requested-With', 'XMLHttpRequest');
        $test_response = $this->app->handle($json_request);
        $this->assertSame(['Content-Type' => ['application/json']], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $body = (string)$test_response->getBody();
        $this->assertSame('{"sent":0}', $body);
        $this->assertSame(['error_detected' => ['No email sent to mail@domain.com']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //Reset mail method to default
        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_DISABLED;
    }

    /**
     * Test charts route
     *
     * @return void
     */
    public function testCharts(): void
    {
        $request = $this->createRequest('charts');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Charts', $body);
    }

    /**
     * Test core fields configuration route
     *
     * @return void
     */
    public function testCoreFieldsConfiguration(): void
    {
        $request = $this->createRequest('configureCoreFields');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Core fields', $body);
    }

    /**
     * Test core fields configuration storage route
     *
     * @return void
     */
    public function testStoreCoreFieldsConfiguration(): void
    {
        $request = $this->createRequest('storeCoreFieldsConfig', [], 'POST');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $fields_config = $this->container->get(\Galette\Entity\FieldsConfig::class);
        $fields_config->installInit();
        $fields_config->load();

        //prepare data with some changes
        $fields = $fields_config->getCategorizedFields();
        //town
        $town = &$fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2];
        $this->assertTrue($town['required']);
        $this->assertSame(\Galette\Entity\FieldsConfig::USER_WRITE, $town['visible']);

        $town['required'] = false;
        $town['visible'] = \Galette\Entity\FieldsConfig::NOBODY;

        //gsm
        $gsm = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5];
        $gsm['position'] = count($fields[1]);
        unset($fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5]);
        $gsm['category'] = \Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY;
        $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][] = $gsm;

        //prepare post data
        $post = [
            'categories' => [],
            'fields' => [],
        ];
        foreach ($fields as $category => $fields_list) {
            $post['categories'][] = $category;
            foreach ($fields_list as $field) {
                $post['fields'][] = $field['field_id'];
                $post[$field['field_id'] . '_category'] = $category;
                $post[$field['field_id'] . '_label'] = $field['label'];
                $post[$field['field_id'] . '_required'] = (int)$field['required'];
                $post[$field['field_id'] . '_visible'] = $field['visible'];
            }
        }

        $this->logSuperAdmin();
        $request = $request->withParsedBody($post);
        $test_response = $this->app->handle($request);
        $this->assertEquals(301, $test_response->getStatusCode());
        $this->assertSame(['Location' => [$this->routeparser->urlFor('configureCoreFields')]], $test_response->getHeaders());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' =>  ['Fields configuration has been successfully stored']], $this->flash_data['slimFlash']);

        //check if changes have been stored in database
        $fields_config->load();
        $fields = $fields_config->getCategorizedFields();

        $town = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2];
        $this->assertFalse($town['required']);
        $this->assertSame(\Galette\Entity\FieldsConfig::NOBODY, $town['visible']);

        $gsm2 = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][13];
        $this->assertSame($gsm, $gsm2);
    }

    /**
     * Test core list configuration route
     *
     * @return void
     */
    public function testConfigureListFields(): void
    {
        $request = $this->createRequest('configureListFields', ['table' => 'adherents']);

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Core lists', $body);
    }

    /**
     * Test core list configuration storage route
     *
     * @return void
     */
    public function testStoreConfigureListFields(): void
    {
        $request = $this->createRequest('storeListFields', ['table' => 'adherents'], 'POST');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $lists_config = $this->container->get(\Galette\Entity\ListsConfig::class);
        $lists_config->installInit();
        $lists_config->load();

        $list = $lists_config->getListedFields();
        $this->assertCount(6, $list);

        $expecteds = [
            'id_adh',
            'list_adh_name',
            'pseudo_adh',
            'id_statut',
            'list_adh_contribstatus',
            'date_modif_adh'
        ];
        foreach ($expecteds as $k => $expected) {
            $this->assertSame($expected, $list[$k]['field_id']);
            $this->assertSame($k, $list[$k]['list_position']);
        }

        //prepare data with some changes
        $expecteds = [
            'id_adh',
            'list_adh_name',
            'email_adh',
            'tel_adh',
            'id_statut',
            'list_adh_contribstatus',
            'ville_adh'
        ];

        $new_list = [];
        $post = ['fields' => []];
        foreach ($expecteds as $key) {
            $new_list[] = $lists_config->getField($key);
            $post['fields'][] = $key;
        }

        $this->logSuperAdmin();
        $request = $request->withParsedBody($post);
        $test_response = $this->app->handle($request);
        $this->assertEquals(301, $test_response->getStatusCode());
        $this->assertSame(['Location' => [$this->routeparser->urlFor('configureListFields', ['table' => 'adherents'])]], $test_response->getHeaders());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' =>  ['List configuration has been successfully stored']], $this->flash_data['slimFlash']);


        //check if changes have been stored in database
        $list = $lists_config->getListedFields();
        $this->assertCount(7, $list);

        foreach ($expecteds as $k => $expected) {
            $this->assertSame($expected, $list[$k]['field_id']);
            $this->assertSame($k, $list[$k]['list_position']);
        }

        $field = $lists_config->getField('pseudo_adh');
        $this->assertSame(-1, $field['list_position']);
        $this->assertFalse($field['list_visible']);

        $field = $lists_config->getField('date_modif_adh');
        $this->assertSame(-1, $field['list_position']);
        $this->assertFalse($field['list_visible']);
    }

    /**
     * Test reminders route
     *
     * @return void
     */
    public function testReminders(): void
    {
        $request = $this->createRequest('reminders');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Reminders', $body);
        $this->assertStringContainsString(
            '<a href="/members/reminder-filter/nearly/withmail">0 members with an email address</a>',
            $body
        );
        $this->assertStringContainsString(
            '<a href="/members/reminder-filter/nearly/withoutmail">0 members without email address</a>',
            $body
        );
        $this->assertStringContainsString(
            '<a href="/members/reminder-filter/late/withmail">0 members with an email address</a>',
            $body
        );
        $this->assertStringContainsString(
            '<a href="/members/reminder-filter/late/withoutmail">0 members without email address</a>',
            $body
        );
    }

    /**
     * Test do reminders route
     *
     * @return void
     */
    public function testDoReminders(): void
    {
        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_SMTP;
        $request = $this->createRequest('doReminders', [], 'POST');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        //no reminder to send by default
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->expectNoLogEntry();
        $this->assertSame(['warning_detected' =>  ['No reminder to send for now.']], $this->flash_data['slimFlash']);
        $this->assertEquals(301, $test_response->getStatusCode());
        $this->assertSame(['Location' => [$this->routeparser->urlFor('reminders')]], $test_response->getHeaders());
        $this->flash_data = [];

        //impendings
        $ireminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::IMPENDING]);
        $this->assertSame([], $ireminders->getList($this->zdb));

        //lates
        $lreminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::LATE]);
        $this->assertSame([], $lreminders->getList($this->zdb));

        //all
        $reminders = new \Galette\Repository\Reminders();
        $this->assertSame([], $reminders->getList($this->zdb));

        //create member
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $now = new \DateTime();

        //create a close to be expired contribution
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $member_one->id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $this->assertTrue($member_one->load($member_one->id));

        //member is up-to-date, and close to be expired, one impending reminder to send
        $this->assertTrue($member_one->isUp2Date());

        //create an expired contribution, late by 40 days
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P40D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $member_two->id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        $this->assertTrue($member_two->load($member_two->id));

        //member is late, one late reminder to send
        $this->assertFalse($member_two->isUp2Date());

        $this->assertCount(2, $reminders->getList($this->zdb));
        $this->assertCount(1, $lreminders->getList($this->zdb));
        $this->assertCount(1, $ireminders->getList($this->zdb));

        $test_response = $this->app->handle($request);
        $this->expectNoLogEntry();
        // no real email provider setup so gives an error
        $this->assertSame(
            [
                'error_detected' =>  [
                    'Reminder has not been sent:',
                    'DURAND René <meunier.josephine20250802103040@ledoux.com> (30 days)',
                    'HOARAU Lucas <phoarau20250802103040@tele2.fr> (40 days)',
                ]
            ],
            $this->flash_data['slimFlash']
        );
        $this->flash_data = [];
        $this->assertEquals(301, $test_response->getStatusCode());
        $this->assertSame(['Location' => [$this->routeparser->urlFor('reminders')]], $test_response->getHeaders());
        $this->preferences->pref_mail_method = \Galette\Core\GaletteMail::METHOD_DISABLED;
    }

    /**
     * Test filter reminders route
     *
     * @return void
     */
    public function testFilterReminders(): void
    {
        $request = $this->createRequest('reminders-filter', ['membership' => 'nearly', 'mail' => 'withmail']);

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //superadmin will get page displayed
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertEquals(301, $test_response->getStatusCode());
    }

    /**
     * Test direct link route
     *
     * @return void
     */
    public function testDocumentLink(): void
    {
        $request = $this->createRequest('directlink', ['hash' => 'testhash']);

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
        $body = (string) $test_response->getBody();
        $this->assertStringContainsString('Download document', $body);
        $this->assertStringContainsString(
            sprintf('<form action="%s"', $this->routeparser->urlFor('get-directlink', ['hash' => 'testhash'])),
            $body
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="hash" value="testhash"/>',
            $body
        );
    }

    /**
     * Test favicon route
     *
     * @return void
     */
    public function testFavicon(): void
    {
        $request = $this->createRequest('defaultFavicon');

        $test_response = $this->app->handle($request);
        $this->assertEquals(200, $test_response->getStatusCode());
    }
}
