<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contribution tests
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
 * @since     2017-06-11
 */

namespace Galette\Entity\test\units;

use \atoum;

/**
 * Contribution tests class
 *
 * @category  Entity
 * @name      Contribution
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-06-11
 */
class Contribution extends atoum
{
    private $zdb;
    private $i18n;
    private $preferences;
    private $session;
    private $login;
    private $history;
    private $seed = 95842354;
    private $default_deps;
    private $adh;
    private $contrib;
    private $ids = [];
    private $members_fields;

    /**
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown()
    {
        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
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

        global $zdb, $login, $hist, $i18n; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;

        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;
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
     * Look in database if test contrib already exists
     *
     * @return false|ResultSet
     */
    private function contribExists()
    {
        $select = $this->zdb->select(\Galette\Entity\Contribution::TABLE, 'c');
        $select->where(array('c.info_cotis' => 'FAKER' . $this->seed));

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
     * Create test contribution in database
     *
     * @return void
     */
    private function createContribution()
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

        $data = $fakedata->fakeContrib($this->adh->id);
        $this->createContrib($data);
    }

    /**
     * Loads member from a resultset
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function loadAdherent($rs)
    {
        $this->adh = new \Galette\Entity\Adherent($this->zdb, $rs);
    }

    /**
     * Loads contribution from a resultset
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function loadContribution($rs)
    {
        $this->adh = new \Galette\Entity\Contribution($this->zdb, $this->login, $rs);
    }


    /**
     * Test empty contribution
     *
     * @return void
     */
    public function testEmpty()
    {
        $contrib = $this->contrib;
        $this->variable($contrib->id)->isNull();
        $this->variable($contrib->isCotis())->isNull();
        $this->variable($contrib->is_cotis)->isNull();
        $this->variable($contrib->date)->isNull();
        $this->variable($contrib->begin_date)->isNull();
        $this->variable($contrib->end_date)->isNull();
        $this->variable($contrib->raw_date)->isNull();
        $this->variable($contrib->raw_begin_date)->isNull();
        $this->variable($contrib->raw_end_date)->isNull();
        $this->string($contrib->duration)->isEmpty();
        $this->variable($contrib->payment_type)->isNull();
        $this->string($contrib->spayment_type)->isIdenticalTo('-');
        $this->variable($contrib->model)->isNull();
        $this->variable($contrib->member)->isNull();
        $this->variable($contrib->type)->isNull();
        $this->variable($contrib->amount)->isNull();
        $this->variable($contrib->orig_amount)->isNull();
        $this->variable($contrib->info)->isNull();
        $this->variable($contrib->transaction)->isNull();
        $this->array($contrib->fields)
            ->hasSize(11)
            ->hasKeys([
                \Galette\Entity\Contribution::PK,
                \Galette\Entity\Adherent::PK,
                \Galette\Entity\ContributionsTypes::PK,
                'montant_cotis',
                'type_paiement_cotis',
                'info_cotis',
                'date_debut_cotis'
            ]);

        $this->string($contrib->getRowClass())->isIdenticalTo('cotis-give');
        $this->variable($contrib::getDueDate($this->zdb, 1))->isNull();
        $this->boolean($contrib->isTransactionPart())->isFalse();
        $this->boolean($contrib->isTransactionPartOf(1))->isFalse();
        $this->string($contrib->getRawType())->isIdenticalTo('donation');
        $this->string($contrib->getTypeLabel())->isIdenticalTo('Donation');
        $this->string($contrib->getPaymentType())->isIdenticalTo('-');
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
     * Create contribution from data
     *
     * @param array $data Data to use to create contribution
     *
     * @return \Galette\Entity\Contribution
     */
    public function createContrib(array $data)
    {
        $contrib = $this->contrib;
        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $contrib->store();
        $this->boolean($store)->isTrue();

        $this->ids[] = $contrib->id;
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
     * Check contributions expecteds
     *
     * @param Contribution $contrib       Contribution instance, if any
     * @param array        $new_expecteds Changes on expected values
     *
     * @return void
     */
    private function checkContribExpected($contrib = null, $new_expecteds = [])
    {
        if ($contrib === null) {
            $contrib = $this->contrib;
        }

        $expecteds = [
            'id_adh' => "{$this->adh->id}",
            'id_type_cotis' => 1,
            'montant_cotis' => '92',
            'type_paiement_cotis' => '3',
            'info_cotis' => 'FAKER95842354',
            'date_enreg' => '2016-10-18',
            'date_debut_cotis' => '2017-01-12',
            'date_fin_cotis' => '2018-01-12',
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        foreach ($expecteds as $key => $value) {
            $property = $this->contrib->fields[$key]['propname'];
            switch ($key) {
                case \Galette\Entity\ContributionsTypes::PK:
                    $ct = $this->contrib->type;
                    if ($ct instanceof \Galette\Entity\ContributionsTypes) {
                        $this->integer((int)$ct->id)->isIdenticalTo($value);
                    } else {
                        $this->integer($ct)->isIdenticalTo($value);
                    }
                    break;
                default:
                    $this->variable($contrib->$property)->isIdenticalTo($value);
                    break;
            }
        }

        //member is now up-to-date
        $this->string($this->adh->getRowClass())->isIdenticalTo('active cotis-ok');
        $this->string($this->adh->due_date)->isIdenticalTo($this->contrib->end_date);
        $this->boolean($this->adh->isUp2Date())->isTrue();
    }

    /**
     * Test contribution creation
     *
     * @return void
     */
    public function testCreation()
    {
        $rs = $this->adhExists();
        if ($rs === false) {
            $this->createAdherent();
        } else {
            $this->loadAdherent($rs->current());
        }

        $this->checkMemberExpected();

        //create contribution for member
        $rs = $this->contribExists();
        if ($rs === false) {
            $this->createContribution();
        } else {
            $this->loadContribution($rs->current());
        }

        //load member from db
        $this->adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        $this->checkContribExpected();
    }
}
