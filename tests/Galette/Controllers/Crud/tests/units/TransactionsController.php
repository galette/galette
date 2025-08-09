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
 * Transactions controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TransactionsController extends GaletteRoutingTestCase
{
    protected int $seed = 20250716083517;

    private \Galette\Entity\Transaction $transaction;

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

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Transaction::TABLE);
        $delete->where(['trans_desc' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSUSERS_TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSMANAGERS_TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        parent::tearDown();
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
     * Create test transactions in database
     *
     * @param array $data Data to set
     *
     * @return \Galette\Entity\Transaction
     */
    private function createTransaction(array $data = []): \Galette\Entity\Transaction
    {
        $date = new \DateTime();
        $data = array_merge(
            [
                'id_adh' => $this->adh->id,
                'trans_date' => $date->format('Y-m-d'),
                'trans_amount' => 92,
                'payment_type' => 3, //bank check
            ],
            $data
        );
        $data['trans_desc'] = 'FAKER' . $this->seed;

        $this->transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $check = $this->transaction->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $this->transaction->store($this->history);
        $this->assertTrue($store);
        return $this->transaction;
    }

    /**
     * Test transactions list
     *
     * @return void
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $transaction_one = $this->createTransaction();

        $member_two = $this->getMemberTwo();
        $transaction_two = $this->createTransaction();
        $this->login->logOut();

        $route_name = 'contributions';
        $route_arguments = ['type' => 'transactions'];
        $request = $this->createRequest($route_name, $route_arguments);

        $controller = new \Galette\Controllers\Crud\TransactionsController($this->container);
        $filter_name = $controller->getFilterName('transactions');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //test with simple member: can show its own transactions only
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $transaction_one->member);
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">1 transaction</div>',
            $body
        );
        //member transaction is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );

        $this->logSuperAdmin();
        //add a child member to first member
        $child_data = [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $member_one->id,
            'attach'        => true,
            'login_adh'     => 'child.johny.doe',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $child = $this->createMember($child_data);
        $cid = $child->id;

        $data = [
            'id_adh' => $cid,
            'trans_date' => '2020-05-01',
            'trans_amount' => 42,
        ];
        $child_transaction = $this->createTransaction($data);
        $this->login->logOut();

        //simple member can show its own contributions and children ones
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $transaction_one->member);

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">2 transactions</div>',
            $body
        );
        //member transaction is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            (string)$child_transaction->id,
            $body
        );

        //when showing "my transactions"; shows only member transactions
        $request = $this->createRequest('myContributions', $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">1 transaction</div>',
            $body
        );
        //member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );

        //cannot show contributions of another member
        $request = $this->createRequest($route_name, $route_arguments + ['option' => 'member', 'value' => $member_two->id]);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, sprintf('Trying to display transactions for member #%1$s without appropriate ACLs', $member_two->id));
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">1 transaction</div>',
            $body
        );
        //member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );

        //can show transactions of children
        $request = $this->createRequest($route_name, $route_arguments + ['option' => 'member', 'value' => $child->id]);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">1 transaction</div>',
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            (string)$child_transaction->id,
            $body
        );
        $this->login->logout();

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertSame($m2data['login_adh'], $member_two->login);
        $this->assertSame($member_two->id, $transaction_two->member);

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        //$this->expectNoLogEntry();
        //FIXME: should not happen
        $this->expectLogEntry(\Analog::WARNING, sprintf('Trying to display transactions for member #%1$s without appropriate ACLs', $child->id));
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">1 transaction</div>',
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_two->id,
            $body
        );
        $this->login->logOut();

        //set member as staff
        $staff_member = clone $member_one;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">3 transactions</div>',
            $body
        );
        //member one contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            (string)$child_transaction->id,
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_two->id,
            $body
        );

        //reset staff status
        $check = $staff_member->check(['id_statut' => $member_one->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());
        $this->login->logout();

        //set member as admin
        $adm_member = clone $member_one;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">3 transactions</div>',
            $body
        );
        //member one contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            (string)$child_transaction->id,
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_two->id,
            $body
        );

        //reset admin status
        $check = $adm_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two, $child]));

        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //by default, group manager can only see its own transactions
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">1 transaction</div>',
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_two->id,
            $body
        );

        //change preferences so managers can see group members contributions
        $this->preferences->pref_bool_groupsmanagers_see_transactions = true;
        $this->assertTrue($this->preferences->store());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_see_transactions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">3 transactions</div>',
            $body
        );
        //member one contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            (string)$child_transaction->id,
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_two->id,
            $body
        );
    }

    /**
     * Test transactions filters
     *
     * @return void
     */
    public function testListFilter(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $transaction_one = $this->createTransaction();
        $this->login->logOut();

        $filterroute_name = 'filterContributions';
        $filterroute_arguments = ['type' => 'transactions'];

        //filter request with correct transaction
        $request = $this->createRequest($filterroute_name, $filterroute_arguments, 'POST');
        $request = $request->withParsedBody(['end_date_filter' => $transaction_one->date]);

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $transaction_one->member);

        $route_name = 'contributions';
        $route_arguments = ['type' => 'transactions'];

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor($route_name, $route_arguments)]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['slimFlash' => []], $this->flash_data);

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['slimFlash' => []], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">1 transaction</div>',
            $body
        );
        //member contribution is listed
        $this->assertStringContainsString(
            (string)$transaction_one->id,
            $body
        );

        //filter request to exclude transaction
        $request = $this->createRequest($filterroute_name, $filterroute_arguments, 'POST');
        $request = $request->withParsedBody(['end_date_filter' => '2020-01-01']);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor($route_name, $route_arguments)]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['slimFlash' => []], $this->flash_data);

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['slimFlash' => []], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            '<div class="ui label">0 transactions</div>',
            $body
        );
    }

    /**
     * Test contributions add page
     *
     * @return void
     */
    public function testAddPage(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $route_name = 'addTransaction';

        //login is required to access this page
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //super-admin can access add page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $this->flash_data = [];
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logout();

        //set member as staff
        $staff_member = clone $member_one;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);

        $this->login->logOut();
        //reset statut
        $check = $staff_member->check(['id_statut' => $member_one->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        //set member as admin
        $adm_member = clone $member_one;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);

        $this->login->logOut();
        //reset admin status
        $check = $adm_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

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
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Trying to add transaction without appropriate ACLs');
        $this->assertSame([], $this->flash_data);

        //change preferences so managers can see group members contributions
        $this->preferences->pref_bool_groupsmanagers_create_transactions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_transactions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);

        $this->login->logout();

        $this->logSuperAdmin();
        //add a new member
        $member_three_data = [
            'nom_adh'       => 'Nongroupmember',
            'prenom_adh'    => 'Joe',
            'login_adh'     => 'non.group.member',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $member_three = $this->createMember($member_three_data);
        $this->login->logout();

        //simulate error while storing, values are kept in session
        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $date = new \DateTime();
        $tdata = [
            'id_adh' => $member_three->id, //member not part of "Group 1"
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'payment_type' => 3, //bank check
            'trans_desc' => 'FAKER' . $this->seed
        ];
        $check = $transaction->check($tdata, [], []);
        $this->assertSame(
            [
                '- Please select a member from a group you manage.',
                //'- Mandatory field <a href="#id_adh">Contributor</a> empty.'
            ],
            $check
        );
        $this->expectLogEntry(\Analog::ERROR, 'Please select a member from a group you manage');
        $this->session->transaction = $transaction;

        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        //change preferences so managers can create transactions
        $this->preferences->pref_bool_groupsmanagers_create_transactions = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_transactions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Transaction (creation)', $body);
        //member_one is listed
        $this->assertStringContainsString(
            $member_one->getNameWithCase(
                $member_one->name,
                $member_one->surname,
                false,
                (int)$member_one->id,
                $member_one->nickname
            ),
            $body
        );
        //member_two is listed
        $this->assertStringContainsString(
            $member_two->getNameWithCase(
                $member_two->name,
                $member_two->surname,
                false,
                (int)$member_two->id,
                $member_two->nickname
            ),
            $body
        );
        //member_three is not listed
        $this->assertStringNotContainsStringIgnoringCase($member_three->name, $body);

        $this->login->logout();
    }

    /**
     * Test transactions edit page
     *
     * @return void
     */
    public function testEditPage(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $this->logSuperAdmin();
        $this->createTransaction();
        $this->login->logout();

        $route_name = 'editTransaction';
        $route_arguments = ['id' => $this->transaction->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //super-admin can access add page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $this->flash_data = [];

        //transaction that does not exist
        $request = $this->createRequest($route_name, ['id' => 999999] + $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'No transaction #999999');
        $this->assertSame(['error_detected' => ['Unable to load transaction #999999!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logout();

        //set member as staff
        $staff_member = clone $member_one;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);

        $this->login->logOut();
        //reset statut
        $check = $staff_member->check(['id_statut' => $member_one->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        //set member as admin
        $adm_member = clone $member_one;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $body = (string)$test_response->getBody();
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        //check for submit form requirements
        $this->assertStringContainsString('<button type="submit" name="valid"', $body, 'Submit button not found');
        $this->assertStringContainsString('<input type="hidden" name="csrf_name"', $body, 'CSRF name field not found');
        $this->assertStringContainsString('<input type="hidden" name="csrf_value"', $body, 'CSRF value field not found');

        $this->login->logOut();
        //reset admin status
        $check = $adm_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager: refused per default configuration
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(
            \Analog::WARNING,
            'Trying to edit transaction without appropriate ACLs'
        );
        $this->assertSame([], $this->flash_data);
        $this->flash_data = [];

        //change preferences so managers can access transaction edit page
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $body = (string)$test_response->getBody();

        //Reset
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());

        //groups manager: allowed to display edit page per configuration - to attach/detach contributions
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $this->flash_data = [];

        $this->assertStringNotContainsString(
            'Remove transaction',
            $body
        );
        //check for submit form requirements
        $this->assertStringNotContainsString('<button type="submit" name="valid"', $body, 'Submit button found!');
        $this->assertStringNotContainsString('<input type="hidden" name="csrf_name"', $body, 'CSRF name field found!');
        $this->assertStringNotContainsString('<input type="hidden" name="csrf_value"', $body, 'CSRF value field found!');

        //all attachments features are present
        $this->assertStringContainsString($this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]), $body);
        $this->assertStringContainsString($this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_DONATION]), $body);
        $this->assertStringContainsString('id="contribslist"', $body);

        //change preferences so managers cannot create contributions but can see them
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $body = (string)$test_response->getBody();

        //Reset
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->assertTrue($this->preferences->store());

        //all attachments features are present
        $this->assertStringNotContainsString($this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]), $body);
        $this->assertStringNotContainsString($this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_DONATION]), $body);
        $this->assertStringContainsString('id="contribslist"', $body);

        $this->login->logout();
    }

    /**
     * Test add transactions
     *
     * @return void
     */
    public function testAddTransaction(): void
    {
        $member_one = $this->getMemberOne();
        $date = new \DateTime();
        $transaction_data = [
            'id_adh' => $this->adh->id,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'payment_type' => 3, //bank check
            'trans_desc' => 'FAKER' . $this->seed
        ];
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $route_name = 'doAddTransaction';

        $expected_count = 0;
        $count_select = $this->zdb->select(\Galette\Entity\Transaction::TABLE);
        $remove_contributions = $this->zdb->delete(\Galette\Entity\Transaction::TABLE);
        $result = $this->zdb->execute($count_select);
        $this->assertCount($expected_count, $result);

        //login is required to access this page
        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody($transaction_data);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result); //no contribution added

        //super-admin can access add page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Transaction has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logout();

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one contribution added by super-admin
        $this->assertCount(1, $this->zdb->execute($remove_contributions));

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logout();

        $result = $this->zdb->execute($count_select);
        $this->assertCount($expected_count, $result); //no new contribution

        //set member as staff
        $staff_member = clone $member_one;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Transaction has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset statut
        $check = $staff_member->check(['id_statut' => $member_one->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one contribution added by staff member
        $this->assertCount(1, $this->zdb->execute($remove_contributions));

        //set member as admin
        $adm_member = clone $member_one;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Transaction has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset admin status
        $check = $adm_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one contribution added by admin member
        $this->assertCount(1, $this->zdb->execute($remove_contributions));

        $this->logSuperAdmin();
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $new_data = [
            'nom_adh'       => 'Alone',
            'prenom_adh'    => 'Cowboy',
            'attach'        => true,
            'login_adh'     => 'cow.boy',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $member_new = $this->createMember($new_data);
        $this->login->logout();

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //with default preferences, groups manager cannot access add page
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Trying to add transaction without appropriate ACLs');
        $this->assertSame([], $this->flash_data);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result); //no transaction added

        //change preferences so managers can create group members contributions
        $this->preferences->pref_bool_groupsmanagers_create_transactions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_transactions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Transaction has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //Test group manager cannot create contribution for a member he do not own
        $transaction_data['id_adh'] = $member_new->id; //set contribution for new member
        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody($transaction_data);

        //change preferences so managers can create group members contributions
        $this->preferences->pref_bool_groupsmanagers_create_transactions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_transactions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor($route_name)]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(
            \Analog::ERROR,
            'Please select a member from a group you manage.'
        );
        $this->assertSame(
            [
                'error_detected' => [
                    '- Please select a member from a group you manage.',
                    '- Mandatory field <a href="#id_adh">Originator</a> empty.'
                ]
            ],
            $this->flash_data['slimFlash']
        );
        $this->flash_data = [];

        $this->login->logout();

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one contribution added by admin member
        $this->assertCount(1, $this->zdb->execute($remove_contributions));
    }

    /**
     * Test edit transactions
     *
     * @return void
     */
    public function testEditTransaction(): void
    {
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $date = new \DateTime();
        $transaction_data = [
            'id_adh' => $this->adh->id,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'payment_type' => 3, //bank check
            'trans_desc' => 'FAKER' . $this->seed
        ];
        $this->createTransaction($transaction_data);
        $this->login->logout();

        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $route_name = 'doEditTransaction';
        $route_arguments = ['id' => $this->transaction->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody($transaction_data);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //super-admin can access add page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Transaction has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logout();

        //set member as staff
        $staff_member = clone $member_one;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Transaction has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset statut
        $check = $staff_member->check(['id_statut' => $member_one->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        //set member as admin
        $adm_member = clone $member_one;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Transaction has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset admin status
        $check = $adm_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager cannot edit transactions
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Trying to edit transaction without appropriate ACLs');
        $this->assertSame([], $this->flash_data);

        //change preferences so managers can see and create group members contributions
        $this->preferences->pref_bool_groupsmanagers_see_transactions = true;
        $this->preferences->pref_bool_groupsmanagers_create_transactions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_see_transactions = false;
        $this->preferences->pref_bool_groupsmanagers_create_transactions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Trying to edit transaction without appropriate ACLs');
        $this->assertSame([], $this->flash_data);
        $this->flash_data = [];

        $this->login->logout();
    }

    /**
     * Test remove transaction page
     *
     * @return void
     */
    public function testRemovePage(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $transaction_one = $this->createTransaction();
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());
        $this->login->logOut();

        $route_name = 'removeContribution';
        $route_arguments = ['type' => 'transactions', 'id' => $transaction_one->id];

        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove transaction #' . $transaction_one->id, $body);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $transaction_one->member);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //set member as staff
        $staff_member = clone $member_one;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove transaction #' . $transaction_one->id, $body);

        $this->login->logOut();
        //reset statut
        $check = $staff_member->check(['id_statut' => $member_one->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        //set member as admin
        $adm_member = clone $member_one;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove transaction #' . $transaction_one->id, $body);

        $this->login->logOut();
        //reset admin status
        $check = $adm_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
    }

    /**
     * Test delete transaction
     *
     * @return void
     */
    public function testDeleteTransaction(): void
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

        $transaction_two = $this->createTransaction();
        $member_one = $this->getMemberOne();
        $transaction_one = $this->createTransaction();
        $this->login->logOut();

        $route_name = 'doRemoveContribution';
        $route_arguments = ['type' => 'transactions'];

        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody(['id' => [$transaction_one->id, $transaction_two->id]]);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //test with logged-in user
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Removal has not been confirmed!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //make sure transactions still exists
        $select = $this->zdb->select(\Galette\Entity\Transaction::TABLE)
            ->where(['trans_id' => [$transaction_one->id, $transaction_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(2, $result);

        $request = $request->withParsedBody(['id' => [$transaction_one->id, $transaction_two->id], 'confirm' => true]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Successfully deleted!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //make sure contributions no longer exists
        $select = $this->zdb->select(\Galette\Entity\Transaction::TABLE)
            ->where(['trans_id' => [$transaction_one->id, $transaction_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(0, $result);
        $this->login->logout();

        $request = $this->createRequest($route_name, $route_arguments, 'POST');

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $transaction_one->member);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //set member as staff
        $this->logSuperAdmin();
        $trans = $this->createTransaction();
        $this->login->logOut();
        $request = $request->withParsedBody(['id' => [$trans->id], 'confirm' => true]);

        $staff_member = clone $member_one;
        $check = $staff_member->check(['id_statut' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Successfully deleted!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset statut
        $check = $staff_member->check(['id_statut' => $member_one->status], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($staff_member->store());

        //set member as admin
        $this->logSuperAdmin();
        $trans = $this->createTransaction();
        $this->login->logOut();
        $request = $request->withParsedBody(['id' => [$trans->id], 'confirm' => true]);

        $adm_member = clone $member_one;
        $check = $adm_member->check(['bool_admin_adh' => '1'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'transactions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Successfully deleted!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset admin status
        $check = $adm_member->check(['bool_admin_adh' => '0'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($adm_member->store());

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['You do not have permission for requested URL.']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
    }

    /**
     * Test attach contribution
     *
     * @return void
     */
    public function testAttachContribution(): void
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
        $contrib_data = $this->getContribData();
        $contrib_data['id_type_cotis'] = 5; //annual fee or donation in money
        $contribution_two = $this->createContrib($contrib_data);
        $transaction_two = $this->createTransaction();
        $this->createContribution();
        $contribution_one = $this->contrib;
        $transaction_one = $this->createTransaction();
        $this->login->logOut();

        $route_name = 'attach_contribution';
        $route_arguments = [
            'id' => $transaction_one->id,
            'cid' => $contribution_one->id
        ];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //test with logged-in user
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('editTransaction', ['id' => $transaction_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully attached to current transaction']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //reload and check attachment
        $this->assertTrue($contribution_one->load($contribution_one->id));
        $this->assertTrue($contribution_one->isTransactionPartOf($transaction_one->id));
        //Detach and reload
        $this->assertTrue($contribution_one->unsetTransactionPart($transaction_one->id));
        $this->assertTrue($contribution_one->load($contribution_one->id));
        $this->assertFalse($contribution_one->isTransactionPart());
        $this->login->logOut();

        $route_arguments = [
            'id' => $transaction_two->id,
            'cid' => $contribution_two->id
        ];
        $request = $this->createRequest($route_name, $route_arguments);

        //test with group manager
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Unable to attach contribution to transaction']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //groups manager: refused from authenticate middleware
        //change preferences so managers can see group members contributions and see transactions
        $this->preferences->pref_bool_groupsmanagers_see_transactions = true;
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);
        //Reset preferences
        $this->preferences->pref_bool_groupsmanagers_see_transactions = false;
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully attached to current transaction']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
    }

    /**
     * Test deattach contribution
     *
     * @return void
     */
    public function testDetachContribution(): void
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
        $contrib_data = $this->getContribData();
        $contrib_data['id_type_cotis'] = 5; //annual fee or donation in money
        $contribution_two = $this->createContrib($contrib_data);
        $transaction_two = $this->createTransaction();
        $this->assertTrue($contribution_two->setTransactionPart($transaction_two->id));
        $this->createContribution();
        $contribution_one = $this->contrib;
        $transaction_one = $this->createTransaction();
        $this->assertTrue($contribution_one->setTransactionPart($transaction_one->id));
        $this->login->logOut();

        $route_name = 'detach_contribution';
        $route_arguments = [
            'id' => $transaction_one->id,
            'cid' => $contribution_one->id
        ];
        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Login required']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        //reload and check attachment
        $this->logSuperAdmin();
        $this->assertTrue($contribution_one->load($contribution_one->id));
        $this->assertTrue($contribution_one->isTransactionPartOf($transaction_one->id));
        $this->login->logOut();

        //test with logged-in user
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('editTransaction', ['id' => $transaction_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully detached from current transaction']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //reload and check detachment
        $this->assertTrue($contribution_one->load($contribution_one->id));
        $this->assertFalse($contribution_one->isTransactionPartOf($transaction_one->id));
        //Attach and reload
        $this->assertTrue($contribution_one->setTransactionPart($transaction_one->id));
        $this->assertTrue($contribution_one->load($contribution_one->id));
        $this->assertTrue($contribution_one->isTransactionPartOf($transaction_one->id));
        $this->login->logOut();

        $route_arguments = [
            'id' => $transaction_two->id,
            'cid' => $contribution_two->id
        ];
        $request = $this->createRequest($route_name, $route_arguments);

        //test with group manager
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Unable to detach contribution from transaction']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //reload and check attachment
        $this->logSuperAdmin();
        $this->assertTrue($contribution_two->load($contribution_two->id));
        $this->assertTrue($contribution_two->isTransactionPartOf($transaction_two->id));
        $this->login->logOut();

        //groups manager: refused from authenticate middleware
        //change preferences so managers can see group members contributions and see transactions
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));

        $this->preferences->pref_bool_groupsmanagers_see_transactions = true;
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);
        //Reset preferences
        $this->preferences->pref_bool_groupsmanagers_see_transactions = false;
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully detached from current transaction']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logOut();

        //reload and check detachment
        $this->logSuperAdmin();
        $this->assertTrue($contribution_two->load($contribution_two->id));
        $this->assertFalse($contribution_two->isTransactionPartOf($transaction_two->id));
        $this->login->logOut();
    }
}
