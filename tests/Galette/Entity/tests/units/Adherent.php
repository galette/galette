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

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * Adherent tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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

        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\DynamicFields\DynamicField::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $delete->where([
            'text_orig' => [
                'Dynamic boolean field',
                'Dynamic date field'
            ]
        ]);
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
    public function testEmpty(): void
    {
        $adh = $this->adh;
        $this->assertFalse($adh->isAdmin());
        $this->assertFalse($adh->admin);
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "admin" directly is discouraged.');
        $this->assertFalse($adh->isStaff());
        $this->assertFalse($adh->staff);
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "staff" directly is discouraged.');
        $this->assertFalse($adh->isDueFree());
        $this->assertFalse($adh->due_free);
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "due_free" directly is discouraged.');
        $this->assertFalse($adh->isGroupMember('any'));
        $this->assertFalse($adh->isGroupManager('any'));
        $this->assertFalse($adh->isCompany());
        $this->assertFalse($adh->isMan());
        $this->assertFalse($adh->isWoman());
        $this->assertTrue($adh->isActive());
        $this->assertTrue($adh->active);
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "active" directly is discouraged.');
        $this->assertFalse($adh->isUp2Date());
        $this->assertFalse($adh->appearsInMembersList());
        $this->assertFalse($adh->appears_in_list);
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "appears_in_list" directly is discouraged.');
        $this->assertFalse($adh->duplicate);
        $this->assertFalse($adh->isDuplicate());
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "duplicate" directly is discouraged.');
        $this->assertEquals([], $adh->groups);
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "groups" directly is discouraged.');
        $this->assertEquals([], $adh->getGroups());
        $this->assertEquals([], $adh->managed_groups);
        $this->expectLogEntry(\Analog::WARNING, 'Calling property "managed_groups" directly is discouraged.');
        $this->assertEquals([], $adh->getManagedGroups());

        $this->assertNull($adh->fake_prop);
        $this->expectLogEntry(\Analog::WARNING, 'Unknown property \'fake_prop\'');

        $this->assertSame($this->default_deps, $adh->deps);
        $this->assertFalse($adh->sendEMail());
        $this->assertSame([], $adh->getErrors());
        $this->assertMatchesRegularExpression('/^Never contributed.+/', $adh->getDues());
    }

    /**
     * Test member load dependencies
     *
     * @return void
     */
    public function testDependencies(): void
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
            'socials'   => true
        ];
        $adh
            ->enableDep('dues')
            ->enableDep('dynamics')
            ->enableDep('children')
            ->enableDep('socials');
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
        $adh->disableDep('socials');
        $this->assertSame($expected, $adh->deps);

        $adh->disableDep('none')->enableDep('anothernone');
        $this->expectLogEntry(\Analog::WARNING, 'dependency none does not exists!');
        $this->expectLogEntry(\Analog::WARNING, 'dependency anothernone does not exists!');
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
        $adh->enableDep('children');
        $this->assertSame($expected, $adh->deps);

        //all deps can be disabled on instanciation
        $adh = new \Galette\Entity\Adherent($this->zdb, null, false);
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

        //dyanmics deps can be used on instanciation
        $adh = new \Galette\Entity\Adherent($this->zdb, null, ['dynamics' => true]);
        $expected = [
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => true,
            'socials'   => false
        ];
        $this->assertSame($expected, $adh->deps);

        $adh->enableAllDeps();
        $expected = [
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true,
            'socials'   => true
        ];
        $this->assertSame($expected, $adh->deps);
    }

    /**
     * Tests getter
     *
     * @return void
     */
    public function testGetterWException(): void
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
    public function testDepsAtConstuct(): void
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
    public function testSimpleMember(): void
    {
        $this->getMemberOne();
        $this->checkMemberOneExpected();

        //load member from db
        $adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $this->checkMemberOneExpected($adh);
    }

    /**
     * Test send email property
     *
     * @return void
     */
    public function testSendEmail(): void
    {
        $this->getMemberOne();
        $this->assertFalse($this->adh->sendEMail());
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $this->adh->setSendmail(true));
        $this->assertTrue($this->adh->sendEMail());
    }

    /**
     * Test isset
     *
     * @return void
     */
    public function testIsset(): void
    {
        $this->getMemberOne();

        foreach ($this->adh->getVirtualProperties() as $property) {
            $this->assertTrue(isset($this->adh->{$property}));
        }

        foreach ($this->adh->getForbiddenProperties() as $property) {
            $this->assertFalse(isset($this->adh->{$property}), $property);
        }

        foreach (array_keys($this->adh->getDeprecatedProperties()) as $property) {
            $this->assertTrue(isset($this->adh->{$property}), $property);
            $this->expectLogEntry(\Analog::WARNING, 'Calling property "' . $property . '" directly is discouraged.');
        }

        $this->assertFalse(isset($this->adh->fake_prop));
        $this->assertTrue(isset($this->adh->name));
    }

    /**
     * Test load form login and email
     *
     * @return void
     */
    public function testLoadForLogin(): void
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
    public function testUpdatePassword(): void
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
    public function testCheckErrors(): void
    {
        global $login;
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isAdmin'))
            ->getMock();
        $login->method('isAdmin')->willReturn(true);

        $adh = $this->adh;

        $data = ['ddn_adh' => 'not a date'];
        $expected = ['- Wrong date format (Y-m-d) for Birth date!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = [
            'ddn_adh'       => '',
            'date_crea_adh' => 'not a date'
        ];
        $expected = ['- Wrong date format (Y-m-d) for Creation date!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        //reste creation date to its default value
        $data = ['date_crea_adh' => date('Y-m-d')];
        $check = $adh->check($data, [], []);
        $this->assertTrue($check);

        $data = ['email_adh' => 'not an email'];
        $expected = ['- Non-valid E-Mail address! (E-Mail)'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = ['login_adh' => 'a'];
        $expected = ['- The username must be composed of at least 2 characters!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = ['login_adh' => 'login@galette'];
        $expected = ['- The username cannot contain the @ character'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = [
            'login_adh' => '',
            'mdp_adh'   => 'short',
            'mdp_adh2'  => 'short'
        ];
        $expected = ['Too short (6 characters minimum, 5 found)'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = ['mdp_adh' => 'mypassword'];
        $expected = ['- The passwords don\'t match!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = [
            'mdp_adh'   => 'mypassword',
            'mdp_adh2'  => 'mypasswor'
        ];
        $expected = ['- The passwords don\'t match!'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = ['id_statut' => 256];
        $expected = ['Status #256 does not exists in database.'];
        $check = $adh->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

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
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = ['groups_adh' => [$g2->getId()]];
        $check = $adh->check($data, [], []);
        $expected = ['You have to select a group you own!'];
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        $data = ['groups_adh' => [$g1->getId()]];
        $check = $adh->check($data, [], []);
        $this->assertTrue($check);

        //staff cannot set admin flag
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isStaff'))
            ->getMock();
        $login->method('isStaff')->willReturn(true);

        //force admin flag to be allowed for everyone
        $fc = $this->getMockBuilder(\Galette\Entity\FieldsConfig::class)
            ->setConstructorArgs([
                $this->zdb,
                \Galette\Entity\Adherent::TABLE,
                $this->members_fields,
                $this->members_fields_cats
            ])
            ->onlyMethods(array('getAllowedFields'))
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

        $data = ['bool_admin_adh' => true];
        $exception_thrown = false;
        try {
            $adh->check($data, [], []);
        } catch (\RuntimeException $e) {
            $exception_thrown = true;
            $this->assertSame('No right to store member #', $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');
        //TODO: add log check in next major
    }

    /**
     * Test picture
     *
     * @return void
     */
    public function testPhoto(): void
    {
        $this->getMemberOne();

        $fakedata = new \Galette\Util\FakeData();
        $this->assertTrue($fakedata->addPhoto($this->adh));
        //Process tries to remove any existing photo
        $this->expectLogEntry(\Analog::ERROR, 'Unable to remove picture database entry for ' . $this->adh->id);

        $this->assertTrue($this->adh->hasPicture());

        //remove photo
        $this->assertTrue($this->adh->picture->delete());
    }

    /**
     * Test canEdit
     *
     * @return void
     */
    public function testCanEdit(): void
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
     * Test canDelete
     *
     * @return void
     */
    public function testCanDelete(): void
    {
        $adh = new \Galette\Entity\Adherent($this->zdb);

        //non authorized
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isGroupManager'))
            ->getMock();
        $this->assertFalse($adh->canDelete($login));

        //admin => authorized
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isAdmin'))
            ->getMock();
        $login->method('isAdmin')->willReturn(true);
        $this->assertTrue($adh->canDelete($login));

        //staff => authorized
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isStaff'))
            ->getMock();
        $login->method('isStaff')->willReturn(true);
        $this->assertTrue($adh->canDelete($login));

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

        $this->assertFalse($adh->canDelete($login));

        $login->method('isGroupManager')->willReturnCallback(
            function ($gid) use ($g1) {
                return $gid === null || $gid == $g1->getId();
            }
        );
        $this->assertFalse($adh->canDelete($login));

        $this->preferences->pref_bool_groupsmanagers_edit_member = true;
        $canDelete = $adh->canDelete($login);
        $this->preferences->pref_bool_groupsmanagers_edit_member = false; //reset
        $this->assertTrue($canDelete);

        //groups managers cannot edit members of the groups they do not own
        $adh->method('getGroups')->willReturn([$g2]);
        $this->assertFalse($adh->canDelete($login));
    }

    /**
     * Test member duplication
     *
     * @return void
     */
    public function testDuplicate(): void
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
    public function testParents(): void
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

        $parent->hasChildren();
        $this->assertFalse($parent->hasChildren());
        $this->expectLogEntry(\Analog::WARNING, 'Children has not been loaded!');

        $parent = new \Galette\Entity\Adherent($this->zdb, $parent->id, ['children' => true]);
        $this->assertTrue($parent->hasChildren());

        //check parent inherited fields
        $this->assertSame(
            [
                'adresse_adh',
                'cp_adh',
                'ville_adh',
                'region_adh',
                'email_adh'
            ],
            $this->adh->getParentFields()
        );
        $this->assertSame($parent->getAddress(), $child->getAddress());
        $this->assertSame($parent->getZipcode(), $child->getZipcode());
        $this->assertSame($parent->getTown(), $child->getTown());
        $this->assertSame($parent->getRegion(), $child->getRegion());
        $this->assertSame($parent->getEmail(), $child->getEmail());

        //set an address to child; to ensure it is not inherited
        $check = $child->check(['adresse_adh' => 'Child address', 'ville_adh' => 'Child town'], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($child->store());
        $child = new \Galette\Entity\Adherent($this->zdb, $child->id, ['parents' => true]);
        $child->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        $this->assertNotSame($parent->getAddress(), $child->getAddress(), $parent->getAddress() . '|' . $child->getAddress());
        $this->assertSame('Child address', $child->getAddress());
        $this->assertSame('', $child->getZipcode());
        $this->assertSame('Child town', $child->getTown());
        $this->assertSame('', $child->getRegion());
        $this->assertSame($parent->getEmail(), $child->getEmail()); //still inherited

        $check = $child->check(['detach_parent' => true], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($child->store());
        $this->assertNull($child->parent);

        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $child->setParent($parent->id));
        $this->assertSame($parent->id, $child->parent->id);
    }

    /**
     * Test XSS/SQL injection
     *
     * @return void
     */
    public function testInjection(): void
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
    public function testCan(): void
    {
        $this->getMemberOne();
        //load member from db
        $member = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);

        $this->assertFalse($member->canShow($this->login));
        $this->assertFalse($member->canCreate($this->login));
        $this->assertFalse($member->canEdit($this->login));
        $this->assertFalse($member->canDelete($this->login));

        //Superadmin can fully change members
        $this->logSuperAdmin();

        $this->assertTrue($member->canShow($this->login));
        $this->assertTrue($member->canCreate($this->login));
        $this->assertTrue($member->canEdit($this->login));
        $this->assertTrue($member->canDelete($this->login));

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
        $this->assertTrue($member->canDelete($this->login));

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
        $this->assertFalse($member->canDelete($this->login));

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
        $this->assertTrue($child->canDelete($this->login));

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
     * @param int|false                   $id       ID
     * @param string|false                $nick     Nick
     * @param string                      $expected Expected result
     *
     * @return void
     */
    public function testsGetNameWithCase(
        string $name,
        string $surname,
        \Galette\Entity\Title|false $title,
        int|false $id,
        string|false $nick,
        string $expected
    ): void {
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
    public function testGetDueStatus(): void
    {
        $this->logSuperAdmin();
        $now = new \DateTime();
        $member = new \Galette\Entity\Adherent($this->zdb);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_UNKNOWN, $member->getDueStatus());
        $this->assertMatchesRegularExpression('/^Never contributed.+/', $member->getDues());

        $this->getMemberOne();

        $this->assertTrue($this->adh->isActive());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_NEVER, $this->adh->getDueStatus());

        //non-active members always have OLD due status
        $this->changeMemberActivation(false);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_OLD, $this->adh->getDueStatus());
        $this->assertSame('Never contributed', $this->adh->getDues());
        $this->changeMemberActivation(true);

        //create a close to be expired contribution
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->logSuperAdmin();
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
        $this->login->logout();

        //member is up-to-date, close to be expired
        $this->assertTrue($this->adh->load($this->adh->id));
        $this->assertTrue($this->adh->isActive());
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_IMPENDING, $this->adh->getDueStatus());
        $this->assertSame(
            '30 days remaining (ending on ' . $due_date->format('Y-m-d') . ')',
            $this->adh->getDues()
        );

        //non-active members always have OLD due status
        $this->changeMemberActivation(false);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_OLD, $this->adh->getDueStatus());
        $this->assertSame(
            '30 days remaining (ending on ' . $due_date->format('Y-m-d') . ')',
            $this->adh->getDues()
        );
        $this->changeMemberActivation(true);

        //create an expired contribution, 29 days ago
        $due_date = clone $now;
        $due_date->sub(new \DateInterval('P29D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->logSuperAdmin();
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
        $this->login->logout();

        //member is late, but for less than 30 days, no reminder to send
        $this->assertTrue($this->adh->load($this->adh->id));
        $this->assertTrue($this->adh->isActive());
        $this->assertFalse($this->adh->isUp2Date());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_LATE, $this->adh->getDueStatus());
        $this->assertSame(
            'Late of 29 days (since ' . $due_date->format('Y-m-d') . ')',
            $this->adh->getDues()
        );

        //non-active members always have OLD due status
        $this->changeMemberActivation(false);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_OLD, $this->adh->getDueStatus());
        $this->assertSame(
            'No longer member',
            $this->adh->getDues()
        );
        $this->changeMemberActivation(true);
        $this->login->logout();
    }

    /**
     * Test isSponsor
     *
     * @return void
     */
    public function testIsSponsor(): void
    {
        $now = new \DateTime();
        $member = new \Galette\Entity\Adherent($this->zdb);
        $this->assertSame(\Galette\Entity\Contribution::STATUS_UNKNOWN, $member->getDueStatus());

        $this->getMemberOne();

        $this->assertTrue($this->adh->isActive());
        $this->assertFalse($this->adh->isSponsor());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_NEVER, $this->adh->getDueStatus());

        //create a close to be expired contribution
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P30D'));
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P1Y'));

        $this->logSuperAdmin();
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
        $this->login->logout();

        //member is up-to-date, close to be expired - still not sponsor
        $this->assertTrue($this->adh->load($this->adh->id));
        $this->assertTrue($this->adh->isActive());
        $this->assertTrue($this->adh->isUp2Date());
        $this->assertSame(\Galette\Entity\Contribution::STATUS_IMPENDING, $this->adh->getDueStatus());
        $this->assertFalse($this->adh->isSponsor());

        //create a donation
        $this->logSuperAdmin();
        $this->createContrib([
            'id_adh'                => $this->adh->id,
            'id_type_cotis'         => 5, //donation in money
            'montant_cotis'         => '10',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_enreg'            => $begin_date->format('Y-m-d'),
            'date_debut_cotis'      => $begin_date->format('Y-m-d')
        ]);
        $this->login->logout();

        $this->assertTrue($this->adh->load($this->adh->id));
        $this->assertTrue($this->adh->isSponsor());
    }

    /**
     * Test dynamic boolean field uncheck
     * @see https://bugs.galette.eu/issues/1472
     *
     * @return void
     */
    public function testDynamicBooleanUncheck(): void
    {
        $this->logSuperAdmin();
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);

        //new dynamic field, of type boolean.
        $cfield_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic boolean field',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::BOOLEAN,
            'field_required'    => 0,
            'field_repeat'      => 1
        ];

        $cdf = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $cdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $cdf->getWarnings()));

        //create/load member
        $this->getMemberOne();

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $dboolean = array_pop($dfields);
        $this->assertSame(
            [
                0 => [
                    'item_id'       => $adh->id,
                    'field_form'    => 'adh',
                    'val_index'     => 1,
                    'field_val'     => '',
                    'is_new'        => true,
                ]
            ],
            $dynamics->getValues($dboolean->getId())
        );

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne() + [
            'info_field_' . $dboolean->getId() . '_1'   => '1'
        ];

        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $this->assertArrayHasKey($dboolean->getId(), $dfields);
        $dboolean = $dfields[$dboolean->getId()];
        $this->assertSame(
            [
                0 => [
                    'item_id'       => "$adh->id",
                    'field_form'    => 'adh',
                    'val_index'     => "1",
                    'field_val'     => "1",
                ]
            ],
            $dynamics->getValues($dboolean->getId())
        );

        //remove boolean value
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        //no dynamic boolean means boolean false.
        $data = $this->dataAdherentOne();

        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $this->assertArrayHasKey($dboolean->getId(), $dfields);
        $dboolean = $dfields[$dboolean->getId()];
        $this->assertSame(
            [
                0 => [
                    'item_id'       => $adh->id,
                    'field_form'    => 'adh',
                    'val_index'     => 1,
                    'field_val'     => '',
                    'is_new'        => true
                ]
            ],
            $dynamics->getValues($dboolean->getId())
        );
    }

    /**
     * Test dynamic dates
     * @see https://bugs.galette.eu/issues/1881
     *
     * @return void
     */
    public function testDynamicDates(): void
    {
        $this->logSuperAdmin();
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);

        //new dynamic field, of type date.
        $cfield_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic date field',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::DATE,
            'field_required'    => 0,
            'field_repeat'      => 1
        ];

        $cdf = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $cdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $cdf->getWarnings()));

        //create/load member
        $this->getMemberOne();

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $ddate = array_pop($dfields);
        $this->assertSame(
            [
                0 => [
                    'item_id'       => $adh->id,
                    'field_form'    => 'adh',
                    'val_index'     => 1,
                    'field_val'     => '',
                    'is_new'        => true,
                ]
            ],
            $dynamics->getValues($ddate->getId())
        );

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne() + [
            'info_field_' . $ddate->getId() . '_1'   => date('Y-m-d')
        ];

        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $this->assertArrayHasKey($ddate->getId(), $dfields);
        $ddate = $dfields[$ddate->getId()];
        $this->assertSame(
            [
                0 => [
                    'item_id'       => "$adh->id",
                    'field_form'    => 'adh',
                    'val_index'     => "1",
                    'field_val'     => date('Y-m-d'),
                ]
            ],
            $dynamics->getValues($ddate->getId())
        );

        //remove date value
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne() + [
            'info_field_' . $ddate->getId() . '_1'   => ''
        ];

        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $this->assertArrayHasKey($ddate->getId(), $dfields);
        $ddate = $dfields[$ddate->getId()];
        $this->assertSame(
            [
                0 => [
                    'item_id'       => $adh->id,
                    'field_form'    => 'adh',
                    'val_index'     => 1,
                    'field_val'     => '',
                    'is_new'        => true
                ]
            ],
            $dynamics->getValues($ddate->getId())
        );

        //test with wrong date format
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne() + [
                'info_field_' . $ddate->getId() . '_1'   => date('d/m/Y')
            ];

        $check = $adh->check($data, [], []);
        $this->assertIsArray($check);
        $this->assertContains('- Wrong date format (Y-m-d) for Dynamic date field!', $check);
        $this->expectLogEntry(\Analog::ERROR, '- Wrong date format (Y-m-d) for Dynamic date field!');

        //test with localized date. Will be stored as default date format (Y-m-d)
        $this->i18n->changeLanguage('fr_FR');
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne() + [
                'info_field_' . $ddate->getId() . '_1'   => date('d/m/Y')
            ];

        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $this->assertArrayHasKey($ddate->getId(), $dfields);
        $ddate = $dfields[$ddate->getId()];
        $this->assertSame(
            [
                0 => [
                    'item_id'       => "$adh->id",
                    'field_form'    => 'adh',
                    'val_index'     => "1",
                    'field_val'     => date('Y-m-d'),
                ]
            ],
            $dynamics->getValues($ddate->getId())
        );

        //still localized, but using raw format is also OK
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne() + [
            'info_field_' . $ddate->getId() . '_1'   => date('Y-m-d')
        ];

        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $dynamics = $adh->getDynamicFields();
        $dfields = $dynamics->getFields();
        $this->assertCount(1, $dfields);
        $this->assertArrayHasKey($ddate->getId(), $dfields);
        $ddate = $dfields[$ddate->getId()];
        $this->assertSame(
            [
                0 => [
                    'item_id'       => "$adh->id",
                    'field_form'    => 'adh',
                    'val_index'     => "1",
                    'field_val'     => date('Y-m-d'),
                ]
            ],
            $dynamics->getValues($ddate->getId())
        );

        $this->i18n->changeLanguage('en_US');

        //test with wrong date should fail
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            $this->adh->id,
            ['dynamics' => true] + $this->adh->deps
        );
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne() + [
                'info_field_' . $ddate->getId() . '_1'   => '2025-13-13'
            ];

        $check = $adh->check($data, [], []);
        $this->assertIsArray($check);
        $this->assertContains('- Wrong date format (Y-m-d) for Dynamic date field!', $check);
        $this->expectLogEntry(\Analog::ERROR, '- Wrong date format (Y-m-d) for Dynamic date field!');
    }

    /**
     * Test group membership
     *
     * @return void
     */
    public function testTitle(): void
    {
        $this->getMemberOne();

        //set title
        $check = $this->adh->check(['titre_adh' => \Galette\Entity\Title::MRS], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $this->adh->store();
        $this->assertTrue($store);

        $this->getMemberOne();
        $this->assertSame('Mrs.', $this->adh->stitle);
    }

    /**
     * Test group membership
     *
     * @return void
     */
    public function testGroupMembership(): void
    {
        $adh1 = $this->getMemberOne();
        $adh2 = $this->getMemberTwo();

        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$adh2]));

        $g2 = new \Galette\Entity\Group();
        $g2->setName('Group 2');
        $this->assertTrue($g2->store());
        $this->assertTrue($g2->setMembers([$adh1, $adh2]));

        //do not load group dependency, to make sure loadGroups() is called
        $adh = new \Galette\Entity\Adherent($this->zdb, $adh1->id, ['groups' => false]);
        $this->assertFalse($adh->isGroupMember($g1->getName()));
        $this->assertTrue($adh->isGroupMember($g2->getName()));
        $this->assertFalse($adh->isGroupManager($g1->getName()));
        $this->assertFalse($adh->isGroupManager($g2->getName()));
        $this->assertFalse($adh->isGroupManager(null));

        //make member1 admin
        $check = $adh1->check(['bool_admin_adh' => true], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh1->store();
        $this->assertTrue($store);

        //do not load group dependency, to make sure loadGroups() is called
        $adh = new \Galette\Entity\Adherent($this->zdb, $adh1->id, ['groups' => false]);
        $this->assertTrue($adh->isAdmin());
        $this->assertFalse($adh->isGroupManager($g1->getName()));
        $this->assertFalse($adh->isGroupManager($g2->getName()));
        $this->assertTrue($adh->isGroupManager(null));

        //do not load group dependency, to make sure loadGroups() is called
        $adh = new \Galette\Entity\Adherent($this->zdb, $adh2->id, ['groups' => false]);
        $this->assertFalse($adh->isGroupMember($g1->getName()));
        $this->assertTrue($adh->isGroupMember($g2->getName()));
        $this->assertTrue($adh->isGroupManager($g1->getName()));
        $this->assertFalse($adh->isGroupManager($g2->getName()));
        $this->assertTrue($adh->isGroupManager(null));
    }
}
