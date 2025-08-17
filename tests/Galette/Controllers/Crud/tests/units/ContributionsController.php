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
 * Contributions controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionsController extends GaletteRoutingTestCase
{
    protected int $seed = 20250705080320;

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

        $this->cleanContributions();

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
     * Test contributions list
     *
     * @return void
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $contrib_one = $this->contrib;

        $member_two = $this->getMemberTwo();
        $this->createContribution();
        $contrib_two = $this->contrib;
        $this->login->logOut();

        $route_name = 'contributions';
        $route_arguments = ['type' => 'contributions'];
        $request = $this->createRequest($route_name, $route_arguments);

        $controller = new \Galette\Controllers\Crud\ContributionsController($this->container);
        $filter_name = $controller->getFilterName('contributions');

        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with simple member: can show its own contributions only
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $contrib_one->member);
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //second member contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
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
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => 22,
            'type_paiement_cotis' => 1,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => '2020-05-01',
            'date_debut_cotis' => '2020-05-01',
            'date_fin_cotis' => '2021-04-30',
        ];
        $child_contrib = $this->createContrib($data);
        $this->login->logOut();

        //simple member can show its own contributions and children ones
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $contrib_one->member);

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
            $body
        );

        //when showing "my contributions"; shows only member contributions
        $request = $this->createRequest('myContributions', $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
            $body
        );

        //cannot show contributions of another member
        $request = $this->createRequest($route_name, $route_arguments + ['option' => 'member', 'value' => $member_two->id]);
        $test_response = $this->app->handle($request);
        $this->expectLogEntry(\Analog::WARNING, sprintf('Trying to display contributions for member #%1$s without appropriate ACLs', $member_two->id));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
            $body
        );

        //can show contributions of children
        $request = $this->createRequest($route_name, $route_arguments + ['option' => 'member', 'value' => $child->id]);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
            $body
        );
        $this->login->logout();

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertSame($m2data['login_adh'], $member_two->login);
        $this->assertSame($member_two->id, $contrib_two->member);

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        //FIXME: should not happen
        $this->expectLogEntry(\Analog::WARNING, sprintf('Trying to display contributions for member #%1$s without appropriate ACLs', $child->id));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member one contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
            $body
        );
        $this->login->logOut();

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member one contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $contrib_two->id),
            $body
        );
        $this->login->logout();

        //reset staff status
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member one contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="checkbox" name="entries_sel[]" value="%1$s"/>', $contrib_two->id),
            $body
        );

        //reset admin status
        $this->resetAdminStatus($adm_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two, $child]));

        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //by default, group manager can only see its own contributions
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member one contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
            $body
        );

        //change preferences so managers can see group members contributions
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->assertTrue($this->preferences->store());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member one contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
        //member child contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $child_contrib->id),
            $body
        );
        //second member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_two->id),
            $body
        );
    }

    /**
     * Test contributions filters
     *
     * @return void
     */
    public function testListFilter(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $contrib_one = $this->contrib;
        $this->login->logOut();

        $filterroute_name = 'filterContributions';
        $filterroute_arguments = ['type' => 'contributions'];

        //filter request with correct payment type (3)
        $request = $this->createRequest($filterroute_name, $filterroute_arguments, 'POST');
        $request = $request->withParsedBody(['payment_type_filter' => '3']);

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $contrib_one->member);

        $route_name = 'contributions';
        $route_arguments = ['type' => 'contributions'];

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor($route_name, $route_arguments)]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['slimFlash' => []], $this->flash_data);
        $this->flash_data = [];

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member contribution is listed
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );

        //filter request with another payment type (not 3)
        $request = $this->createRequest($filterroute_name, $filterroute_arguments, 'POST');
        $request = $request->withParsedBody(['payment_type_filter' => '1']);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => ['/contributions']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame([], $this->flash_data);

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member contribution is not listed
        $this->assertStringNotContainsString(
            sprintf('<input type="hidden" name="contrib_id" value="%1$s"/>', $contrib_one->id),
            $body
        );
    }

    /**
     * Data provider for contribution type
     *
     * @return array<int, array<string, int|string>>
     */
    public static function contributionTypeProvider(): array
    {
        return [
            ['type' => \Galette\Entity\Contribution::TYPE_FEE],
            ['type' => \Galette\Entity\Contribution::TYPE_DONATION]
        ];
    }

    /**
     * Test contributions add page
     *
     * @param string $type Contribution type
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('contributionTypeProvider')]
    public function testAddPage(string $type): void
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

        $route_name = 'addContribution';
        $route_arguments = ['type' => $type];

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
        $this->assertSame(['Location' => ['/']], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Trying to add contribution without appropriate ACLs');
        $this->assertSame([], $this->flash_data);

        //change preferences so managers can create contributions
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);

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
        $cdata = $this->getContribData();
        $cdata['id_type_cotis'] = 5; //donation
        $cdata['id_adh'] = $member_three->id; //member not part of "Group 1"

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            [
                'type' => $cdata['id_type_cotis'], //donation
                'adh' => $cdata['id_adh'],  //member not part of "Group 1"
            ]
        );
        $check = $contrib->check($cdata, $contrib->getRequired(), []);
        $this->assertSame(
            [
                '- Please select a member from a group you manage.',
                '- Mandatory field <a href="#id_adh">Contributor</a> empty.'
            ],
            $check
        );
        $this->expectLogEntry(\Analog::ERROR, 'Please select a member from a group you manage');
        $this->session->contribution = $contrib;

        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        //change preferences so managers can create contributions
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('(creation)', $body);
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
     * Test contributions edit page
     *
     * @param string $type Contribution type
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('contributionTypeProvider')]
    public function testEditPage(string $type): void
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
        $contrib_data = $this->getContribData();
        $contrib_data['id_type_cotis'] = $type === \Galette\Entity\Contribution::TYPE_FEE ? 1 : 5; //annual fee or donation in money
        $this->createContrib($contrib_data);
        $expected = [];
        if ($type === \Galette\Entity\Contribution::TYPE_DONATION) {
            $expected = [
                'id_type_cotis' => 5,
                'row_class' => 'active-account cotis-never',
                'type' => \Galette\Entity\Contribution::TYPE_DONATION
            ];
        }
        $this->checkContribExpected(null, $expected);
        $this->login->logout();

        $route_name = 'editContribution';
        $route_arguments = ['type' => $type, 'id' => $this->contrib->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access add page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        //contribution that does not exists
        $request = $this->createRequest($route_name, ['id' => 999999] + $route_arguments);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions'])]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'No contribution #999999');
        $this->assertSame(['error_detected' => ['Unable to load contribution #999999!']], $this->flash_data['slimFlash']);
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

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //change preferences so managers can see and create group members contributions
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->expectAuthMiddlewareRefused($test_response);

        $this->login->logout();
    }

    /**
     * Test add contributions
     *
     * @param string $type Contribution type
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('contributionTypeProvider')]
    public function testAddContribution(string $type): void
    {
        $member_one = $this->getMemberOne();
        $contrib_data = $this->getContribData();
        $contrib_data['id_type_cotis'] = $type === \Galette\Entity\Contribution::TYPE_FEE ? 1 : 5; //annual fee or donation in money
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $route_name = 'doAddContribution';
        $route_arguments = ['type' => $type];

        $expected_count = 0;
        $count_select = $this->zdb->select(\Galette\Entity\Contribution::TABLE);
        $remove_contributions = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $result = $this->zdb->execute($count_select);
        $this->assertCount($expected_count, $result);

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody($contrib_data);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result); //no contribution added

        //super-admin can access add page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions']) . '?id_adh=' . $member_one->id]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully stored']], $this->flash_data['slimFlash']);
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
        $this->expectAuthMiddlewareRefused($test_response);
        $this->login->logout();

        $result = $this->zdb->execute($count_select);
        $this->assertCount($expected_count, $result); //no new contribution

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions']) . '?id_adh=' . $member_one->id]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one contribution added by staff member
        $this->assertCount(1, $this->zdb->execute($remove_contributions));

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions']) . '?id_adh=' . $member_one->id]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset admin status
        $this->resetAdminStatus($adm_member);

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
        $this->expectLogEntry(\Analog::WARNING, 'Trying to add contribution without appropriate ACLs');
        $this->assertSame([], $this->flash_data);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result); //no contribution added

        //change preferences so managers can create group members contributions
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //Test group manager cannot create contribution for a member he do not own
        $contrib_data['id_adh'] = $member_new->id; //set contribution for new member
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody($contrib_data);

        //change preferences so managers can create group members contributions
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor($route_name, $route_arguments)]],
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
                    '- Mandatory field <a href="#id_adh">Contributor</a> empty.'
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
     * Test edit contributions
     *
     * @param string $type Contribution type
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('contributionTypeProvider')]
    public function testEditContribution(string $type): void
    {
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $contrib_data = $this->getContribData();
        $contrib_data['id_type_cotis'] = $type === \Galette\Entity\Contribution::TYPE_FEE ? 1 : 5; //annual fee or donation in money
        $this->createContrib($contrib_data);
        $expected = [];
        if ($type === \Galette\Entity\Contribution::TYPE_DONATION) {
            $expected = [
                'id_type_cotis' => 5,
                'row_class' => 'active-account cotis-never',
                'type' => \Galette\Entity\Contribution::TYPE_DONATION
            ];
        }
        $this->checkContribExpected(null, $expected);
        $this->login->logout();

        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $route_name = 'doEditContribution';
        $route_arguments = ['type' => $type, 'id' => $this->contrib->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody($contrib_data);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access add page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions']) . '?id_adh=' . $member_one->id]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
        $this->login->logout();

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions']) . '?id_adh=' . $member_one->id]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];
        $this->login->logOut();

        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions']) . '?id_adh=' . $member_one->id]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Contribution has been successfully stored']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

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

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //change preferences so managers can see and create group members contributions
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());

        $this->expectAuthMiddlewareRefused($test_response);

        $this->login->logout();
    }

    /**
     * Test remove contribution page
     *
     * @return void
     */
    public function testRemovePage(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $contrib_one = $this->contrib;
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        $this->createContribution();
        $contrib_two = $this->contrib;
        $this->login->logOut();

        $route_name = 'removeContribution';
        $route_arguments = ['type' => 'contributions', 'id' => $contrib_one->id];

        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove contribution #' . $contrib_one->id, $body);
        $this->expectOK($test_response);

        $batch_request = $this->createRequest('removeContributions', ['type' => 'contributions']);
        $batch_request = $batch_request->withParsedBody([
            'id' => [
                $contrib_one->id,
                $contrib_two->id
            ]
        ]);
        $test_response = $this->app->handle($batch_request);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove 2 contributions', $body);
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $contrib_one->member);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove contribution #' . $contrib_one->id, $body);

        $this->login->logOut();
        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove contribution #' . $contrib_one->id, $body);

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

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
    }

    /**
     * Test delete contribution
     *
     * @return void
     */
    public function testDeleteContribution(): void
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

        $this->createContribution();
        $contrib_two = $this->contrib;
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $contrib_one = $this->contrib;
        $this->login->logOut();

        $route_name = 'doRemoveContribution';
        $route_arguments = ['type' => 'contributions'];

        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody(['id' => [$contrib_one->id, $contrib_two->id]]);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['error_detected' => ['Removal has not been confirmed!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //make sure contributions still exists
        $select = $this->zdb->select(\Galette\Entity\Contribution::TABLE)
            ->where(['id_cotis' => [$contrib_one->id, $contrib_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(2, $result);

        $request = $request->withParsedBody(['id' => [$contrib_one->id, $contrib_two->id], 'confirm' => true]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Successfully deleted!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        //make sure contributions no longer exists
        $select = $this->zdb->select(\Galette\Entity\Contribution::TABLE)
            ->where(['id_cotis' => [$contrib_one->id, $contrib_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(0, $result);
        $this->login->logout();

        $request = $this->createRequest($route_name, $route_arguments, 'POST');

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $this->assertSame($member_one->id, $contrib_one->member);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //set member as staff
        $this->logSuperAdmin();
        $this->createContribution();
        $contrib = $this->contrib;
        $this->login->logOut();
        $staff_member = $this->getStaffMember($member_one);
        $request = $request->withParsedBody(['id' => [$contrib->id], 'confirm' => true]);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Successfully deleted!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

        $this->login->logOut();
        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $this->logSuperAdmin();
        $this->createContribution();
        $contrib = $this->contrib;
        $this->login->logOut();
        $request = $request->withParsedBody(['id' => [$contrib->id], 'confirm' => true]);

        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('contributions', ['type' => 'contributions'])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(['success_detected' => ['Successfully deleted!']], $this->flash_data['slimFlash']);
        $this->flash_data = [];

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

        //groups manager: refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);
    }
}
