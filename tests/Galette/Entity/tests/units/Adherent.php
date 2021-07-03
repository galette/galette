<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Adherent tests
 *
 * PHP version 5
 *
 * Copyright © 2017 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
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
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-04-17
 */
class Adherent extends GaletteTestCase
{
    protected $seed = 95842354;
    private $default_deps;

    /**
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown()
    {
        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Set up tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        parent::beforeTestMethod($testMethod);
        $this->initStatus();

        $this->default_deps = [
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => false
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
        $this->boolean($adh->isAdmin())->isFalse();
        $this->boolean($adh->admin)->isFalse();
        $this->boolean($adh->isStaff())->isFalse();
        $this->boolean($adh->staff)->isFalse();
        $this->boolean($adh->isDueFree())->isFalse();
        $this->boolean($adh->due_free)->isFalse();
        $this->boolean($adh->isGroupMember('any'))->isFalse();
        $this->boolean($adh->isGroupManager('any'))->isFalse();
        $this->boolean($adh->isCompany())->isFalse();
        $this->boolean($adh->isMan())->isFalse();
        $this->boolean($adh->isWoman())->isFalse();
        $this->boolean($adh->isActive())->isTrue();
        $this->boolean($adh->active)->isTrue();
        $this->boolean($adh->isUp2Date())->isFalse();
        $this->boolean($adh->appearsInMembersList())->isFalse();
        $this->boolean($adh->appears_in_list)->isFalse();

        $this->variable($adh->fake_prop)->isNull();

        $this->array($adh->deps)->isIdenticalTo($this->default_deps);
    }

    /**
     * Tests getter
     *
     * @return void
     */
    public function testGetterWException()
    {
        $adh = $this->adh;

        $this->exception(
            function () use ($adh) {
                $adh->row_classes;
            }
        )->isInstanceOf('RuntimeException');
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
            'dynamics'  => false
        ];
        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            null,
            $deps
        );

        $this->array($adh->deps)->isIdenticalTo($deps);

        $adh = new \Galette\Entity\Adherent(
            $this->zdb,
            null,
            'not an array'
        );
        $this->array($adh->deps)->isIdenticalTo($this->default_deps);
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

        $this->variable($this->adh->email)->isIdenticalTo($this->adh->getEmail());

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
        $this->boolean($pw_checked)->isTrue();

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
        $this->array($check)->isIdenticalTo($expected);

        $data = [
            'ddn_adh'       => '',
            'date_crea_adh' => 'not a date'
        ];
        $expected = ['- Wrong date format (Y-m-d) for Creation date!'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        //reste creation date to its default value
        $data = ['date_crea_adh' => date('Y-m-d')];
        $check = $adh->check($data, [], []);
        $this->boolean($check)->isTrue();

        $data = ['email_adh' => 'not an email'];
        $expected = ['- Non-valid E-Mail address! (E-Mail)'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        $data = [
            'email_adh' => '',
            'url_adh'   => 'mywebsite'
        ];
        $expected = ['- Non-valid Website address! Maybe you\'ve skipped the http://?'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        $data = ['url_adh' => 'http://'];
        $expected = ['- Non-valid Website address! Maybe you\'ve skipped the http://?'];
        $check = $adh->check($data, [], []);
        $this->boolean($check)->isTrue($expected);
        $this->variable($adh->_website)->isIdenticalTo('');

        $data = ['login_adh' => 'a'];
        $expected = ['- The username must be composed of at least 2 characters!'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        $data = ['login_adh' => 'login@galette'];
        $expected = ['- The username cannot contain the @ character'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        $data = [
            'login_adh' => '',
            'mdp_adh'   => 'short',
            'mdp_adh2'  => 'short'
        ];
        $expected = ['Too short (6 characters minimum, 5 found)'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        $data = ['mdp_adh' => 'mypassword'];
        $expected = ['- The passwords don\'t match!'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        $data = [
            'mdp_adh'   => 'mypassword',
            'mdp_adh2'  => 'mypasswor'
        ];
        $expected = ['- The passwords don\'t match!'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);

        $data = ['id_statut' => 256];
        $expected = ['Status #256 does not exists in database.'];
        $check = $adh->check($data, [], []);
        $this->array($check)->isIdenticalTo($expected);
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
        $this->boolean($fakedata->addPhoto($this->adh))->isTrue();

        $this->boolean($this->adh->hasPicture())->isTrue();

        //remove photo
        $this->boolean($this->adh->picture->delete())->isTrue();
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
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->boolean($adh->canEdit($login))->isFalse();

        //admin => authorized
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->calling($login)->isAdmin = true;
        $this->boolean($adh->canEdit($login))->isTrue();

        //staff => authorized
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->calling($login)->isStaff = true;
        $this->boolean($adh->canEdit($login))->isTrue();

        //group managers
        $adh = new \mock\Galette\Entity\Adherent($this->zdb);

        $g1 = new \mock\Galette\Entity\Group();
        $this->calling($g1)->getId = 1;
        $g2 = new \mock\Galette\Entity\Group();
        $this->calling($g1)->getId = 2;

        $this->calling($adh)->getGroups = [$g1, $g2];
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->boolean($adh->canEdit($login))->isFalse();

        $this->calling($login)->isGroupManager = true;
        $this->boolean($adh->canEdit($login))->isTrue();
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

        $this->string($adh->others_infos_admin)->contains('Duplicated from');
        $this->variable($adh->email)->isNull();
        $this->variable($adh->id)->isNull();
        $this->variable($adh->login)->isNull();
        $this->variable($adh->birthdate)->isNull();
        $this->variable($adh->surname)->isNull();
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

        $child_data = $this->dataAdherentOne() + [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $parent->id,
        ];
        $child = $this->createMember($child_data);

        $this->string($child->name)->isIdenticalTo($child_data['nom_adh']);
        $this->object($child->parent)->isInstanceOf('\Galette\Entity\Adherent');
        $this->integer($child->parent->id)->isIdenticalTo($parent->id);

        $check = $child->check(['detach_parent' => true], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();
        $this->boolean($child->store())->isTrue();
        $this->variable($child->parent)->isNull();
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
            'info_public_adh'   => 'Any <script>console.log("useful");</script> information'
        ] + $this->dataAdherentOne();
        $member = $this->createMember($data);

        $this->string($member->sfullname)->isIdenticalTo('DOE Johny Console.log("anything");');
        $this->string($member->others_infos)->isIdenticalTo('Any console.log("useful"); information');
    }
}
