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

use \atoum;

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
class Adherent extends atoum
{
    private $zdb;
    private $members_fields;
    private $members_fields_cats;
    private $i18n;
    private $preferences;
    private $session;
    private $login;
    private $history;
    private $seed = 95842354;
    private $default_deps;
    private $adh;
    private $ids = [];

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
        $this->zdb = new \Galette\Core\Db();

        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;
        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
        $this->members_fields_cats = $members_fields_cats;

        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );

        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);
        $this->history = new \Galette\Core\History($this->zdb, $this->login);

        if (!defined('_CURRENT_TEMPLATE_PATH')) {
            define(
                '_CURRENT_TEMPLATE_PATH',
                GALETTE_TEMPLATES_PATH . $this->preferences->pref_theme . '/'
            );
        }

        $this->default_deps = [
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => false,
            'children'  => false
        ];

        global $zdb, $login, $hist, $i18n; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Look in database if test member already exists
     *
     * @return false|ResultSet
     */
    private function adhExists()
    {
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(array('a.fingerprint' => 'FAKER' . $this->seed));

        $results = $this->zdb->execute($select);
        if ($results->count() === 0) {
            return false;
        } else {
            return $results;
        }
    }

    /**
     * Create test user in database
     *
     * @return void
     */
    private function createAdherent()
    {
        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);
        $fakedata
            ->setSeed($this->seed)
            ->setDependencies(
                $this->preferences,
                $this->members_fields,
                $this->history,
                $this->login
            );

        $data = $fakedata->fakeMember();
        $this->createMember($data);
    }

    /**
     * Loads member from a resultset
     *
     * @param integer $id Id
     *
     * @return void
     */
    private function loadAdherent($id)
    {
        $this->adh = new \Galette\Entity\Adherent($this->zdb, (int)$id);
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
     * Create member from data
     *
     * @param array $data Data to use to create member
     *
     * @return \Galette\Entity\Adherent
     */
    public function createMember(array $data)
    {
        $adh = $this->adh;
        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $adh->store();
        $this->boolean($store)->isTrue();

        $this->ids[] = $adh->id;
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
            'children'  => false
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
     * Check members expecteds
     *
     * @param Adherent $adh           Member instance, if any
     * @param array    $new_expecteds Changes on expected values
     *
     * @return void
     */
    private function checkMemberExpected($adh = null, $new_expecteds = [])
    {
        if ($adh === null) {
            $adh = $this->adh;
        }

        $expecteds = [
            'nom_adh' => 'Durand',
            'prenom_adh' => 'René',
            'ville_adh' => 'Martel',
            'cp_adh' => '07 926',
            'adresse_adh' => '66, boulevard De Oliveira',
            'email_adh' => 'meunier.josephine@ledoux.com',
            'login_adh' => 'arthur.hamon',
            'mdp_adh' => 'J^B-()f',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => true,
            'sexe_adh' => 0,
            'prof_adh' => 'Chef de fabrication',
            'titre_adh' => null,
            'ddn_adh' => '1934-06-08',
            'lieu_naissance' => 'Gonzalez-sur-Meunier',
            'pseudo_adh' => 'ubertrand',
            'cp_adh' => '39 069',
            'pays_adh' => 'Antarctique',
            'tel_adh' => '0439153432',
            'url_adh' => 'http://bouchet.com/',
            'activite_adh' => true,
            'id_statut' => 9,
            'pref_lang' => 'fr_FR',
            'fingerprint' => 'FAKER95842354',
            'societe_adh' => ''
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        foreach ($expecteds as $key => $value) {
            $property = $this->members_fields[$key]['propname'];
            switch ($key) {
                case 'bool_admin_adh':
                    $this->boolean($adh->isAdmin())->isIdenticalTo($value);
                    break;
                case 'bool_exempt_adh':
                    $this->boolean($adh->isDueFree())->isIdenticalTo($value);
                    break;
                case 'bool_display_info':
                    $this->boolean($adh->appearsInMembersList())->isIdenticalTo($value);
                    break;
                case 'activite_adh':
                    $this->boolean($adh->isActive())->isIdenticalTo($value);
                    break;
                case 'mdp_adh':
                    $pw_checked = password_verify($value, $adh->password);
                    $this->boolean($pw_checked)->isTrue();
                    break;
                case 'ddn_adh':
                    //rely on age, not on birthdate
                    $this->variable($adh->$property)->isNotNull();
                    $this->string($adh->getAge())->isIdenticalTo(' (82 years old)');
                    break;
                default:
                    $this->variable($adh->$property)->isIdenticalTo($value);
                    break;
            }
        }

        $d = \DateTime::createFromFormat('Y-m-d', $expecteds['ddn_adh']);

        $expected_str = ' (82 years old)';
        $this->string($adh->getAge())->isIdenticalTo($expected_str);
        $this->boolean($adh->hasChildren())->isFalse();
        $this->boolean($adh->hasParent())->isFalse();
        $this->boolean($adh->hasPicture())->isFalse();

        $this->string($adh->sadmin)->isIdenticalTo('No');
        $this->string($adh->sdue_free)->isIdenticalTo('No');
        $this->string($adh->sappears_in_list)->isIdenticalTo('Yes');
        $this->string($adh->sstaff)->isIdenticalTo('No');
        $this->string($adh->sactive)->isIdenticalTo('Active');
        $this->variable($adh->stitle)->isNull();
        $this->string($adh->sstatus)->isIdenticalTo('Non-member');
        $this->string($adh->sfullname)->isIdenticalTo('DURAND René');
        $this->string($adh->saddress)->isIdenticalTo('66, boulevard De Oliveira');
        $this->string($adh->sname)->isIdenticalTo('DURAND René');

        $this->string($adh->getAddress())->isIdenticalTo($expecteds['adresse_adh']);
        $this->string($adh->getAddressContinuation())->isIdenticalTo('');
        $this->string($adh->getZipcode())->isIdenticalTo($expecteds['cp_adh']);
        $this->string($adh->getTown())->isIdenticalTo($expecteds['ville_adh']);
        $this->string($adh->getCountry())->isIdenticalTo($expecteds['pays_adh']);

        $this->string($adh::getSName($this->zdb, $adh->id))->isIdenticalTo('DURAND René');
        $this->string($adh->getRowClass())->isIdenticalTo('active cotis-never');
    }

    /**
     * Test simple member creation
     *
     * @return void
     */
    public function testSimpleMember()
    {
        $rs = $this->adhExists();
        if ($rs === false) {
            $this->createAdherent();
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }

        $this->checkMemberExpected();

        //load member from db
        $adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $this->checkMemberExpected($adh);
    }

    /**
     * Test load form login and email
     *
     * @return void
     */
    public function testLoadForLogin()
    {
        $rs = $this->adhExists();
        if ($rs === false) {
            $this->createAdherent();
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }

        $login = $this->adh->login;
        $email = $this->adh->email;

        $this->variable($this->adh->email)->isIdenticalTo($this->adh->getEmail());

        $adh = new \Galette\Entity\Adherent($this->zdb, $login);
        $this->checkMemberExpected($adh);

        $adh = new \Galette\Entity\Adherent($this->zdb, $email);
        $this->checkMemberExpected($adh);
    }

    /**
     * Test password updating
     *
     * @return void
     */
    public function testUpdatePassword()
    {
        $rs = $this->adhExists();
        if ($rs === false) {
            $this->createAdherent();
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }

        $this->checkMemberExpected();

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

        $data = ['date_crea_adh' => 'not a date'];
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

        $data = ['url_adh' => 'mywebsite'];
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

        $data = ['mdp_adh' => 'short'];
        $expected = ['- The password must be of at least 6 characters!'];
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
        $rs = $this->adhExists();
        if ($rs === false) {
            $this->createAdherent();
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }

        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);
        $fakedata
            ->setSeed($this->seed)
            ->setDependencies(
                $this->preferences,
                $this->members_fields,
                $this->history,
                $this->login
            );
        $fakedata->addPhoto($this->adh);

        $this->boolean($this->adh->hasPicture())->isTrue();
    }
}
