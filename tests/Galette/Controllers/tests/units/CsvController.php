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
        $this->assertStringContainsString(
            '"' . $member_one->id . '";"Non-member";"Durand";"René";"";"ubertrand";"";"1942-12-26";"Unspecified";"66, boulevard De Oliveira";"39 069";"Martel";"Caribbean";"Antarctique";"0439153432";"";"meunier.josephine20250912171952@ledoux.com";"";"";"Chef de fabrication";"arthur.hamon20250912171952";"2020-06-10";"2025-09-13";"Yes";"No";"No";"Yes";"";"en_US";"Gonzalez-sur-Meunier";"";"FAKER20250912171952";"";""',
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
            'Content-Disposition' => ['attachment;filename="filtered_shceduledpaymentslist.csv"'],
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
}
