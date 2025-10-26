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

namespace GaletteTests\Controllers;

use Galette\GaletteRoutingTestCase;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;

/**
 * CSV controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CsvController extends GaletteRoutingTestCase
{
    protected int $seed = 20250912171952;

    /**
     * Set up tests
     *
     * @return void
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
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->zdb = new \Galette\Core\Db();

        $delete = $this->zdb->delete(\Galette\Entity\ScheduledPayment::TABLE);
        $this->zdb->execute($delete);

        $this->cleanContributions();
        $this->cleanMembers();
        $this->cleanHistory();

        //remove model
        $delete = $this->zdb->delete(\Galette\Entity\ImportModel::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Cleanup after class
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        $self = new self(__METHOD__);
        $self->tearDown();
    }

    /**
     * Test export page
     *
     * @return void
     */
    public function testExportPage(): void
    {
        $route_name = 'export';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again once logged-in as superadmin
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'Exports',
            $body
        );
        $this->login->logOut();
    }

    /**
     * Test do export (doExport, get file, remove file)
     *
     * @return void
     */
    public function testDoExport(): void
    {
        $route_name = 'doExport';
        $request = $this->createRequest($route_name, [], 'POST');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again once logged-in as superadmin
        $this->logSuperAdmin();

        //no table to export
        $request = $request->withParsedBody(['export_tables' => []]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('export')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //empty table
        $request = $request->withParsedBody(['export_tables' => ['galette_groups']]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('export')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['warning_detected' => ['Table galette_groups is empty, and has not been exported.']]);

        //populate table
        $group = new \Galette\Entity\Group();
        $group->setName('Group one' . $this->seed);
        $this->assertTrue($group->store());
        $group_one_id = $group->getId();
        $group = new \Galette\Entity\Group();
        $group->setName('Group two' . $this->seed);
        $this->assertTrue($group->store());
        $group_two_id = $group->getId();

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('export')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([
            'written_exports' => [
                sprintf(
                    '<a href="%s">%s</a>',
                    $this->routeparser->urlFor('getCsv', ['type' => 'export', 'file' => 'galette_groups_full.csv']),
                    'galette_groups (galette_groups_full.csv)'
                )
            ]
        ]);

        //get file
        $route_name = 'getCsv';
        $route_arguments = ['type' => 'export', 'file' => 'galette_groups_full.csv'];
        $get_request = $this->createRequest($route_name, $route_arguments, 'GET');
        $test_response = $this->app->handle($get_request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="galette_groups_full.csv"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);
        $body = (string)$test_response->getBody();
        $creation_date = (new \DateTime($group->getCreationDate(false)))->format('Y-m-d H:i:s');
        $this->assertSame(
            sprintf(
                "%s\r\n%s\r\n%s\r\n",
                '"id_group";"group_name";"creation_date";"parent_group"',
                '"' . $group_one_id . '";"Group one' . $this->seed . '";"' . $creation_date . '";""',
                '"' . $group_two_id . '";"Group two' . $this->seed . '";"' . $creation_date . '";""'
            ),
            $body
        );

        //run parameted example export
        $request = $request->withParsedBody(['export_parameted' => ['cotisations']]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('export')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([
            'written_exports' => [
                sprintf(
                    '<a href="%s">%s</a>',
                    $this->routeparser->urlFor('getCsv', ['type' => 'export', 'file' => 'galette_cotisations.csv']),
                    'Cotisations (galette_cotisations.csv)'
                )
            ]
        ]);

        $route_arguments['file'] = 'galette_cotisations.csv';
        $get_request = $this->createRequest($route_name, $route_arguments, 'GET');
        $test_response = $this->app->handle($get_request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="galette_cotisations.csv"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);
        $body = (string)$test_response->getBody();
        $this->assertSame(
            sprintf(
                "%s\r\n",
                '"Name";"Surname";"Town";"Amount";"Begin date";"End date"'
            ),
            $body
        );

        $this->login->logOut();
    }

    /**
     * Test file removal routes
     *
     * @return void
     */
    public function testFileRemoval(): void
    {
        $filename = 'galette_cotisations.csv';

        $route_name = 'removeCsv';
        $route_arguments = ['type' => 'export', 'file' => $filename];
        $request = $this->createRequest($route_name, $route_arguments);
        $do_route_name = 'doRemoveCsv';
        $do_request = $this->createRequest($do_route_name, $route_arguments, 'POST');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        $test_response = $this->app->handle($do_request);
        $this->expectLogin($test_response);

        //test again once logged-in as superadmin
        $this->logSuperAdmin();

        //create export file
        //run parameted example export
        $create_request = $this->createRequest('doExport', [], 'POST');
        $create_request = $create_request->withParsedBody(['export_parameted' => ['cotisations']]);
        $test_response = $this->app->handle($create_request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('export')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([
            'written_exports' => [
                sprintf(
                    '<a href="%s">%s</a>',
                    $this->routeparser->urlFor('getCsv', ['type' => 'export', 'file' => $filename]),
                    'Cotisations (galette_cotisations.csv)'
                )
            ]
        ]);

        //test agian removal call
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'Remove export file ' . $filename,
            $body
        );

        $test_response = $this->app->handle($do_request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Removal has not been confirmed!']]);

        $do_request = $do_request->withParsedBody(['confirm' => 'on']);
        $test_response = $this->app->handle($do_request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['\'' . $filename . '\' file has been removed from disk.']]);

        //make sure file no longer exists
        $route_name = 'getCsv';
        $route_arguments = ['type' => 'import', 'file' => $filename];
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->assertEquals(404, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'A request has been made to get a CSV file named `' . $filename . '` that does not exists');
        $this->expectFlashData([]);

        //Call delete once again, should fail as file does not exist anymore (also test a JSON response
        $do_request = $do_request->withParsedBody(['confirm' => 'on', 'ajax' => 'true']);
        $test_response = $this->app->handle($do_request);
        $this->assertSame(['Content-Type' => ['application/json']], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, $filename . ' does not exists, no way to remove it!');
        $this->expectFlashData(['error_detected' => ['Cannot remove \'' . $filename . '\' from disk :/']]);
        $body = (string)$test_response->getBody();
        $this->assertSame(
            json_encode(
                [
                    'success'  => false
                ]
            ),
            $body
        );
    }

    /**
     * Test import page
     *
     * @return void
     */
    public function testImportPage(): void
    {
        $route_name = 'import';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again once logged-in as superadmin
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'Imports',
            $body
        );
        $this->login->logOut();
    }

    /**
     * Test getFile route
     *
     * @return void
     */
    public function testGetFile(): void
    {
        $member_one = $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $filename = 'testfile-' . $this->seed . '.csv';

        $route_name = 'getCsv';
        $route_arguments = ['type' => 'import', 'file' => $filename];
        $request = $this->createRequest($route_name, $route_arguments, 'GET');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again once logged-in as superadmin
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertEquals(404, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'A request has been made to get a CSV file named `' . $filename . '` that does not exists');
        $this->expectFlashData([]);
        $this->login->logOut();

        //simple member should not be allowed to access this page
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
        $this->login->logOut();

        $staff_member = $this->getStaffMember($member_one);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->resetStaffStatus($staff_member, $member_one);
        $this->assertEquals(404, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'A request has been made to get a CSV file named `' . $filename . '` that does not exists');
        $this->expectFlashData([]);
        $this->login->logOut();

        //create file
        $filepath = GALETTE_IMPORTS_PATH . $filename;
        $contents = '"col1";"col2";"col3"\r\n"val1";"val2";"val3"';
        $this->assertNotFalse(file_put_contents($filepath, $contents));

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertTrue(unlink($filepath));
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="' . $filename . '"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);

        $body = (string)$test_response->getBody();
        $this->assertSame($contents, $body);
        $this->login->logOut();
    }

    /**
     * Test import model page
     *
     * @return void
     */
    public function testImportModelPage(): void
    {
        $route_name = 'importModel';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again once logged-in as superadmin
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'CSV import model',
            $body
        );
        $this->login->logOut();
    }

    /**
     * Test import model routes (store, get)
     *
     * @return void
     */
    public function testImportModel(): void
    {
        $get_route_name = 'getImportModel';
        $get_request = $this->createRequest($get_route_name);

        $store_route_name = 'storeImportModel';
        $store_request = $this->createRequest($store_route_name, [], 'POST');

        //get default model
        $test_response = $this->app->handle($get_request);
        $this->expectLogin($test_response);

        $test_response = $this->app->handle($store_request);
        $this->expectLogin($test_response);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($get_request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="galette_import_model.csv"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);

        $body = (string)$test_response->getBody();
        $this->assertSame(
            '"nom_adh";"prenom_adh";"ddn_adh";"adresse_adh";"cp_adh";"ville_adh";"pays_adh";"tel_adh";"gsm_adh";"email_adh";"prof_adh";"pseudo_adh";"societe_adh";"login_adh";"date_crea_adh";"id_statut";"info_public_adh";"info_adh"',
            trim($body)
        );

        //change model
        $new_fields = ["nom_adh", "ddn_adh", "email_adh"];
        $store_request = $store_request->withParsedBody(
            [
                'fields' => $new_fields
            ]
        );
        $store_response = $this->app->handle($store_request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('importModel')]], $store_response->getHeaders());
        $this->assertSame(301, $store_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Import model has been successfully stored :)']]);

        //check model has been changed
        $test_response = $this->app->handle($get_request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="galette_import_model.csv"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);

        $body = (string)$test_response->getBody();
        $this->assertSame(
            '"' . implode('";"', $new_fields) . '"',
            trim($body)
        );

        $this->login->logOut();
    }

    /**
     * Test members CSV export
     *
     * @return void
     */
    public function testMembersExport(): void
    {
        $fc = $this->container->get(\Galette\Entity\FieldsConfig::class);
        $fc->installInit();
        $controller = $this->container->get(\Galette\Controllers\CsvController::class);
        $filter_name = $controller->getFilterName(\Galette\Controllers\Crud\MembersController::getDefaultFilterName());
        unset($this->session->$filter_name);
        $member_one = $this->getMemberOne();

        $route_name = 'csv-memberslist';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again from filters
        $this->logSuperAdmin();
        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$member_one->id];
        $this->session->$filter_name = $filters;

        $test_response = $this->app->handle($request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="filtered_memberslist.csv"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '"Member id";"Status";"Name";"First name";"Company";"Nickname";"Title";"Birth date";"Gender";"Address";"Zip Code";"City";"Region";"Country";"Phone";"Mobile phone";"E-Mail";"Other information (admin)";"Other information";"Profession";"Username";"Creation date";"Modification date";"Account";"Galette Admin";"Freed of dues";"Be visible on public pages";"Due date";"Language";"Birthplace";"Id GNUpg (GPG)";"fingerprint";"Parent";"Member number"',
            $body
        );
        $now = new \DateTime();
        $this->assertStringContainsString(
            '"' . $member_one->id . '";"Non-member";"Durand";"René";"";"ubertrand";"";"1942-12-26";"Unspecified";"66, boulevard De Oliveira";"39 069";"Martel";"Caribbean";"Antarctique";"0439153432";"";"meunier.josephine20250912171952@ledoux.com";"";"";"Chef de fabrication";"arthur.hamon20250912171952";"2020-06-10";"' . $now->format('Y-m-d') . '";"Yes";"No";"No";"Yes";"";"en_US";"Gonzalez-sur-Meunier";"";"FAKER20250912171952";"";""',
            $body
        );
    }

    /**
     * Test contributions CSV export
     *
     * @return void
     */
    public function testContributionExport(): void
    {
        $controller = $this->container->get(\Galette\Controllers\CsvController::class);
        $filter_name = $controller->getFilterName('contributions', ['suffix' => 'csvexport']);
        unset($this->session->$filter_name);
        $this->getMemberOne();

        $route_name = 'csv-contributionslist';
        $route_arguments = ['type' => 'contributions'];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again from filters
        $this->logSuperAdmin();
        $this->createContribution();
        $filters = new \Galette\Filters\ContributionsList();
        $filters->selected = [$this->contrib->id];
        $this->session->$filter_name = $filters;

        $test_response = $this->app->handle($request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="filtered_contributionslist.csv"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '"Contribution id";"Contributor";"Contribution type";"Amount";"Payment type";"Comments";"Date";"Start date of membership / Date of contribution";"End date of membership";"Transaction ID"',
            $body
        );
        $this->assertStringContainsString(
            '"' . $this->contrib->id . '";"DURAND René";"annual fee";"92.00";"Check";"FAKER20250912171952";"' . $this->contrib->date . '";"' . $this->contrib->begin_date . '";"' . $this->contrib->end_date . '";""',
            $body
        );
    }

    /**
     * Test scheduled payment CSV export
     *
     * @return void
     */
    public function testScheduledPaymentExport(): void
    {
        $controller = $this->container->get(\Galette\Controllers\CsvController::class);
        $filter_name = $controller->getFilterName(\Galette\Controllers\Crud\ScheduledPaymentController::getDefaultFilterName(), ['suffix' => 'csvexport']);
        unset($this->session->$filter_name);
        $member_one = $this->getMemberOne();

        $route_name = 'csv-scheduledPaymentslist';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again from filters
        $this->logSuperAdmin();

        $contrib_data = $this->getContribData();
        $contrib_data['type_paiement_cotis'] = \Galette\Entity\PaymentType::SCHEDULED;
        $contrib_data[\Galette\Entity\Adherent::PK] = $member_one->id;
        $this->createContrib($contrib_data);

        $now = new \DateTime();
        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'amount' => 10.0,
            'comment' => 'FAKER' . $this->seed
        ];

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $filters = new \Galette\Filters\ScheduledPaymentsList();
        $filters->selected = [$scheduledPayment->getId()];
        $this->session->$filter_name = $filters;

        $test_response = $this->app->handle($request);
        $expected_headers = [
            'Content-Description' => ['File Transfer'],
            'Content-Type' => ['text/csv'],
            'Content-Disposition' => ['attachment;filename="filtered_scheduledpaymentslist.csv"'],
            'Pragma' => ['public'],
            'Content-Transfer-Encoding' => ['binary'],
            'Expires' => ['0'],
            'Cache-Control' => ['must-revalidate']
        ];
        $this->expectOK($test_response, $expected_headers);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '"Scheduled payment ID";"Contribution ID";"Payment type";"Record date";"Scheduled date";"Amount";"Paid";"Comments"',
            $body
        );
        $this->assertStringContainsString(
            '"' . $scheduledPayment->getId() . '";"' . $this->contrib->id . '";"Cash";"' . $now->format('Y-m-d') . '";"' . $now->format('Y-m-d') . '";"10.00";"0";"FAKER20250912171952"',
            $body
        );
    }

    /**
     * Test upload and import CSV file
     *
     * @return void
     */
    public function testImportFile(): void
    {
        $route_name = 'uploadImportFile';
        $route_arguments = [];
        $request = $this->createRequest($route_name, $route_arguments, 'POST');

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test again once logged-in as superadmin
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('import')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['warning_detected' => ['No file has been uploaded!']]);

        $this->assertTrue(copy(GALETTE_TESTS_PATH . '/fixtures/import_file.csv', sys_get_temp_dir() . '/uploaded_file.csv'));
        $uploaded_files = [
            'new_file' => new \Slim\Psr7\UploadedFile(
                sys_get_temp_dir() . '/uploaded_file.csv',
                'uploaded_file.csv',
                'text/csv'
            )
        ];
        $request = $request->withUploadedFiles($uploaded_files);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('import')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Your file has been successfully uploaded!']]);

        //check uploaded file
        $this->assertTrue(file_exists(GALETTE_IMPORTS_PATH . '/uploaded_file.csv'));
        $this->assertSame(
            file_get_contents(GALETTE_TESTS_PATH . '/fixtures/import_file.csv'),
            file_get_contents(GALETTE_IMPORTS_PATH . '/uploaded_file.csv')
        );

        //import file
        $model = new \Galette\Entity\ImportModel();
        $model->setFields(['nom_adh', 'prenom_adh', 'ville_adh', 'fingerprint']);
        $this->assertTrue($model->store($this->zdb));

        $members = new \Galette\Repository\Members();
        $this->assertCount(0, $members->getList());

        $route_name = 'doImport';
        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody(['import_file' => 'uploaded_file.csv']);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('import')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['File \'uploaded_file.csv\' has been successfully imported :)']]);

        //2 members in CSV file has been imported
        $this->assertCount(2, $members->getList());

        //remove file
        unlink(GALETTE_IMPORTS_PATH . '/uploaded_file.csv');
        $this->login->logOut();
    }
}
