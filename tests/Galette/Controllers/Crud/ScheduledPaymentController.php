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
 * Contributions controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScheduledPaymentController extends GaletteRoutingTestCase
{
    protected int $seed = 20250906091032;

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
     * Test scheduled payments list
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $contrib_data = $this->getContribData();
        $contrib_data['type_paiement_cotis'] = \Galette\Entity\PaymentType::SCHEDULED;

        $member_one = $this->getMemberOne();
        $contrib_data[\Galette\Entity\Adherent::PK] = $member_one->id;
        $this->createContrib($contrib_data);
        $contrib_one = $this->contrib;

        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());
        $contrib_data[\Galette\Entity\Adherent::PK] = $member_two->id;
        $this->createContrib($contrib_data);
        $contrib_two = $this->contrib;
        $this->login->logOut();

        $route_name = 'scheduledPayments';
        $request = $this->createRequest($route_name);

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with simple member: can show its own scheduled payment only from "my" route
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $contrib_one->member);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        $my_request = $this->createRequest('myScheduledPayments');
        $my_test_response = $this->app->handle($my_request);
        $this->expectOK($my_test_response);
        $body = (string)$my_test_response->getBody();
        //no scheduled payment
        $this->assertStringContainsString(
            'No scheduled payment',
            $body
        );
        $this->login->logOut();

        $this->logSuperAdmin();
        //create scheduled payment for both contributions
        $data = [
            \Galette\Entity\Contribution::PK => $contrib_one->id,
            'amount' => 42,
            'scheduled_date' => date('Y-m-d'),
            'id_paymenttype' => \Galette\Entity\PaymentType::CREDITCARD
        ];
        $scheduled_one = new \Galette\Entity\ScheduledPayment($this->zdb);
        $check = $scheduled_one->check($data);
        $this->assertTrue($check, print_r($scheduled_one->getErrors(), true));
        $store = $scheduled_one->store();
        $this->assertTrue($store);

        $data = [
            \Galette\Entity\Contribution::PK => $contrib_two->id,
            'amount' => 42,
            'scheduled_date' => date('Y-m-d'),
            'id_paymenttype' => \Galette\Entity\PaymentType::CREDITCARD
        ];
        $scheduled_two = new \Galette\Entity\ScheduledPayment($this->zdb);
        $check = $scheduled_two->check($data);
        $this->assertTrue($check, print_r($scheduled_two->getErrors(), true));
        $store = $scheduled_two->store();
        $this->assertTrue($store);
        $this->login->logOut();

        //check again - scheduled payments have been created
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $my_test_response = $this->app->handle($my_request);
        $this->expectOK($my_test_response);
        $body = (string)$my_test_response->getBody();

        //member one scheduled payment is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $scheduled_one->getId()),
            $body
        );

        //member two scheduled payment is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $scheduled_two->getId()),
            $body
        );
        $this->login->logOut();

        $staff_member = $this->getStaffMember($member_one);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isStaff());
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member one scheduled payment is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $scheduled_one->getId()),
            $body
        );
        //member two scheduled payment is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $scheduled_two->getId()),
            $body
        );

        //reset staff status
        $this->resetStaffStatus($staff_member, $member_one);

        $admin_member = $this->getAdminMember($member_one);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member one scheduled payment is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" id="scheduled_checkbox_%1$s" value="%1$s"/>', $scheduled_one->getId()),
            $body
        );
        //member two scheduled payment is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" id="scheduled_checkbox_%1$s" value="%1$s"/>', $scheduled_two->getId()),
            $body
        );

        //reset adim status
        $this->resetAdminStatus($admin_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //by default, group manager can only see its own contributions
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
    }

    /**
     * Test scheduled payment filters
     */
    public function testListFilter(): void
    {
        $this->logSuperAdmin();
        $contrib_data = $this->getContribData();
        $contrib_data['type_paiement_cotis'] = \Galette\Entity\PaymentType::SCHEDULED;

        $member_one = $this->getMemberOne();
        $contrib_data[\Galette\Entity\Adherent::PK] = $member_one->id;
        $this->createContrib($contrib_data);
        $contrib_one = $this->contrib;
        $this->login->logOut();

        $controller = new \Galette\Controllers\Crud\ScheduledPaymentController($this->container);
        $filter_name = $controller->getFilterName($controller->getDefaultFilterName());
        unset($this->session->$filter_name);

        $filterroute_name = 'filterScheduledPayments';
        $request = $this->createRequest($filterroute_name, [], 'POST');
        $request = $request->withParsedBody(['payment_type_filter' => \Galette\Entity\PaymentType::CHECK]);

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        $mdata = $this->dataAdherentOne();

        //staff member can filter scheduled payments standard list
        $staff_member = $this->getStaffMember($member_one);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isStaff());
        $request = $this->createRequest($filterroute_name, [], 'POST');
        $request = $request->withParsedBody(['payment_type_filter' => \Galette\Entity\PaymentType::CHECK]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('scheduledPayments')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $filter = $this->session->$filter_name;
        $this->assertInstanceOf(\Galette\Filters\ScheduledPaymentsList::class, $filter);
        $this->assertSame(\Galette\Entity\PaymentType::CHECK, $filter->payment_type_filter);
        unset($this->session->$filter_name);

        $this->resetStaffStatus($staff_member, $member_one);

        //test with simple member: can show its own scheduled payment only from "my" route
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $contrib_one->member);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        $my_request = $this->createRequest('filterMyScheduledPayments', [], 'POST');
        $my_request = $my_request->withParsedBody(['payment_type_filter' => \Galette\Entity\PaymentType::TRANSFER]);
        $my_test_response = $this->app->handle($my_request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('myScheduledPayments')]], $my_test_response->getHeaders());
        $this->assertSame(301, $my_test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //make sure filter has been updated in session
        $filter = $this->session->$filter_name;
        $this->assertInstanceOf(\Galette\Filters\ScheduledPaymentsList::class, $filter);
        $this->assertSame(\Galette\Entity\PaymentType::TRANSFER, $filter->payment_type_filter);
        unset($this->session->$filter_name);
        $this->login->logOut();
    }

    /**
     * Test scheduled payment add page
     */
    public function testAddPage(): void
    {
        $this->logSuperAdmin();
        $contrib_data = $this->getContribData();
        $contrib_data['type_paiement_cotis'] = \Galette\Entity\PaymentType::SCHEDULED;

        $member_one = $this->getMemberOne();
        $contrib_data[\Galette\Entity\Adherent::PK] = $member_one->id;
        $this->createContrib($contrib_data);
        $contrib_one = $this->contrib;

        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());
        $this->login->logOut();

        $route_name = 'addScheduledPayment';
        $route_arguments = [\Galette\Entity\Contribution::PK => (string)$contrib_one->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access add page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
        $this->login->logout();

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        $this->login->logOut();
        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        $this->login->logOut();
        //reset admin status
        $this->resetAdminStatus($adm_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //with default preferences, groups manager cannot access add page
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
        $this->login->logout();
    }
}
