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

namespace Galette\Tests\Controllers;

use Galette\Tests\GaletteRoutingTestCase;

/**
 * PDF controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfController extends GaletteRoutingTestCase
{
    protected int $seed = 58144569971203;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Test store models
     */
    public function testStoreModels(): void
    {
        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);
        $this->assertSame('_T("Invoice") {CONTRIBUTION_YEAR}-{CONTRIBUTION_ID}', $model->title);

        $route_name = 'pdfModels';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments, 'POST', 'application/json');
        $request = $request->withParsedBody(
            [
                'store' => true,
                'models_id' => \Galette\Entity\PdfModel::INVOICE_MODEL,
                'model_type' => \Galette\Entity\PdfModel::INVOICE_MODEL,
                'model_title' => 'DaTitle'
            ]
        );

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('pdfModels', ['id' => $model->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(
            [
                'success_detected' => [
                    'Model has been successfully stored!'
                ]
            ]
        );

        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);
        $this->assertSame('DaTitle', $model->title);
    }

    /**
     * Test display models
     */
    public function testDisplayModels(): void
    {
        $route_name = 'pdfModels';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments);

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'PDF models',
            $body
        );
    }

    /**
     * Test membersCards
     */
    public function testMembersCards(): void
    {
        $this->logSuperAdmin();
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());
        $member_one = $this->getMemberOne();
        $this->login->logOut();

        $route_name = 'pdf-members-cards';
        $route_arguments = [\Galette\Entity\Adherent::PK => $member_one->id];

        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with another simple member
        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/member/me']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(
            [
                'error_detected' => [
                    'You do not have permission for requested URL.'
                ]
            ]
        );
        $this->login->logout();

        //test no selection
        $this->logSuperAdmin();
        $test_request = $this->createRequest($route_name, []);
        $test_response = $this->app->handle($test_request);
        $this->assertSame(['Location' => ['/members']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(
            [
                'error_detected' => [
                    'No member was selected, please check at least one name.'
                ]
            ]
        );
        $this->login->logout();

        //test with expected simple member
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        //member is not up-to-date, he cannot see his card
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Member ' . $member_one->id . ' is not up to date; cannot get his PDF member card');

        //make member up-to-date
        $this->login->logout();
        $this->logSuperAdmin();
        $this->createContribution();
        $this->login->logout();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $this->expectOutputRegex('/^%PDF-\d\.\d\.');
        $test_response = $this->app->handle($request);

        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="cards.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
    }

    /**
     * Test filtered membersCards
     */
    public function testFilteredMembersCards(): void
    {
        $member_one = $this->getMemberOne();

        $route_name = 'pdf-members-cards';
        $route_arguments = [\Galette\Entity\Adherent::PK => $member_one->id];
        $request = $this->createRequest($route_name, $route_arguments);

        //test logged-in as superadmin
        $this->logSuperAdmin();

        //test with filters
        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->adh->id];
        $controller = new \Galette\Controllers\PdfController($this->container);
        $this->session->{$controller->getFilterName('members')} = $filters;

        $this->expectOutputRegex('/^%PDF-\d.\d.');
        $test_response = $this->app->handle($request);

        unset($this->session->{$controller->getFilterName('members')});
        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="cards.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
    }

    /**
     * Test membersLabels
     */
    public function testMembersLabels(): void
    {
        $controller = new \Galette\Controllers\PdfController($this->container);
        unset($this->session->{$controller->getFilterName('members')});
        $member_one = $this->getMemberOne();

        $route_name = 'pdf-members-labels';
        $route_arguments = [\Galette\Entity\Adherent::PK => $member_one->id];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        $this->logSuperAdmin();
        //non member selected
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/members']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(
            [
                'error_detected' => [
                    'No member was selected, please check at least one name.'
                ]
            ]
        );

        //add selected member to filters
        $test_response = null;
        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->adh->id];
        $this->session->{$controller->getFilterName('members')} = $filters;

        $this->expectOutputRegex('/^%PDF-\d\.\d');
        $test_response = $this->app->handle($request);

        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="labels_print_filename.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
        unset($this->session->{$controller->getFilterName('members')});
    }

    /**
     * Test filtered membersLabels
     */
    public function testFilteredMembersLabels(): void
    {
        $controller = new \Galette\Controllers\PdfController($this->container);
        unset($this->session->{$controller->getFilterName('members')});
        $this->getMemberOne();

        $route_name = 'pdf-members-labels';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again from filters
        $this->logSuperAdmin();
        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->adh->id];
        $this->session->{$controller->getFilterName('members')} = $filters;

        $this->expectOutputRegex('/^%PDF-\d\.\d');
        $test_response = $this->app->handle($request);

        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="labels_print_filename.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
    }

    /**
     * Test adhesionForm
     */
    public function testadhesionForm(): void
    {
        $controller = new \Galette\Controllers\PdfController($this->container);
        unset($this->session->{$controller->getFilterName('members')});
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $route_name = 'adhesionForm';
        $route_arguments = [\Galette\Entity\Adherent::PK => $member_one->id];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with simple member: can show its own form only
        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/member/me']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(
            [
                'error_detected' => [
                    'You do not have permission for requested URL.'
                ]
            ]
        );
        $this->login->logOut();

        //test logged-in as member one
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->expectOutputRegex('/^%PDF-\d\.\d/');
        $test_response = $this->app->handle($request);
        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="adherent_form.' . $member_one->id . '.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
        $this->login->logout();
    }

    /**
     * Test attendanceSheet
     */
    public function testAttendanceSheet(): void
    {
        $this->getMemberOne();

        $route_name = 'attendance_sheet';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments, 'POST', 'application/json');

        $this->logSuperAdmin();

        //test no selection
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/members']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(
            [
                'error_detected' => [
                    'No member selected to generate attendance sheet'
                ]
            ]
        );

        //test with selection
        $request = $request->withParsedBody(
            [
                'selection' => [$this->adh->id]
            ]
        );

        $this->expectOutputRegex('/^%PDF-\d\.\d/');
        $test_response = $this->app->handle($request);

        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="attendance_sheet.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
    }

    /**
     * Test attendanceSheetConfig
     */
    public function testAttendanceSheetConfig(): void
    {
        $route_name = 'attendance_sheet_details';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments, 'POST', 'application/json');

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->expectOK($test_response);
        $this->expectNoLogEntry();
        $this->expectFlashData([]);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'Attendance sheet configuration',
            $body
        );
    }

    /**
     * Test contribution
     */
    public function testContribution(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        $this->createContribution();
        $contribution_one = $this->contrib;

        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $this->login->logOut();

        $route_name = 'printContribution';
        $route_arguments = ['id' => $contribution_one->id];
        $request = $this->createRequest($route_name, $route_arguments);

        //test No rights
        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/contributions']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'No contribution #' . $contribution_one->id);
        $this->expectFlashData(
            [
                'error_detected' => [
                    'Unable to load contribution #' . $contribution_one->id . '!'
                ]
            ]
        );
        $this->login->logOut();

        //test with correct member
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $this->expectOutputRegex('/^%PDF-\d\.\d\/');
        $test_response = $this->app->handle($request);

        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="contribution_' . $contribution_one->id . '_invoice.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
    }

    /**
     * Test group
     */
    public function testGroup(): void
    {
        $route_name = 'pdf_groups';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        $this->logSuperAdmin();
        $this->expectOutputRegex('/^%PDF-\d\.\d\.');
        $test_response = $this->app->handle($request);

        //no groups, no pdf
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'An error has occurred, unable to get groups list');
        $this->expectFlashData(['error_detected' => ['Unable to get groups list.']]);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());

        $test_response = $this->app->handle($request);
        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="groups_list.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
        $this->login->logOut();
    }

    /**
     * Test direct member card link
     */
    public function testDirectlinkDocumentMemberCard(): void
    {
        $member_one = $this->getMemberOne();
        $hash = base64_encode('FAKER' . $this->seed);

        $route_name = 'get-directlink';
        $route_arguments = ['hash' => $hash];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody(['email' => $member_one->email]);

        //link does not exist
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('directlink', ['hash' => $hash])]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Invalid link!']]);

        //create a link
        $links = new \Galette\Core\Links($this->zdb);
        $hash = $links->generateNewLink(\Galette\Core\Links::TARGET_MEMBERCARD, $member_one->id);
        $route_arguments = ['hash' => $hash];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody(['email' => $member_one->email]);

        $this->expectOutputRegex('/^%PDF-\d\.\d\.');
        $test_response = $this->app->handle($request);
        $this->expectNoLogEntry();
        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="cards.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
    }

    /**
     * Test direct contribution link
     */
    public function testDirectlinkDocumentContribution(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $cdata = $this->getContribData();
        $cdata['id_type_cotis'] = 5; //donation
        $this->createContrib($cdata);
        $contribution_one = $this->contrib;
        $this->login->logOut();

        //create a link
        $links = new \Galette\Core\Links($this->zdb);
        $hash = $links->generateNewLink(\Galette\Core\Links::TARGET_RECEIPT, $contribution_one->id);
        $route_name = 'get-directlink';
        $route_arguments = ['hash' => $hash];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody(['email' => $member_one->email]);

        $this->expectOutputRegex('/^%PDF-\d\.\d\.');
        $test_response = $this->app->handle($request);
        $expected_headers = [
            'Content-type' => ['application/pdf'],
            'Content-Disposition' => ['attachment;filename="contribution_' . $contribution_one->id . '_receipt.pdf"']
        ];
        $this->expectOK($test_response, $expected_headers);
    }
}
