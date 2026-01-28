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
 * Members controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MembersController extends GaletteRoutingTestCase
{
    protected int $seed = 20250817103312;

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
     * Test members list
     */
    public function testList(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

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

        $route_name = 'members';
        $request = $this->createRequest($route_name);

        $controller = new \Galette\Controllers\Crud\MembersController($this->container);
        $filter_name = $controller->getFilterName($controller::getDefaultFilterName());

        //Need to be logged-in
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //superamdin can list - and can impersonate
        $this->logSuperAdmin();
        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member is listed and impersonate link is present
        $this->assertStringContainsString(
            $member_one->sfullname,
            $body
        );
        $this->assertStringContainsString(
            $this->routeparser->urlFor('impersonate', ['id' => $member_one->id]),
            $body
        );

        //member child is listed and impersonate link is present
        $this->assertStringContainsString(
            $child->sfullname,
            $body
        );
        $this->assertStringContainsString(
            $this->routeparser->urlFor('impersonate', ['id' => $child->id]),
            $body
        );

        //second member is listed and impersonate link is present
        $this->assertStringContainsString(
            $member_two->sfullname,
            $body
        );
        $this->assertStringContainsString(
            $this->routeparser->urlFor('impersonate', ['id' => $member_two->id]),
            $body
        );

        $this->login->logOut();

        //test with simple member: cannot show a list of members
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);
        $request = $this->createRequest($route_name);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member is listed
        $this->assertStringContainsString(
            $member_one->sfullname,
            $body
        );
        //member child is listed
        $this->assertStringContainsString(
            $child->sfullname,
            $body
        );
        //second member is listed
        $this->assertStringContainsString(
            $member_two->sfullname,
            $body
        );
        //no impersonate
        $this->assertStringNotContainsString(
            str_replace((string)$member_one->id, '', $this->routeparser->urlFor('impersonate', ['id' => $member_one->id])),
            $body
        );

        $this->login->logOut();

        //reset staff status
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        unset($this->session->$filter_name);
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member is listed
        $this->assertStringContainsString(
            $member_one->sfullname,
            $body
        );
        //member child is listed
        $this->assertStringContainsString(
            $child->sfullname,
            $body
        );
        //second member is listed
        $this->assertStringContainsString(
            $member_two->sfullname,
            $body
        );
        //no impersonate
        $this->assertStringNotContainsString(
            str_replace((string)$member_one->id, '', $this->routeparser->urlFor('impersonate', ['id' => $member_one->id])),
            $body
        );

        //reset admin status
        $this->resetAdminStatus($adm_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two/*, $child*/]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //group manager can list members he owns
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member is listed
        $this->assertStringContainsString(
            $member_one->sfullname,
            $body
        );
        //member child is *not* listed
        $this->assertStringNotContainsString(
            $child->sfullname,
            $body
        );
        //second member is listed
        $this->assertStringContainsString(
            $member_two->sfullname,
            $body
        );
        //no impersonate
        $this->assertStringNotContainsString(
            str_replace((string)$member_one->id, '', $this->routeparser->urlFor('impersonate', ['id' => $member_one->id])),
            $body
        );
    }

    /**
     * Test public members list
     */
    public function testPublicMembersList(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $this->assertTrue($member_one->load($member_one->id));
        $member_two = $this->getMemberTwo();
        $this->createContribution();
        $this->assertTrue($member_two->load($member_two->id));
        $this->login->logOut();

        //members are up to date
        $this->assertTrue($member_one->isUp2Date());
        $this->assertTrue($member_two->isUp2Date());
        //member one appears in public lists, not member two
        $this->assertTrue($member_one->appearsInMembersList());
        $this->assertFalse($member_two->appearsInMembersList());

        $route_name = 'publicMembersList';
        $request = $this->createRequest($route_name);

        //No access per default
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Unauthorized']]);

        // enable public page
        $this->preferences->pref_publicpages_visibility_memberslist = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //reset
        $this->preferences->pref_publicpages_visibility_memberslist = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;
        $this->assertTrue($this->preferences->store());

        //$this->expectOK($test_response); //FIXME: Adherent::website direct call is deprecated
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "website" directly is deprecated.');
        $this->expectFlashData([]);

        $body = (string)$test_response->getBody();
        //member one is listed
        $this->assertStringContainsString(
            $member_one->sfullname,
            $body
        );
        //member two is not listed
        $this->assertStringNotContainsString(
            $member_two->sfullname,
            $body
        );
    }

    /**
     * Test public members gallery
     */
    public function testPublicMembersGallery(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $this->assertTrue($member_one->load($member_one->id));
        $member_two = $this->getMemberTwo();
        $this->createContribution();
        $this->assertTrue($member_two->load($member_two->id));
        $this->login->logOut();

        //members are up to date
        $this->assertTrue($member_one->isUp2Date());
        $this->assertTrue($member_two->isUp2Date());
        //member one appears in public lists, not member two
        $this->assertTrue($member_one->appearsInMembersList());
        $this->assertFalse($member_two->appearsInMembersList());

        $route_name = 'publicMembersGallery';
        $request = $this->createRequest($route_name);

        //No access per default
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Unauthorized']]);

        // enable public page
        $this->preferences->pref_publicpages_visibility_membersgallery = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //reset
        $this->preferences->pref_publicpages_visibility_membersgallery = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //no member has picture
        $this->assertStringNotContainsString(
            $member_one->sfullname,
            $body
        );
        $this->assertStringNotContainsString(
            $member_two->sfullname,
            $body
        );
    }

    /**
     * Test public staff list
     */
    public function testPublicStaffList(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $this->assertTrue($member_one->load($member_one->id));
        $member_two = $this->getMemberTwo();
        $this->createContribution();
        $this->assertTrue($member_two->load($member_two->id));
        $this->login->logOut();

        //members are up to date
        $this->assertTrue($member_one->isUp2Date());
        $this->assertTrue($member_two->isUp2Date());
        //member one appears in public lists, not member two
        $this->assertTrue($member_one->appearsInMembersList());
        $this->assertFalse($member_two->appearsInMembersList());
        $staff_member = $this->getStaffMember($member_one);

        $route_name = 'publicStaffList';
        $request = $this->createRequest($route_name);

        //No access per default
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Unauthorized']]);

        // enable public page
        $this->preferences->pref_publicpages_visibility_stafflist = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //reset
        $this->preferences->pref_publicpages_visibility_stafflist = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;
        $this->assertTrue($this->preferences->store());

        //$this->expectOK($test_response); //FIXME: Adherent::website direct call is deprecated
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "website" directly is deprecated.');
        $this->expectFlashData([]);

        $body = (string)$test_response->getBody();
        //member one is listed
        $this->assertStringContainsString(
            $staff_member->sfullname,
            $body
        );
        //member two is not listed
        $this->assertStringNotContainsString(
            $member_two->sfullname,
            $body
        );
    }

    /**
     * Test public staff gallery
     */
    public function testPublicStaffGallery(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $this->assertTrue($member_one->load($member_one->id));
        $member_two = $this->getMemberTwo();
        $this->createContribution();
        $this->assertTrue($member_two->load($member_two->id));
        $this->login->logOut();

        //members are up to date
        $this->assertTrue($member_one->isUp2Date());
        $this->assertTrue($member_two->isUp2Date());
        //member one appears in public lists, not member two
        $this->assertTrue($member_one->appearsInMembersList());
        $this->assertFalse($member_two->appearsInMembersList());
        $staff_member = $this->getStaffMember($member_one);

        $route_name = 'publicStaffGallery';
        $request = $this->createRequest($route_name);

        //No access per default
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Unauthorized']]);

        // enable public page
        $this->preferences->pref_publicpages_visibility_staffgallery = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //reset
        $this->preferences->pref_publicpages_visibility_staffgallery = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //no member has picture
        $this->assertStringNotContainsString(
            $staff_member->sfullname,
            $body
        );
        $this->assertStringNotContainsString(
            $member_two->sfullname,
            $body
        );
    }

    /**
     * Test members filters
     */
    public function testListFilter(): void
    {
        $controller = new \Galette\Controllers\Crud\MembersController($this->container);
        $filter_name = $controller->getFilterName($controller::getDefaultFilterName());

        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
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

        //add another member
        $m3data = [
            'nom_adh'       => 'Special',
            'prenom_adh'    => 'Member',
            'login_adh'     => 'special.member',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $member_three = $this->createMember($m3data);

        $filterroute_name = 'filter-memberslist';

        //filter request on member one name
        unset($this->session->$filter_name);
        $request = $this->createRequest($filterroute_name, [], 'POST');
        $request = $request->withParsedBody([
            'field_filter' => \Galette\Repository\Members::FILTER_NAME,
            'filter_str' => $member_one->name,
        ]);

        $this->logSuperAdmin();

        $route_name = 'members';

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor($route_name)]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member is listed
        $this->assertStringContainsString(
            $member_one->sfullname,
            $body
        );
        //member child is not listed
        $this->assertStringNotContainsString(
            $child->sfullname,
            $body
        );
        //second member is not listed
        $this->assertStringNotContainsString(
            $member_two->sfullname,
            $body
        );
        //third member is not listed
        $this->assertStringNotContainsString(
            $member_three->sfullname,
            $body
        );

        //filter request on members with mail
        unset($this->session->$filter_name);
        $request = $this->createRequest($filterroute_name, [], 'POST');
        $request = $request->withParsedBody(['email_filter' => \Galette\Repository\Members::FILTER_W_EMAIL]);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor($route_name)]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member is listed
        $this->assertStringContainsString(
            $member_one->sfullname,
            $body
        );
        //member child is listed
        $this->assertStringContainsString(
            $child->sfullname,
            $body
        );
        //second member is listed
        $this->assertStringContainsString(
            $member_two->sfullname,
            $body
        );
        //third member is not listed
        $this->assertStringNotContainsString(
            $member_three->sfullname,
            $body
        );

        //filter request on members without mail
        unset($this->session->$filter_name);
        $request = $this->createRequest($filterroute_name, [], 'POST');
        $request = $request->withParsedBody(['email_filter' => \Galette\Repository\Members::FILTER_WO_EMAIL]);

        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor($route_name)]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        //member is not listed
        $this->assertStringNotContainsString(
            $member_one->sfullname,
            $body
        );
        //member child is not listed
        $this->assertStringNotContainsString(
            $child->sfullname,
            $body
        );
        //second member is not listed
        $this->assertStringNotContainsString(
            $member_two->sfullname,
            $body
        );
        //third member is listed
        $this->assertStringContainsString(
            $member_three->sfullname,
            $body
        );
    }

    /**
     * Test public members list filters
     */
    public function testPublicMembersListFilter(): void
    {
        $controller = new \Galette\Controllers\Crud\MembersController($this->container);
        $filter_name = $controller->getFilterName($controller->getDefaultFilterName(), ['prefix' => 'public', 'suffix' => 'list']);

        $filterroute_name = 'filterPublicMembersList';

        //show 42 elements
        unset($this->session->$filter_name);
        $request = $this->createRequest($filterroute_name, [], 'POST');
        $request = $request->withParsedBody([
            'nbshow' => 42
        ]);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('publicMembersList')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $this->assertSame(42, $this->session->$filter_name->show);
    }

    /**
     * Test public members gallery filters
     */
    public function testPublicMembersGalleryFilter(): void
    {
        $controller = new \Galette\Controllers\Crud\MembersController($this->container);
        $filter_name = $controller->getFilterName($controller->getDefaultFilterName(), ['prefix' => 'public', 'suffix' => 'trombi']);

        $filterroute_name = 'filterPublicMembersGallery';

        //show 42 elements
        unset($this->session->$filter_name);
        $request = $this->createRequest($filterroute_name, [], 'POST');
        $request = $request->withParsedBody([
            'nbshow' => 42
        ]);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('publicMembersGallery')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $this->assertSame(42, $this->session->$filter_name->show);
    }

    /**
     * Test members add page
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

        $route_name = 'addMember';

        //login is required to access this page
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access add page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $request = $this->createRequest($route_name);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //change preferences so members can create child members - route is still not accessible
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_create_member = false;
        $this->assertTrue($this->preferences->store());

        $this->expectAuthMiddlewareRefused($test_response);

        $this->login->logout();

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $request = $this->createRequest($route_name);
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

        $request = $this->createRequest($route_name);
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
        $this->assertSame(['Location' => [$this->routeparser->urlFor('me')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['You do not have permission for requested URL.']]);

        //change preferences so managers can create members
        $this->preferences->pref_bool_groupsmanagers_create_member = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_member = false;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $this->login->logout();

        //simulate error while storing, values are kept in session
        $this->logSuperAdmin();
        $mdata = $this->dataAdherentOne();
        $mdata['login_adh'] = 'login_4_test';
        $mdata['email_adh'] = 'email_4_test@galette.eu';
        unset($mdata['nom_adh']);

        $member = new \Galette\Entity\Adherent($this->zdb);
        $member->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        $check = $member->check($mdata, ['nom_adh' => true], []);
        $this->assertSame(
            [
                '- Mandatory field <a href="#nom_adh">Name</a> empty.'
            ],
            $check
        );
        $this->expectLogEntry(\Analog::ERROR, 'Mandatory field <a href="#nom_adh">Name</a> empty');
        $this->session->member = $member;

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($mdata['email_adh'], $body);
        $this->login->logout();
    }

    /**
     * Test members self subscription page
     */
    public function testSelfSubscriptionPage(): void
    {
        $route_name = 'subscribe';
        $request = $this->createRequest($route_name);

        //preference is required to access this page - enabled per default
        $this->preferences->pref_bool_selfsubscribe = false;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_selfsubscribe = true;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);
        $this->login->logout();

        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('<title>Subscription', $body);
        $this->assertStringContainsString('name="gaptcha"', $body);
    }

    /**
     * Test members add child page
     */
    public function testAddChildPage(): void
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

        $route_name = 'addMemberChild';

        //login is required to access this page
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin cannot access add child page, since it's not a regular member
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //change preferences so members can create child members - route is still not accessible
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_create_member = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $this->login->logout();

        //by default, simple members cannot add child members
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //change preferences so members can create child members - route is now accessible
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_create_member = false;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $body  = (string)$test_response->getBody();
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="parent_id" value="%1$s"/>', $member_one->id),
            $body
        );

        //groups managers using addMemberChild route must be set as parent, as every other member
        $this->logSuperAdmin();
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));
        $this->login->logout();

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //by default, groups managers like simple members cannot add child members
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //change preferences so managers can create child members
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_create_member = false;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            sprintf('<input type="hidden" name="parent_id" value="%1$s"/>', $member_two->id),
            $body
        );
    }

    /**
     * Test members edit page
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

        $route_name = 'editMember';
        $route_arguments = ['id' => $member_one->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access edit page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_one->login, $body);
        $this->assertStringContainsString($member_one->name, $body);

        //member that does not exists
        $request = $this->createRequest($route_name, ['id' => 999999]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'No member #999999');
        $this->expectFlashData(['error_detected' => ['No member #999999.']]);

        $this->login->logout();

        //member can edit his own information
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_one->login, $body);
        $this->assertStringContainsString($member_one->name, $body);

        $this->login->logout();

        //another member cannot edit information
        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('me')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([
            'error_detected' => ['You do not have permission for requested URL.']
        ]);
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

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        //with default preferences, groups manager cannot access edit page
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('me')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['You do not have permission for requested URL.']]);

        $this->assertTrue($g1->setMembers([]));

        //change preferences so managers can edit group members
        $this->preferences->pref_bool_groupsmanagers_edit_member = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_edit_member = false;
        $this->assertTrue($this->preferences->store());

        //NOK since member_one is not part of group 1
        $this->assertSame(['Location' => [$this->routeparser->urlFor('me')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['You do not have permission for requested URL.']]);

        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        //change preferences so managers can edit group members
        $this->preferences->pref_bool_groupsmanagers_edit_member = true;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_edit_member = false;
        $this->assertTrue($this->preferences->store());

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_one->login, $body);
        $this->assertStringContainsString($member_one->name, $body);

        $this->login->logout();
    }

    /**
     * Test members show page
     */
    public function testShowPage(): void
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

        $route_name = 'member';
        $route_arguments = ['id' => $member_one->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access show page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_one->sfullname, $body);
        $this->assertStringContainsString($member_one->getEmail(), $body);

        //member that does not exists
        $request = $this->createRequest($route_name, ['id' => 999999]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'No member #999999');
        $this->expectFlashData(['error_detected' => ['No member #999999.']]);

        $this->login->logout();

        //member can show his own information
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $request = $this->createRequest($route_name, $route_arguments);

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_one->sfullname, $body);
        $this->assertStringContainsString($member_one->getEmail(), $body);

        $this->login->logout();

        //another member cannot show information
        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('me')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([
            'error_detected' => ['You do not have permission for requested URL.']
        ]);
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

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager can access show page of their members only
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('me')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['You do not have permission for requested URL.']]);

        $this->assertTrue($g1->setMembers([$member_one, $member_two]));
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_one->sfullname, $body);
        $this->assertStringContainsString($member_one->getEmail(), $body);
    }

    /**
     * Test "my" page
     */
    public function testShowMePage(): void
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

        $route_name = 'me';

        //login is required to access this page
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin has no "show me" page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);
        $this->login->logout();

        //member can show his own information
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $request = $this->createRequest($route_name);

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_one->login, $body);
        $this->assertStringContainsString($member_one->name, $body);
        $this->assertStringContainsString($member_one->getEmail(), $body);
        $this->assertStringNotContainsString($member_two->getEmail(), $body);

        $this->login->logout();

        //another member can also show its own information
        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($member_two->login, $body);
        $this->assertStringContainsString($member_two->name, $body);
        $this->assertStringContainsString($member_two->getEmail(), $body);
        $this->assertStringNotContainsString($member_one->getEmail(), $body);

        $this->login->logout();
    }

    /**
     * Test add members
     */
    public function testAddMember(): void
    {
        $member_one = $this->getMemberOne();
        $mdata = $this->dataAdherentOne();

        $member_two = $this->getMemberTwo();
        $m2data = $this->dataAdherentTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

        //add another member
        $m3data = [
            'nom_adh'       => 'Special',
            'prenom_adh'    => 'Member',
            'login_adh'     => 'special.member',
            'adresse_adh'   => 'A street',
            'cp_adh'        => '12345',
            'ville_adh'     => 'A town',
            'fingerprint' => 'FAKER' . $this->seed
        ];

        $route_name = 'doAddMember';

        $count_select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $count_select->where(['nom_adh' => $m3data['nom_adh']]);
        $remove_members = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $remove_members->where(['nom_adh' => $m3data['nom_adh']]);
        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result);

        //login is required to access this page
        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody($m3data);
        $test_response = $this->app->handle($request);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result);
        $this->expectLogin($test_response);

        //member cannot be added even if self subscription mode is on (not from doAdd route)
        $this->preferences->pref_bool_selfsubscribe = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_selfsubscribe = false;
        $this->assertTrue($this->preferences->store());

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result);
        $this->expectLogin($test_response);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result); //no member added

        //super-admin can add member
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $headers = $test_response->getHeaders();
        $this->assertCount(1, $headers);
        $this->assertArrayHasKey('Location', $headers);
        $this->assertStringStartsWith(
            $this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]) . '?id_adh=',
            $headers['Location'][0]
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['New member has been successfully added.']]);
        $this->login->logout();

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one member added by super-admin
        $this->assertCount(1, $this->zdb->execute($remove_members));

        //test with simple member: no rights (will cause an exception)
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $exception_thrown = false;
        try {
            $this->app->handle($request);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store new member!', $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');
        $this->login->logout();

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result); //no new member

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $headers = $test_response->getHeaders();
        $this->assertCount(1, $headers);
        $this->assertArrayHasKey('Location', $headers);
        $this->assertStringStartsWith(
            $this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]) . '?id_adh=',
            $headers['Location'][0]
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['New member has been successfully added.']]);

        $this->login->logOut();
        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //new member added by staff member
        $this->assertCount(1, $this->zdb->execute($remove_members));

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $headers = $test_response->getHeaders();
        $this->assertCount(1, $headers);
        $this->assertArrayHasKey('Location', $headers);
        $this->assertStringStartsWith(
            $this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]) . '?id_adh=',
            $headers['Location'][0]
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['New member has been successfully added.']]);
        $this->login->logOut();

        //reset admin status
        $this->resetAdminStatus($adm_member);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one member added by admin member
        $this->assertCount(1, $this->zdb->execute($remove_members));

        $this->logSuperAdmin();
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));
        $this->login->logout();

        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //with default preferences, groups manager cannot add member (will throw an exception)
        $exception_thrown = false;
        try {
            $this->app->handle($request);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store new member!', $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result); //no member added

        //change preferences so managers can create members - but only attached to a group he owns
        $this->preferences->pref_bool_groupsmanagers_create_member = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_member = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(['Location' => [$this->routeparser->urlFor('addMember')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'You have to select a group you own!');
        $this->expectFlashData(['error_detected' => ['You have to select a group you own!']]);

        //test with group set
        $this->preferences->pref_bool_groupsmanagers_create_member = true;
        $this->assertTrue($this->preferences->store());

        $request = $request->withParsedBody($m3data + ['groups_adh' => [$g1->getId() . '|' . $g1->getName()]]);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_create_member = false;
        $this->assertTrue($this->preferences->store());

        $headers = $test_response->getHeaders();
        $this->assertCount(1, $headers);
        $this->assertArrayHasKey('Location', $headers);
        $this->assertStringStartsWith(
            $this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]) . '?id_adh=',
            $headers['Location'][0]
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['New member has been successfully added.']]);
        $this->login->logOut();

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result); //one member added by group manager
        $this->assertTrue($g1->setMembers([$member_one, $member_two])); //reset members
        $this->assertCount(1, $this->zdb->execute($remove_members));
    }

    /**
     * Test members adding their own children
     */
    public function testMemberAddChild(): void
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

        $route_name = 'doAddMemberChild';

        //login is required to access this page
        $request = $this->createRequest($route_name, [], 'POST');
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin cannot access add child page, since it's not a regular member
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //change preferences so members can create child members - route is still not accessible
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_create_member = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $this->login->logout();

        //by default, simple members cannot add child members
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //change preferences so members can create child members - route is now accessible
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($this->preferences->store());

        $child_data = [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $member_one->id,
            'attach'        => true,
            'login_adh'     => 'child.johny.doe',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody($child_data);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_create_member = false;
        $this->assertTrue($this->preferences->store());

        $headers = $test_response->getHeaders();
        $this->assertCount(1, $headers);
        $this->assertArrayHasKey('Location', $headers);
        $start_header = $this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]) . '?id_adh=';
        $this->assertStringStartsWith(
            $start_header,
            $headers['Location'][0]
        );
        $new_adh_id = (int)str_replace($start_header, '', $headers['Location'][0]);
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['New member has been successfully added.']]);

        $member = new \Galette\Entity\Adherent($this->zdb, $new_adh_id);
        $this->assertSame($member_one->id, $member->parent_id);

        //groups managers using addMemberChild route must be set as parent, as every other member
        $this->logSuperAdmin();
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));
        $this->login->logout();

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //by default, groups managers like simple members cannot add child members
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('slash')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //change preferences so managers can create child members
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($this->preferences->store());

        $child_data = [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'login_adh'     => 'another.child.johny.doe',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody($child_data);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_create_member = false;
        $this->assertTrue($this->preferences->store());

        $headers = $test_response->getHeaders();
        $this->assertCount(1, $headers);
        $this->assertArrayHasKey('Location', $headers);
        $start_header = $this->routeparser->urlFor('addContribution', ['type' => \Galette\Entity\Contribution::TYPE_FEE]) . '?id_adh=';
        $this->assertStringStartsWith(
            $start_header,
            $headers['Location'][0]
        );
        $new_adh_id = (int)str_replace($start_header, '', $headers['Location'][0]);
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['New member has been successfully added.']]);

        $member = new \Galette\Entity\Adherent($this->zdb, $new_adh_id);
        $this->assertSame($member_two->id, $member->parent_id);
    }

    /**
     * Test members self subscription
     */
    public function testMemberSelfSubscription(): void
    {
        $member_data = $this->dataAdherentOne();
        $route_name = 'storeselfmembers';

        $count_select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result);

        //preference is required to access this page
        $this->preferences->pref_bool_selfsubscribe = false;
        $this->assertTrue($this->preferences->store());

        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody($member_data);
        $test_response = $this->app->handle($request);

        $result = $this->zdb->execute($count_select);
        $this->assertCount(0, $result);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //member can be added when self subscription mode is on
        $this->preferences->pref_bool_selfsubscribe = true;
        $this->assertTrue($this->preferences->store());

        //setup captcha
        $gaptcha = new \Galette\Core\Gaptcha(new \Galette\Core\I18n());
        $rgaptcha = new \ReflectionClass($gaptcha);
        $current = $rgaptcha->getProperty('gaptcha');
        $current->setValue($gaptcha, 8);
        $this->assertTrue($gaptcha->check(8));
        $this->session->gaptcha = $gaptcha;

        $self_member_data = $this->dataAdherentOne();
        unset($self_member_data['mdp_adh'], $self_member_data['mdp_adh2']);
        $self_member_data['gaptcha'] = 8;
        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody($self_member_data);
        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_selfsubscribe = false;
        $this->assertTrue($this->preferences->store());

        $result = $this->zdb->execute($count_select);
        $this->assertCount(1, $result);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('login')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Your account has been created!']]);
    }

    /**
     * Test member duplication route
     */
    public function testMemberDuplicate(): void
    {
        $member_one = $this->getMemberOne();

        $route_name = 'duplicateMember';
        $route_arguments = ['id_adh' => $member_one->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('addMember')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);
        $this->login->logout();

        $duplicated_member = $this->session->member;
        $this->assertSame(
            sprintf('Duplicated from %1$s (%2$s)', $member_one->sfullname, $member_one->id),
            $duplicated_member->others_infos_admin
        );
        $this->assertFalse($duplicated_member->isAdmin());
        $this->assertFalse($duplicated_member->isStaff());
    }

    /**
     * Test edit members
     */
    public function testEditMember(): void
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

        $mdata = $this->dataAdherentOne();
        $mdata['id_adh'] = $member_one->id;
        $mdata['nom_adh'] = 'Super changed name';
        $m2data = $this->dataAdherentTwo();

        $route_name = 'doEditMember';
        $route_arguments = ['id' => $member_one->id];

        //login is required to access this page
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody($mdata);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access edit page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $member_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);
        $this->login->logout();

        //check member name has been changed
        $member_one->load($member_one->id);
        $this->assertSame('Super changed name', $member_one->name);

        //simple member can edit his own information
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $mdata['nom_adh'] = 'Self changed name';
        $request = $request->withParsedBody($mdata);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $member_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);
        $this->login->logout();

        //check member name has been changed
        $member_one->load($member_one->id);
        $this->assertSame('Self changed name', $member_one->name);

        //another simple member cannot edit information
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $mdata['nom_adh'] = 'Another changed name';
        $request = $request->withParsedBody($mdata);

        $exception_thrown = false;
        try {
            $this->app->handle($request);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store member #' . $member_one->id, $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');

        $this->expectNoLogEntry();
        $this->expectFlashData([]);
        $this->login->logout();

        //check member name has *not* been changed
        $member_one->load($member_one->id);
        $this->assertSame('Self changed name', $member_one->name);

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $mdata['nom_adh'] = 'Staff changed name';
        $request = $request->withParsedBody($mdata);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $member_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);
        $this->login->logout();

        //check member name has been changed
        $member_one->load($member_one->id);
        $this->assertSame('Staff changed name', $member_one->name);

        $this->login->logOut();
        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $mdata['nom_adh'] = 'Admin changed name';
        $request = $request->withParsedBody($mdata);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $member_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);
        $this->login->logout();

        //check member name has been changed
        $member_one->load($member_one->id);
        $this->assertSame('Admin changed name', $member_one->name);

        $this->login->logOut();
        //reset admin status
        $this->resetAdminStatus($adm_member);

        //test with groups manager
        $mdata['nom_adh'] = 'Group manager changed name';
        $request = $request->withParsedBody($mdata);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));

        //groups manager: no right by default
        $exception_thrown = false;
        try {
            $this->app->handle($request);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store member #' . $member_one->id, $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');

        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        //change preferences so managers can see and create group members contributions
        $this->preferences->pref_bool_groupsmanagers_edit_member = true;
        $this->assertTrue($this->preferences->store());

        $test_response = $this->app->handle($request);

        //Reset
        $this->preferences->pref_bool_groupsmanagers_edit_member = false;
        $this->assertTrue($this->preferences->store());

        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $member_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);
        $this->login->logout();

        //check member name has been changed
        $member_one->load($member_one->id);
        $this->assertSame('Group manager changed name', $member_one->name);

        $this->login->logout();
        $this->assertTrue($g1->setManagers([]));

        //simple member can edit his child information
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $child_data['id_adh'] = $child->id;
        $child_data['nom_adh'] = 'Parent changed name';

        $child_request = $this->createRequest($route_name, ['id' => $child->id], 'POST');
        $child_request = $child_request->withParsedBody($child_data);
        $test_response = $this->app->handle($child_request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $child->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);
        $this->login->logout();

        //check member name has been changed
        $child->load($child->id);
        $this->assertSame('Parent changed name', $child->name);

        //another simple member cannot edit information
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        $child_data['nom_adh'] = 'Another changed name';
        $child_request = $child_request->withParsedBody($child_data);

        $exception_thrown = false;
        try {
            $this->app->handle($child_request);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store member #' . $child->id, $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');

        $this->expectNoLogEntry();
        $this->expectFlashData([]);
        $this->login->logout();

        //check member name has *not* been changed
        $child->load($child->id);
        $this->assertSame('Parent changed name', $child->name);

        //check there is no privilege escalation
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());

        //force admin flag to be allowed for everyone
        $fc = $this->getMockBuilder(\Galette\Entity\FieldsConfig::class)
            ->setConstructorArgs([
                $this->zdb,
                \Galette\Entity\Adherent::TABLE,
                $this->members_fields,
                $this->members_fields_cats
            ])
            ->onlyMethods(['getAllowedFields'])
            ->getMock();
        $orig_fields = $fc->getCategorizedFields();
        $fields = [];
        foreach ($orig_fields as $fieldset) {
            foreach ($fieldset as $field) {
                $fields[] = $field['field_id'];
            }
        }
        $fc->method('getAllowedFields')->willReturn($fields);
        $this->container->set(\Galette\Entity\FieldsConfig::class, $fc);

        $mdata['bool_admin_adh'] = true;
        $request = $request->withParsedBody($mdata);

        $exception_thrown = false;
        try {
            $this->app->handle($request);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store member #' . $member_one->id, $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');

        $this->expectLogEntry(
            \Analog::CRITICAL,
            sprintf(
                'Non allowed user %1$s attempting to change member %1$s admin flag',
                $member_one->id,
            )
        );
        $this->expectFlashData([]);
        $this->login->logout();

        //check admin flag has not been changed
        $member_one->load($member_one->id);
        $this->assertFalse($member_one->isAdmin());

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $mdata['nom_adh'] = 'Staff changed name';
        $mdata['bool_admin_adh'] = true;
        $request = $request->withParsedBody($mdata);

        //no right for staff member to set admin flag
        $exception_thrown = false;
        try {
            $this->app->handle($request);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store member #' . $member_one->id, $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');
        $this->expectLogEntry(
            \Analog::CRITICAL,
            sprintf(
                'Non allowed user %1$s attempting to change member %1$s admin flag',
                $member_one->id,
            )
        );
        $this->expectFlashData([]);
        $this->resetStaffStatus($staff_member, $member_one);
    }

    /**
     * Test member photo
     */
    public function testMemberPhoto(): void
    {
        $member_one = $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $mdata['id_adh'] = $member_one->id;

        $route_name = 'doEditMember';
        $route_arguments = ['id' => $member_one->id];

        $this->logSuperAdmin();

        $this->assertTrue(copy(GALETTE_TESTS_PATH . '/fixtures/galette_pro.png', sys_get_temp_dir() . '/galette_pro.png'));
        $uploaded_files = [
            'photo' => new \Slim\Psr7\UploadedFile(
                sys_get_temp_dir() . '/galette_pro.png',
                'galette_pro.png',
                'impage/png',
                filesize(sys_get_temp_dir() . '/galette_pro.png'),
                UPLOAD_ERR_OK
            )
        ];

        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody($mdata);
        $request = $request->withUploadedFiles($uploaded_files);

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $member_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog::ERROR, 'Unable to remove picture database entry for ' . $member_one->id);
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);

        //check photo is present
        $this->assertTrue(file_exists(GALETTE_PHOTOS_PATH . '/' . $member_one->id . '.png'));

        //Remove photo
        $request = $this->createRequest($route_name, $route_arguments, 'POST');
        $request = $request->withParsedBody($mdata + ['del_photo' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('member', ['id' => $member_one->id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Member account has been modified.']]);

        $this->assertFalse(file_exists(GALETTE_PHOTOS_PATH . '/' . $member_one->id . '.png'));

        $this->login->logout();
    }

    /**
     * Test mass change page
     */
    public function testMassChangePage(): void
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

        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$member_one->id, $member_two->id];
        $controller = new \Galette\Controllers\Crud\MembersController($this->container);
        $this->session->{$controller->getFilterName($controller->getDefaultFilterName(), ['suffix' => 'masschange'])} = $filters;
        $expected_title = 'Mass change ' . count($filters->selected) . ' members';

        $route_name = 'masschangeMembers';

        //login is required to access this page
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($expected_title, $body);
        $this->login->logout();

        //member cannot access page
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
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($expected_title, $body);

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
        $this->assertStringContainsString($expected_title, $body);

        $this->login->logOut();
        //reset admin status
        $this->resetAdminStatus($adm_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        //with default preferences, groups manager cannot access edit page
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($expected_title, $body);
    }

    /**
     * Test mass change validation page
     */
    public function testValidateMassChange(): void
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

        //add another member
        $m3data = [
            'nom_adh'       => 'Special',
            'prenom_adh'    => 'Member',
            'login_adh'     => 'special.member',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $member_three = $this->createMember($m3data);

        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$member_one->id, $member_two->id];
        $controller = new \Galette\Controllers\Crud\MembersController($this->container);
        $this->session->{$controller->getFilterName($controller->getDefaultFilterName(), ['suffix' => 'masschange'])} = $filters;
        $expected_title = 'Review mass change ' . count($filters->selected) . ' members';

        $route_name = 'masschangeMembersReview';

        //login is required to access this page
        $request = $this->createRequest($route_name, [], 'POST');
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access page
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);

        //confirmation is expected
        $this->assertSame([], $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Mass changes has not been confirmed!']]);

        $request = $request->withParsedBody(['confirm' => 1]);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($expected_title, $body);
        $this->assertStringContainsString('No changes selected', $body);

        $request = $request->withParsedBody([
            'confirm' => 1,
            'id' => $filters->selected,
            'mass_ville_adh' => 'on',
            'ville_adh' => 'Test New City'
        ]);
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($expected_title, $body);
        $this->assertStringNotContainsString('No changes selected', $body);
        $this->assertStringContainsString('<input type="hidden" name="ville_adh" value="Test New City"/>', $body);
        $this->assertStringContainsString('City: Test New City', $body);

        $this->login->logout();

        //member cannot access page
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
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($expected_title, $body);
        $this->assertStringContainsString('<input type="hidden" name="ville_adh" value="Test New City"/>', $body);
        $this->assertStringContainsString('City: Test New City', $body);
        $this->assertStringContainsString(sprintf('<input type="hidden" name="id[]" value="%1$s"/>', $member_one->id), $body);
        $this->assertStringContainsString(sprintf('<input type="hidden" name="id[]" value="%1$s"/>', $member_two->id), $body);

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
        $this->assertStringContainsString($expected_title, $body);
        $this->assertStringContainsString('<input type="hidden" name="ville_adh" value="Test New City"/>', $body);
        $this->assertStringContainsString('City: Test New City', $body);
        $this->assertStringContainsString(sprintf('<input type="hidden" name="id[]" value="%1$s"/>', $member_one->id), $body);
        $this->assertStringContainsString(sprintf('<input type="hidden" name="id[]" value="%1$s"/>', $member_two->id), $body);

        $this->login->logOut();
        //reset admin status
        $this->resetAdminStatus($adm_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        //tets with group manager
        $filters->selected = [$member_one->id, $member_two->id, $member_three->id];
        $expected_title = 'Review mass change ' . count($filters->selected) . ' members';

        $request = $request->withParsedBody([
            'confirm' => 1,
            'id' => $filters->selected,
            'mass_ville_adh' => 'on',
            'ville_adh' => 'Test New City',
            'mass_group_to_add' => 'on',
            'group_to_add' => $g1->getId()
        ]);

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString($expected_title, $body);
        $this->assertStringContainsString('<input type="hidden" name="ville_adh" value="Test New City"/>', $body);
        $this->assertStringContainsString('City: Test New City', $body);
        $this->assertStringContainsString(sprintf('<input type="hidden" name="id[]" value="%1$s"/>', $member_one->id), $body);
        $this->assertStringContainsString(sprintf('<input type="hidden" name="id[]" value="%1$s"/>', $member_two->id), $body);
        $this->assertStringContainsString(sprintf('<input type="hidden" name="group_to_add" value="%1$s"/>', $g1->getId()), $body);
        $this->assertStringContainsString('Add to group ' . $g1->getName(), $body);
    }

    /**
     * Test mass change action
     */
    public function testDoMassChanges(): void
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

        //add another member
        $m3data = [
            'nom_adh'       => 'Special',
            'prenom_adh'    => 'Member',
            'login_adh'     => 'special.member',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $member_three = $this->createMember($m3data);

        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$member_two->id, $member_three->id];
        $controller = new \Galette\Controllers\Crud\MembersController($this->container);
        $this->session->{$controller->getFilterName($controller->getDefaultFilterName(), ['suffix' => 'masschange'])} = $filters;

        $route_name = 'massstoremembers';

        //login is required to access this page
        $request = $this->createRequest($route_name, [], 'POST');
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can mass change members
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);

        //confirmation is expected
        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Mass changes has not been confirmed!']]);

        $request = $request->withParsedBody(['confirm' => 1]);
        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Nothing to do!']]);

        $request = $request->withParsedBody([
            'confirm' => 1,
            'id' => $filters->selected,
            'ville_adh' => 'Superadmin city'
        ]);
        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['2 members has been changed successfully!']]);

        $this->assertTrue($member_three->load($member_three->id));
        $this->assertSame('Superadmin city', $member_three->town);
        $this->assertTrue($member_two->load($member_two->id));
        $this->assertSame('Superadmin city', $member_two->town);

        $this->login->logout();

        //member cannot access page
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

        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody([
            'confirm' => 1,
            'id' => $filters->selected,
            'ville_adh' => 'Staff city'
        ]);
        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['2 members has been changed successfully!']]);

        $this->login->logOut();
        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        $this->assertTrue($member_three->load($member_three->id));
        $this->assertSame('Staff city', $member_three->town);
        $this->assertTrue($member_two->load($member_two->id));
        $this->assertSame('Staff city', $member_two->town);

        //set member as admin
        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $request = $request->withParsedBody([
            'confirm' => 1,
            'id' => $filters->selected,
            'ville_adh' => 'Admin city'
        ]);
        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['2 members has been changed successfully!']]);

        $this->assertTrue($member_three->load($member_three->id));
        $this->assertSame('Admin city', $member_three->town);
        $this->assertTrue($member_two->load($member_two->id));
        $this->assertSame('Admin city', $member_two->town);

        $this->login->logOut();
        //reset admin status
        $this->resetAdminStatus($adm_member);

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager($g1->getId()));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        //test with group manager
        $filters->selected = [$member_two->id, $member_three->id];

        $request = $request->withParsedBody([
            'confirm' => 1,
            'id' => $filters->selected,
            'ville_adh' => 'Group Manager City',
            'group_to_add' => $g1->getId() //to check there is no duplicated entry
        ]);

        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['1 member has been changed successfully!']]); //only one member edited since member three is not in the group

        $this->assertTrue($member_three->load($member_three->id));
        $this->assertSame('Admin city', $member_three->town);
        $this->assertTrue($member_two->load($member_two->id));
        $this->assertSame('Group Manager City', $member_two->town);

        //try to mass change on an emty group
        $this->logSuperAdmin();
        $g2 = new \Galette\Entity\Group();
        $g2->setName('Group 2');
        $this->assertTrue($g2->store());
        $filters->selected = [$member_one->id, $member_two->id, $member_three->id];

        $request = $request->withParsedBody([
            'confirm' => 1,
            'id' => $filters->selected,
            'group_to_add' => $g2->getId()
        ]);

        $test_response = $this->app->handle($request);

        $this->assertSame(['Location' => [$this->routeparser->urlFor('members')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['3 members has been changed successfully!']]); //only one member edited since member three is not in the group

        $this->assertTrue($g2->load($g2->getId()));
        $members = $g2->getMembers();
        $this->assertCount(3, $members);

        $this->login->logout();
    }

    /**
     * Test remove member page
     */
    public function testRemovePage(): void
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

        $route_name = 'removeMember';
        $route_arguments = ['id' => $member_one->id];

        $request = $this->createRequest($route_name, $route_arguments);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();
        $test_response = $this->app->handle($request);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Remove member ' . $member_one->sfullname, $body);
        $this->expectOK($test_response);

        $batch_request = $this->createRequest('batch-memberslist', [], 'POST');
        $batch_request = $batch_request->withParsedBody([
            'delete' => true,
            'entries_sel' => [
                $member_one->id,
                $member_two->id
            ]
        ]);
        $test_response = $this->app->handle($batch_request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('removeMembers')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);

        $remove_request = $this->createRequest('removeMembers');
        $test_response = $this->app->handle($remove_request);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('You are about to remove 2 members.', $body);
        $this->expectOK($test_response);

        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

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
        $this->assertStringContainsString('Remove member ' . $member_one->sfullname, $body);

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
        $this->assertStringContainsString('Remove member ' . $member_one->sfullname, $body);

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
     * Test delete member
     */
    public function testDeleteMember(): void
    {
        $member_two = $this->getMemberTwo();
        $member_one = $this->getMemberOne();

        $route_name = 'doRemoveMember';

        $request = $this->createRequest($route_name, [], 'POST');
        $request = $request->withParsedBody(['id' => [$member_one->id, $member_two->id]]);

        //login is required to access this page
        //Refused from authenticate middleware
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //test with logged-in user
        $this->logSuperAdmin();

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('members')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Removal has not been confirmed!']]);

        //make sure members still exists
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE)
            ->where([\Galette\Entity\Adherent::PK => [$member_one->id, $member_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(2, $result);

        $request = $request->withParsedBody(['id' => [$member_one->id, $member_two->id], 'confirm' => true]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('members')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);

        //make sure members no longer exists
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE)
            ->where([\Galette\Entity\Adherent::PK => [$member_one->id, $member_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(0, $result);
        $this->login->logout();

        //create members again
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $request = $this->createRequest($route_name, [], 'POST');

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertSame($mdata['login_adh'], $member_one->login);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //set member as staff
        $request = $request->withParsedBody(['id' => [$member_two->id], 'confirm' => true]);
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('members')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);

        //make sure member no longer exists
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE)
            ->where([\Galette\Entity\Adherent::PK => [$member_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(0, $result);
        $this->login->logout();
        //create member again
        $member_two = $this->getMemberTwo();

        $this->login->logOut();

        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        //set member as admin
        $request = $request->withParsedBody(['id' => [$member_two->id], 'confirm' => true]);

        $adm_member = $this->getAdminMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('members')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);

        //make sure member no longer exists
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE)
            ->where([\Galette\Entity\Adherent::PK => [$member_two->id]]);
        $result = $this->zdb->execute($select);
        $this->assertCount(0, $result);
        $this->login->logout();
        //create member again
        $member_two = $this->getMemberTwo();
        //change language
        $check = $member_two->check(['pref_lang' => 'en_US'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($member_two->store());

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
     * Test members advanced search page
     */
    public function testAvancedSearchPage(): void
    {
        $member_one = $this->getMemberOne();

        $route_name = 'advanced-search';

        //login is required to access this page
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectLogin($test_response);

        //super-admin can access add page
        $this->logSuperAdmin();
        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $this->login->logout();

        //test with simple member: refused from authenticate middleware
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        $this->assertFalse($this->login->isGroupManager());
        $request = $this->createRequest($route_name);

        $test_response = $this->app->handle($request);
        $this->expectAuthMiddlewareRefused($test_response);

        //set member as staff
        $staff_member = $this->getStaffMember($member_one);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertTrue($this->login->isStaff());

        $request = $this->createRequest($route_name);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $this->login->logOut();

        //reset statut
        $this->resetStaffStatus($staff_member, $member_one);

        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Advanced search', $body);
    }

    /**
     * Test disabled fields are well handled
     */
    public function testDisabledFields(): void
    {
        $this->logSuperAdmin();
        $fc = $this->container->get(\Galette\Entity\FieldsConfig::class);
        $orig_fields = $fc->getCategorizedFields();
        $fields = [];
        foreach ($orig_fields as $fieldset) {
            foreach ($fieldset as $field) {
                $fields[] = $field['field_id'];
            }
        }

        $route_name = 'addMember';
        $request = $this->createRequest($route_name);

        $excluded = [
            'id_adh',
            'fingerprint',
            'parent_id'
        ];

        $expected_defaults = $this->adh->getDefaultValues();

        $counter = 0;
        foreach ($fields as $key => $field) {
            if (in_array($field, $excluded)) {
                continue;
            }

            //change data
            $counter++;
            $data = $this->dataAdherentOne();
            $data['email_adh'] = sprintf('mail%1$s@domain.com', $counter);
            $data['login_adh'] = sprintf('login%1$s', $counter);

            //unset each field to be sure disabled fields are well handled
            $current_fields = $fields;
            unset($current_fields[$key]);
            unset($data[$field]);

            $fc = $this->container->get(\Galette\Entity\FieldsConfig::class);
            $categorized_fields = $orig_fields;
            foreach ($categorized_fields as $fieldset_key => &$fieldset) {
                foreach ($fieldset as $entry_key => &$entry) {
                    if ($entry['field_id'] == $field) {
                        $entry['visible'] = \Galette\Entity\FieldsConfig::NOBODY; //make sure fingerprint field is visible
                    } else {
                        $entry = $orig_fields[$fieldset_key][$entry_key];
                    }
                }
            }
            $fc->setFields($categorized_fields);

            $test_response = $this->app->handle($request);
            $this->expectOK($test_response);
            $body = (string)$test_response->getBody();
            $this->assertStringNotContainsString(
                'name="' . $field . '"',
                $body,
                sprintf('Field %s is present in form', $field)
            );

            $adh = new \Galette\Entity\Adherent($this->zdb);
            $adh->setDependencies(
                $this->preferences,
                $this->members_fields,
                $this->history
            );
            $this->assertTrue($adh->check($data, [], []));
            $this->assertTrue($adh->store());

            $this->assertTrue($adh->load($adh->id));
            if ($field === 'login_adh' || $field == 'mdp_adh') {
                //login and password are mandatory, they takes an automatic value if not set
                $this->assertNotEmpty($adh->login);
            } else {
                //check for expected default values
                $propname = $this->members_fields[$field]['propname'];
                if (isset($expected_defaults[$propname])) {
                    $this->assertSame($expected_defaults[$propname], $adh->$propname, 'Disabled field ' . $field);
                    if (in_array($propname, array_keys($adh->getDeprecatedProperties()))) {
                        $this->expectLogEntry(
                            \Analog::WARNING,
                            sprintf(
                                'Calling property "%1$s" directly is discouraged.',
                                $propname,
                            )
                        );
                    }
                }
            }
        }

        //reset
        $fc->setFields($orig_fields);

        $this->login->logOut();
    }
}
