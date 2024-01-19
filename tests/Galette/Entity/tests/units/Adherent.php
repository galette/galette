<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Adherent tests
 *
 * PHP version 5
 *
 * Copyright © 2017-2023 The Galette Team
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
 *
 * @category  Entity
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2017-04-17
 */

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * Adherent tests class
 *
 * @category  Entity
 * @name      Adherent
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2017-04-17
 */
class Adherent extends GaletteTestCase
{
    protected int $seed = 95842354;
    private array $default_deps;

    /**
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->zdb = new \Galette\Core\Db();

        $this->cleanContributions();

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
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
        $self->setUp();
        $self->tearDown();
    }

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->initStatus();
        $this->initTitles();

        $this->default_deps = [
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => false,
            'socials'   => false
        ];

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Test empty member
     *
     * @return void
     */
    public function testEmpty()
    {
        $adh = $this->adh;
        $this->assertFalse($adh->isAdmin());
        $this->assertFalse($adh->admin);
        $this->assertFalse($adh->isStaff());
        $this->assertFalse($adh->staff);
        $this->assertFalse($adh->isDueFree());
        $this->assertFalse($adh->due_free);
        $this->assertFalse($adh->isGroupMember('any'));
        $this->assertFalse($adh->isGroupManager('any'));
        $this->assertFalse($adh->isCompany());
        $this->assertFalse($adh->isMan());
        $this->assertFalse($adh->isWoman());
        $this->assertTrue($adh->isActive());
        $this->assertTrue($adh->active);
        $this->assertFalse($adh->isUp2Date());
        $this->assertFalse($adh->appearsInMembersList());
        $this->assertFalse($adh->appears_in_list);

        $this->assertNull($adh->fake_prop);

        $this->assertSame($this->default_deps, $adh->deps);
    }

    /**
     * Test member load dependencies
     *
     * @return void
     */
    public function testDependencies()
    {
        $adh = $this->adh;
        $this->assertSame($this->default_deps, $adh->deps);

        $adh = clone $this->adh;
        $adh->disableAllDeps();
        $expected = [
            'picture'   => false,
            'groups'    => false,
            'dues'      => false,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => false,
            'socials'   => false
        ];
        $this->assertSame($expected, $adh->deps);

        $expected = [
            'picture'   => false,
            'groups'    => false,
            'dues'      => true,
            'parent'    => false,
            'children'  => true,
            'dynamics'  => true,
            'socials'   => false
        ];
        $adh
            ->enableDep('dues')
            ->enableDep('dynamics')
            ->enableDep('children');
        $this->assertSame($expected, $adh->deps);

        $expected = [
            'picture'   => false,
            'groups'    => false,
            'dues'      => true,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => true,
            'socials'   => false
        ];
        $adh->disableDep('children');
        $this->assertSame($expected, $adh->deps);

        $adh->disableDep('none')->enableDep('anothernone');
        $this->assertSame($expected, $adh->deps);

        $expected = [
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true,
            'socials'   => true
        ];
        $adh->enableAllDeps('children');
        $this->assertSame($expected, $adh->deps);
    }

    /**
     * Tests getter
     *
     * @return void
     */
    public function testGetterWException()
    {
        $adh = $this->adh;

        $this->expectException('RuntimeException');
        $adh->row_classes;
    }

    /**
     * Set dependencies from constructor
     *
     * @return void
     */
    public function testDepsAtConstuct()
    {
        $deps = [
            'picture'   => false,
            'groups'    => false,
            'dues'      => false,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => false,
            'socials'   => false
        ];
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            null,
            $deps
        );

        $this->assertSame($deps, $adh->deps);
    }

    /**
     * Test simple member creation
     *
     * @return void
     */
    public function testSimpleMember()
    {
        $this->getMemberOne();
        $this->checkMemberOneExpected();

        //load member from db
        $adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $this->checkMemberOneExpected($adh);
    }

    /**
     * Test load form login and email
     *
     * @return void
     */
    public function testLoadForLogin()
    {
        $this->getMemberOne();

        $login = $this->adh->login;
        $email = $this->adh->email;

        $this->assertSame($this->adh->getEmail(), $this->adh->email);

        $adh = new \Galette\Entity\Adherent($this->zdb, $login);
        $this->checkMemberOneExpected($adh);

        $adh = new \Galette\Entity\Adherent($this->zdb, $email);
        $this->checkMemberOneExpected($adh);
    }

    /**
     * Test password updating
     *
     * @return void
     */
    public function testUpdatePassword()
    {
        $this->getMemberOne();

        $this->checkMemberOneExpected();

        $newpass = 'aezrty';
        \Galette\Entity\Adherent::updatePassword($this->zdb, $this->adh->id, $newpass);
        $adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $pw_checked = password_verify($newpass, $adh->password);
        $this->assertTrue($pw_checked);

        //reset original password
        \Galette\Entity\Adherent::updatePassword($this->zdb, $this->adh->id, 'J^B-()f');
    }

    /**
     * Tests check errors
     *
     * @return void
     */
    public function testCheckErrors()
    {
        $adh = $this->adh;

        $data = ['ddn_adh' => 'not a date'];
        $expected = ['- Wrong date format (Y-m-d) for Birth date!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        $data = [
            'ddn_adh'       => '',
            'date_crea_adh' => 'not a date'
        ];
        $expected = ['- Wrong date format (Y-m-d) for Creation date!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        //reste creation date to its default value
        $data = ['date_crea_adh' => date('Y-m-d')];
        $check = $adh->check($data, [], []);
        $this->assertTrue($check);

        $data = ['email_adh' => 'not an email'];
        $expected = ['- Non-valid E-Mail address! (E-Mail)'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        $data = ['login_adh' => 'a'];
        $expected = ['- The username must be composed of at least 2 characters!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        $data = ['login_adh' => 'login@galette'];
        $expected = ['- The username cannot contain the @ character'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        $data = [
            'login_adh' => '',
            'mdp_adh'   => 'short',
            'mdp_adh2'  => 'short'
        ];
        $expected = ['Too short (6 characters minimum, 5 found)'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        $data = ['mdp_adh' => 'mypassword'];
        $expected = ['- The passwords don\'t match!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        $data = [
            'mdp_adh'   => 'mypassword',
            'mdp_adh2'  => 'mypasswor'
        ];
        $expected = ['- The passwords don\'t match!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        $data = ['id_statut' => 256];
        $expected = ['Status #256 does not exists in database.'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);

        //tests for group managers
        //test insert failing
        $g1 = $this->getMockBuilder(\Galette\Entity\Group::class)
            ->onlyMethods(array('getId'))
            ->getMock();
        $g1->method('getId')->willReturn(1);

        $g2 = $this->getMockBuilder(\Galette\Entity\Group::class)
            ->onlyMethods(array('getId'))
            ->getMock();
        $g2->method('getId')->willReturn(2);

        //groups managers must specify a group they manage
        global $login;
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isGroupManager'))
            ->getMock();
        $login->method('isGroupManager')->willReturnCallback(
            function ($gid) use ($g1) {
                return $gid === null || $gid == $g1->getId();
            }
        );

        $data = ['id_statut' => \Galette\Entity\Status::DEFAULT_STATUS];
        $check = $adh->check($data, [], []);
        $expected = ['You have to select a group you own!'];
        $this->assertSame($expected, $check);

        $data = ['groups_adh' => [$g2->getId()]];
        $check = $adh->check($data, [], []);
        $expected = ['You have to select a group you own!'];
        $this->assertSame($expected, $check);

        $data = ['groups_adh' => [$g1->getId()]];
        $check = $adh->check($data, [], []);
        $this->assertTrue($check);
    }

    /**
     * Test picture
     *
     * @return void
     */
    public function testPhoto()
    {
        $this->getMemberOne();

        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);
        $this->assertTrue($fakedata->addPhoto($this->adh));

        $this->assertTrue($this->adh->hasPicture());

        //remove photo
        $this->assertTrue($this->adh->picture->delete());
    }

    /**
     * Test canEdit
     *
     * @return void
     */
    public function testCanEdit()
    {
        $adh = new \Galette\Entity\Adherent($this->zdb);

        //non authorized
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isGroupManager'))
            ->getMock();
        $this->assertFalse($adh->canEdit($login));

        //admin => authorized
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isAdmin'))
            ->getMock();
        $login->method('isAdmin')->willReturn(true);
        $this->assertTrue($adh->canEdit($login));

        //staff => authorized
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isStaff'))
            ->getMock();
        $login->method('isStaff')->willReturn(true);
        $this->assertTrue($adh->canEdit($login));

        //group managers
        $adh = $this->getMockBuilder(\Galette\Entity\Adherent::class)
            ->setConstructorArgs(array($this->zdb))
            ->onlyMethods(array('getGroups'))
            ->getMock();

        $g1 = $this->getMockBuilder(\Galette\Entity\Group::class)
            ->onlyMethods(array('getId'))
            ->getMock();
        $g1->method('getId')->willReturn(1);

        $g2 = $this->getMockBuilder(\Galette\Entity\Group::class)
            ->onlyMethods(array('getId'))
            ->getMock();
        $g2->method('getId')->willReturn(2);

        $adh->method('getGroups')->willReturn([$g1, $g2]);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isGroupManager'))
            ->getMock();

        $this->assertFalse($adh->canEdit($login));

        $login->method('isGroupManager')->willReturnCallback(
            function ($gid) use ($g1) {
                return $gid === null || $gid == $g1->getId();
            }
        );
        $this->assertFalse($adh->canEdit($login));

        $this->preferences->pref_bool_groupsmanagers_edit_member = true;
        $canEdit = $adh->canEdit($login);
        $this->preferences->pref_bool_groupsmanagers_edit_member = false; //reset
        $this->assertTrue($canEdit);

        //groups managers cannot edit members of the groups they do not own
        $adh->method('getGroups')->willReturn([$g2]);
        $this->assertFalse($adh->canEdit($login));
    }

    /**
     * Test member duplication
     *
     * @return void
     */
    public function testDuplicate()
    {
        $this->getMemberOne();

        $this->checkMemberOneExpected();

        //load member from db
        $adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $this->checkMemberOneExpected($adh);

        $adh->setDuplicate();

        $this->assertStringContainsString('Duplicated from', $adh->others_infos_admin);
        $this->assertNull($adh->email);
        $this->assertNull($adh->id);
        $this->assertNull($adh->login);
        $this->assertNull($adh->birthdate);
        $this->assertNull($adh->surname);
    }

    /**
     * Test parents
     *
     * @return void
     */
    public function testParents()
    {
        $this->getMemberOne();

        $this->checkMemberOneExpected();

        //load member from db
        $parent = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $this->checkMemberOneExpected($parent);

        $this->logSuperAdmin();

        $child_data = [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $parent->id,
            'attach'        => true,
            'fingerprint'   => 'FAKER' . $this->seed
        ];
        $child = $this->createMember($child_data);

        $this->assertSame($child_data['nom_adh'], $child->name);
        $this->assertInstanceOf('\Galette\Entity\Adherent', $child->parent);
        $this->assertSame($parent->id, $child->parent->id);

        $check = $child->check(['detach_parent' => true], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($child->store());
        $this->assertNull($child->parent);
    }

    /**
     * Test XSS/SQL injection
     *
     * @return void
     */
    public function testInjection()
    {
        $data = [
            'nom_adh'           => 'Doe',
            'prenom_adh'        => 'Johny <script>console.log("anything");</script>',
            'email_adh'         => 'jdoe@doe.com',
            'login_adh'         => 'jdoe',
            'info_public_adh'   => 'Any <script>console.log("useful");</script> information',
            'fingerprint'       => 'FAKER' . $this->seed
        ] + $this->dataAdherentOne();
        $member = $this->createMember($data);

        $this->assertSame('DOE Johny Console.log("anything");', $member->sfullname);
        $this->assertSame('Any console.log("useful"); information', $member->others_infos);
    }

    /**
     * Test can* methods
     *
     * @return void
     */
    public function testCan()
    {
        $this->getMemberOne();
        //load member from db
        $member = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);

        $this->assertFalse($member->canShow($this->login));
        $this->assertFalse($member->canCreate($this->login));
        $this->assertFalse($member->canEdit($this->login));

        //Superadmin can fully change members
        $this->logSuperAdmin();

        $this->assertTrue($member->canShow($this->login));
        $this->assertTrue($member->canCreate($this->login));
        $this->assertTrue($member->canEdit($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Member can fully change its own information
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertTrue($member->canShow($this->login));
        $this->assertTrue($member->canCreate($this->login));
        $this->assertTrue($member->canEdit($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Another member has no access
        $this->getMemberTwo();
        $mdata = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertFalse($member->canShow($this->login));
        $this->assertFalse($member->canCreate($this->login));
        $this->assertFalse($member->canEdit($this->login));

        //parents can fully change children information
        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        global $login;
        $login = $this->login;
        $this->logSuperAdmin();

        $child_data = [
                'nom_adh'       => 'Doe',
                'prenom_adh'    => 'Johny',
                'parent_id'     => $member->id,
                'attach'        => true,
                'login_adh'     => 'child.johny.doe',
                'fingerprint' => 'FAKER' . $this->seed
        ];
        $child = $this->createMember($child_data);
        $cid = $child->id;
        $this->login->logOut();

        //load child from db
        $child = new \Galette\Entity\Adherent($this->zdb);
        $child->enableDep('parent');
        $this->assertTrue($child->load($cid));

        $this->assertSame($child_data['nom_adh'], $child->name);
        $this->assertInstanceOf('\Galette\Entity\Adherent', $child->parent);
        $this->assertSame($member->id, $child->parent->id);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertTrue($child->canShow($this->login));
        $this->assertFalse($child->canCreate($this->login));
        $this->assertTrue($child->canEdit($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //tests for group managers
        $adh = $this->getMockBuilder('\Galette\Entity\Adherent')
            ->setConstructorArgs([$this->zdb])
            ->onlyMethods(['getGroups'])
            ->getMock();

        $g1 = $this->getMockBuilder('\Galette\Entity\Group')
            ->onlyMethods(['getId'])
            ->getMock();
        $g1->method('getId')->willReturn(1);

        $g2 = $this->getMockBuilder('\Galette\Entity\Group')
            ->onlyMethods(['getId'])
            ->getMock();
        $g2->method('getId')->willReturn(2);

        //groups managers can show members of the groups they own
        $adh->method('getGroups')->willReturn([$g1, $g2]);

        $login = $this->getMockBuilder('\Galette\Core\Login')
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isGroupManager'])
            ->getMock();
        $this->assertFalse($adh->canShow($login));

        $login->method('isGroupManager')->willReturnCallback(function ($gid) use ($g1) {
            return $gid === null || $gid == $g1->getId();
        });
        $this->assertTrue($adh->canShow($login));

        //groups managers cannot show members of the groups they do not own
        $adh = $this->getMockBuilder('\Galette\Entity\Adherent')
            ->setConstructorArgs([$this->zdb])
            ->onlyMethods(['getGroups'])
            ->getMock();
        $adh->method('getGroups')->willReturn([$g2]);
        $this->assertFalse($adh->canShow($login));
    }

    /**
     * Names provider
     *
     * @return array[]
     */
    public static function nameCaseProvider(): array
    {
        return [
            [
                'name' => 'Doe',
                'surname' => 'John',
                'title' => false,
                'id' => false,
                'nick' => false,
                'expected' => 'DOE John'
            ],
            [
                'name' => 'Doéè',
                'surname' => 'John',
                'title' => false,
                'id' => false,
                'nick' => false,
                'expected' => 'DOÉÈ John'
            ],
            [
                'name' => 'Doe',
                'surname' => 'John',
                'title' => new \Galette\Entity\Title(\Galette\Entity\Title::MR),
                'id' => false,
                'nick' => false,
                'expected' => 'Mr. DOE John'
            ],
            [
                'name' => 'Doe',
                'surname' => 'John',
                'title' => false,
                'id' => false,
                'nick' => 'foo',
                'expected' => 'DOE John (foo)'
            ],
            [
                'name' => 'Doe',
                'surname' => 'John',
                'title' => false,
                'id' => 42,
                'nick' => false,
                'expected' => 'DOE John (42)'
            ],
            [
                'name' => 'Doe',
                'surname' => 'John',
                'title' => new \Galette\Entity\Title(\Galette\Entity\Title::MR),
                'id' => 42,
                'nick' => 'foo',
                'expected' => 'Mr. DOE John (foo, 42)'
            ],
        ];
    }

    /**
     * Test getNameWithCase
     *
     * @dataProvider nameCaseProvider
     *
     * @param string                      $name     Name
     * @param string                      $surname  Surname
     * @param \Galette\Entity\Title|false $title    Title
     * @param string|false                $id       ID
     * @param string|false                $nick     Nick
     * @param  string                      $expected Expected result
     *
     * @return void
     */
    public function testsGetNameWithCase(string $name, string $surname, $title, $id, $nick, string $expected)
    {
        $this->assertSame(
            $expected,
            \Galette\Entity\Adherent::getNameWithCase(
                $name,
                $surname,
                $title,
                $id,
                $nick,
            )
        );
    }

    /**
     * Change member active status
     *
     * @param bool $active Activation status
     *
     * @return void
     */
    private function changeMemberActivation(bool $active): void
    {
        $check = $this->adh->check(['activite_adh' => $active], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($this->adh->store());
        $this->assertTrue($this->adh->load($this->adh->id));
    }

    /**
     * Test getDueStatus
     *
     * @return void
     */
    public function testGetDueStatus()
    {
        $now = new \DateTime();
        $member = new \Galette\Entity\Adherent($this->zdb);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_UNKNOWN, $member->getDueStatus());

        $this->getMemberOne();

        $this->assertTrue($this->adh->isActive());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_NEVER, $this->adh->getDueStatus());

        //non-active members always have OLD due status
        $this->changeMemberActivation(false);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_OLD, $this->adh->getDueStatus());
        $this->changeMemberActivation(true);

        //create a close to be expired contribution
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $this->adh->id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        //member is up-to-date, close to be expired
        $this->assertTrue($this->adh->load($this->adh->id));
        $this->assertTrue($this->adh->isActive());
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_IMPENDING, $this->adh->getDueStatus());

        //non-active members always have OLD due status
        $this->changeMemberActivation(false);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_OLD, $this->adh->getDueStatus());
        $this->changeMemberActivation(true);

        //create an expired contribution, 29 days ago
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P29D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->cleanContributions();
        $this->createContrib([
            'id_adh'                => $this->adh->id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $due_date->format('Y-m-d'),
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);

        //member is late, but for less than 30 days, no reminder to send
        $this->assertTrue($this->adh->load($this->adh->id));
        $this->assertTrue($this->adh->isActive());
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_LATE, $this->adh->getDueStatus());

        //non-active members always have OLD due status
        $this->changeMemberActivation(false);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_OLD, $this->adh->getDueStatus());
        $this->changeMemberActivation(true);
    }

    /**
     * Clean created contributions
     *
     * @return void
     */
    private function cleanContributions(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }
}
