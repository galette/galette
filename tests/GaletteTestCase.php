<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette tests
 *
 * PHP version 5
 *
 * Copyright © 2020-2021 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.eu
 * @since     2020-12-27
 */

namespace Galette;

use atoum;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\I18n;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;

/**
 * Galette tests case main class
 *
 * @category  Core
 * @name      GaletteTestCase
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.eu
 * @since     2020-12-27
 */
abstract class GaletteTestCase extends atoum
{
    /** @var Db */
    protected $zdb;
    protected $members_fields;
    protected $members_fields_cats;
    /** @var I18n */
    protected $i18n;
    /** @var Preferences */
    protected $preferences;
    protected $session;
    /** @var Login */
    protected $login;
    /** @var History */
    protected $history;
    protected $logger_storage = '';

    /** @var Adherent */
    protected $adh;
    /** @var Contribution */
    protected $contrib;
    protected $adh_ids = [];
    protected $contrib_ids = [];
    /** @var \mock\Slim\Router */
    protected $mocked_router;
    /** @var array */
    protected $flash_data;
    /** @var \Slim\Flash\Messages */
    protected $flash;
    protected $container;
    protected $request;
    protected $response;
    protected $seed;
    protected $excluded_after_methods = [];

    /**
     * Set up tests
     *
     * @param stgring $method Method tested
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->mocked_router = new \mock\Slim\Router();
        $this->calling($this->mocked_router)->pathFor = function ($name, $params) {
            return $name;
        };
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $app =  new \Galette\Core\SlimApp();
        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set('flash', $this->flash);
        $container->set(Slim\Flash\Messages::class, $this->flash);
        $container->set('router', $this->mocked_router);
        $container->set(Slim\Router::class, $this->mocked_router);

        $this->container = $container;

        $this->zdb = $container->get('zdb');
        $this->i18n = $container->get('i18n');
        $this->login = $container->get('login');
        $this->preferences = $container->get('preferences');
        $this->history = $container->get('history');
        $this->members_fields = $container->get('members_fields');
        $this->members_fields_cats = $container->get('members_fields_cats');
        $this->request = $container->get('request');
        $this->response = $container->get('response');

        global $zdb, $login, $hist, $i18n, $container, $galette_log_var; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;
        $container = $this->container;
        $galette_log_var = $this->logger_storage;
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql' && !in_array($method, $this->excluded_after_methods)) {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
        }
    }

    /**
     * Loads member from a resultset
     *
     * @param integer $id Id
     *
     * @return void
     */
    protected function loadAdherent($id)
    {
        $this->adh = new \Galette\Entity\Adherent($this->zdb, (int)$id);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Get Faker data for one member
     *
     * @return array
     */
    protected function dataAdherentOne(): array
    {
        $bdate = new \DateTime(date('Y') . '-12-26');
        //member is expected to be 82 years old
        $years = 82;
        $now = new \DateTime();
        if ($now <= $bdate) {
            ++$years;
        }
        $bdate->sub(new \DateInterval('P' . $years . 'Y'));
        $data = [
            'nom_adh' => 'Durand',
            'prenom_adh' => 'René',
            'ville_adh' => 'Martel',
            'cp_adh' => '39 069',
            'adresse_adh' => '66, boulevard De Oliveira',
            'email_adh' => 'meunier.josephine@ledoux.com',
            'login_adh' => 'arthur.hamon',
            'mdp_adh' => 'J^B-()f',
            'mdp_adh2' => 'J^B-()f',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => true,
            'sexe_adh' => 0,
            'prof_adh' => 'Chef de fabrication',
            'titre_adh' => null,
            'ddn_adh' => $bdate->format('Y-m-d'),
            'lieu_naissance' => 'Gonzalez-sur-Meunier',
            'pseudo_adh' => 'ubertrand',
            'pays_adh' => 'Antarctique',
            'tel_adh' => '0439153432',
            'url_adh' => 'http://bouchet.com/',
            'activite_adh' => true,
            'id_statut' => 9,
            'date_crea_adh' => '2020-06-10',
            'pref_lang' => 'en_US',
            'fingerprint' => 'FAKER' . $this->seed,
        ];
        return $data;
    }

    /**
     * Get Faker data for second member
     *
     * @return array
     */
    protected function dataAdherentTwo(): array
    {
        $bdate = new \DateTime(date('Y') . '-09-13');
        //member is expected to be 28 years old
        $years = 28;
        $now = new \DateTime();
        if ($now <= $bdate) {
            ++$years;
        }
        $bdate->sub(new \DateInterval('P' . $years . 'Y'));

        $data = [
            'nom_adh' => 'Hoarau',
            'prenom_adh' => 'Lucas',
            'ville_adh' => 'Reynaudnec',
            'cp_adh' => '63077',
            'adresse_adh' => '2, boulevard Legros',
            'email_adh' => 'phoarau@tele2.fr',
            'login_adh' => 'nathalie51',
            'mdp_adh' => 'T.u!IbKOi|06',
            'mdp_adh2' => 'T.u!IbKOi|06',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => false,
            'sexe_adh' => 1,
            'prof_adh' => 'Extraction',
            'titre_adh' => null,
            'ddn_adh' => $bdate->format('Y-m-d'),
            'lieu_naissance' => 'Fischer',
            'pseudo_adh' => 'vallet.camille',
            'pays_adh' => null,
            'tel_adh' => '05 59 53 59 43',
            'url_adh' => 'http://bodin.net/omnis-ratione-sint-dolorem-architecto',
            'activite_adh' => true,
            'id_statut' => 9,
            'date_crea_adh' => '2019-05-20',
            'pref_lang' => 'ca',
            'fingerprint' => 'FAKER' . $this->seed,
            'societe_adh' => 'Philippe',
            'is_company' => true,
        ];
        return $data;
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
        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $check = $this->adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $this->adh->store();
        $this->boolean($store)->isTrue();

        return $this->adh;
    }

    /**
     * Check members expecteds
     *
     * @param Adherent $adh           Member instance, if any
     * @param array    $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkMemberOneExpected($adh = null, $new_expecteds = [])
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
            'ddn_adh' => 'NOT USED',
            'lieu_naissance' => 'Gonzalez-sur-Meunier',
            'pseudo_adh' => 'ubertrand',
            'cp_adh' => '39 069',
            'pays_adh' => 'Antarctique',
            'tel_adh' => '0439153432',
            'url_adh' => 'http://bouchet.com/',
            'activite_adh' => true,
            'id_statut' => 9,
            'pref_lang' => 'en_US',
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
                    $this->variable($adh->$property)->isIdenticalTo(
                        $value,
                        "$property expected {$value} got {$adh->$property}"
                    );

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
        $this->string($adh->getAddressContinuation())->isEmpty();
        $this->string($adh->getZipcode())->isIdenticalTo($expecteds['cp_adh']);
        $this->string($adh->getTown())->isIdenticalTo($expecteds['ville_adh']);
        $this->string($adh->getCountry())->isIdenticalTo($expecteds['pays_adh']);

        $this->string($adh::getSName($this->zdb, $adh->id))->isIdenticalTo('DURAND René');
        $this->string($adh->getRowClass())->isIdenticalTo('active cotis-never');
    }

    /**
     * Check members expecteds
     *
     * @param Adherent $adh           Member instance, if any
     * @param array    $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkMemberTwoExpected($adh = null, $new_expecteds = [])
    {
        if ($adh === null) {
            $adh = $this->adh;
        }

        $expecteds = [
            'nom_adh' => 'Hoarau',
            'prenom_adh' => 'Lucas',
            'ville_adh' => 'Reynaudnec',
            'cp_adh' => '63077',
            'adresse_adh' => '2, boulevard Legros',
            'email_adh' => 'phoarau@tele2.fr',
            'login_adh' => 'nathalie51',
            'mdp_adh' => 'T.u!IbKOi|06',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => false,
            'sexe_adh' => 1,
            'prof_adh' => 'Extraction',
            'titre_adh' => null,
            'ddn_adh' => 'NOT USED',
            'lieu_naissance' => 'Fischer',
            'pseudo_adh' => 'vallet.camille',
            'pays_adh' => '',
            'tel_adh' => '05 59 53 59 43',
            'url_adh' => 'http://bodin.net/omnis-ratione-sint-dolorem-architecto',
            'activite_adh' => true,
            'id_statut' => 9,
            'pref_lang' => 'ca',
            'fingerprint' => 'FAKER' . $this->seed,
            'societe_adh' => 'Philippe'
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
                    $this->string($adh->getAge())->isIdenticalTo(' (28 years old)');
                    break;
                default:
                    $this->variable($adh->$property)->isIdenticalTo(
                        $value,
                        "$property expected {$value} got {$adh->$property}"
                    );
                    break;
            }
        }

        $d = \DateTime::createFromFormat('Y-m-d', $expecteds['ddn_adh']);

        $expected_str = ' (28 years old)';
        $this->string($adh->getAge())->isIdenticalTo($expected_str);
        $this->boolean($adh->hasChildren())->isFalse();
        $this->boolean($adh->hasParent())->isFalse();
        $this->boolean($adh->hasPicture())->isFalse();

        $this->string($adh->sadmin)->isIdenticalTo('No');
        $this->string($adh->sdue_free)->isIdenticalTo('No');
        $this->string($adh->sappears_in_list)->isIdenticalTo('No');
        $this->string($adh->sstaff)->isIdenticalTo('No');
        $this->string($adh->sactive)->isIdenticalTo('Active');
        $this->variable($adh->stitle)->isNull();
        $this->string($adh->sstatus)->isIdenticalTo('Non-member');
        $this->string($adh->sfullname)->isIdenticalTo('HOARAU Lucas');
        $this->string($adh->saddress)->isIdenticalTo('2, boulevard Legros');
        $this->string($adh->sname)->isIdenticalTo('HOARAU Lucas');

        $this->string($adh->getAddress())->isIdenticalTo($expecteds['adresse_adh']);
        $this->string($adh->getAddressContinuation())->isEmpty();
        $this->string($adh->getZipcode())->isIdenticalTo($expecteds['cp_adh']);
        $this->string($adh->getTown())->isIdenticalTo($expecteds['ville_adh']);
        $this->string($adh->getCountry())->isIdenticalTo($expecteds['pays_adh']);

        $this->string($adh::getSName($this->zdb, $adh->id))->isIdenticalTo('HOARAU Lucas');
        $this->string($adh->getRowClass())->isIdenticalTo('active cotis-never');
    }

    /**
     * Look in database if test member already exists
     *
     * @return false|ResultSet
     */
    protected function adhOneExists()
    {
        $mdata = $this->dataAdherentOne();
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(
            array(
                'a.fingerprint' => 'FAKER' . $this->seed,
                'a.login_adh' => $mdata['login_adh']
            )
        );

        $results = $this->zdb->execute($select);
        if ($results->count() === 0) {
            return false;
        } else {
            return $results;
        }
    }

    /**
     * Look in database if test member already exists
     *
     * @return false|ResultSet
     */
    protected function adhTwoExists()
    {
        $mdata = $this->dataAdherentTwo();
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(
            array(
                'a.fingerprint' => 'FAKER' . $this->seed,
                'a.login_adh' => $mdata['login_adh']
            )
        );

        $results = $this->zdb->execute($select);
        if ($results->count() === 0) {
            return false;
        } else {
            return $results;
        }
    }

    /**
     * Get member one
     *
     * @return \Galette\Entity\Adherent
     */
    protected function getMemberOne()
    {
        $rs = $this->adhOneExists();
        if ($rs === false) {
            $this->createMember($this->dataAdherentOne());
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }
    }

    /**
     * Get member two
     *
     * @return \Galette\Entity\Adherent
     */
    protected function getMemberTwo()
    {
        $rs = $this->adhTwoExists();
        if ($rs === false) {
            $this->createMember($this->dataAdherentTwo());
        } else {
            $this->loadAdherent($rs->current()->id_adh);
        }
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
        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $contrib = $this->contrib;
        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $contrib->store();
        $this->boolean($store)->isTrue();

        return $contrib;
    }

    /**
     * Create test contribution in database
     *
     * @return void
     */
    protected function createContribution()
    {
        $bdate = new \DateTime(); // 2020-11-07
        $bdate->sub(new \DateInterval('P5M')); // 2020-06-07
        $bdate->add(new \DateInterval('P3D')); // 2020-06-10

        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => 92,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $bdate->format('Y-m-d'),
            'date_debut_cotis' => $bdate->format('Y-m-d'),
            'date_fin_cotis' => $edate->format('Y-m-d'),
        ];
        $this->createContrib($data);
        $this->checkContribExpected();
    }

    /**
     * Check contributions expected
     *
     * @param Contribution $contrib       Contribution instance, if any
     * @param array        $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkContribExpected($contrib = null, $new_expecteds = [])
    {
        if ($contrib === null) {
            $contrib = $this->contrib;
        }

        $date_begin = $contrib->raw_begin_date;
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->object($contrib->raw_date)->isInstanceOf('DateTime');
        $this->object($contrib->raw_begin_date)->isInstanceOf('DateTime');
        $this->object($contrib->raw_end_date)->isInstanceOf('DateTime');

        $expecteds = [
            'id_adh' => "{$this->adh->id}",
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => '92',
            'type_paiement_cotis' => '3',
            'info_cotis' => 'FAKER' . $this->seed,
            'date_fin_cotis' => $date_end->format('Y-m-d'),
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        $this->string($contrib->raw_end_date->format('Y-m-d'))->isIdenticalTo($expecteds['date_fin_cotis']);

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
                    $this->variable($contrib->$property)->isEqualTo($value, $property);
                    break;
            }
        }

        //load member from db
        $this->adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        //member is now up-to-date
        $this->string($this->adh->getRowClass())->isIdenticalTo('active cotis-ok');
        $this->string($this->adh->due_date)->isIdenticalTo($this->contrib->end_date);
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->boolean($contrib->isFee())->isTrue();
        $this->string($contrib->getTypeLabel())->isIdenticalTo('Membership');
        $this->string($contrib->getRawType())->isIdenticalTo('membership');
        $this->array($this->contrib->getRequired())->isIdenticalTo([
            'id_type_cotis'     => 1,
            'id_adh'            => 1,
            'date_enreg'        => 1,
            'date_debut_cotis'  => 1,
            'date_fin_cotis'    => 1,
            'montant_cotis'     => 1
        ]);
    }

    /**
     * Initialize default status in database
     *
     * @return void
     */
    protected function initStatus(): void
    {
        $status = new \Galette\Entity\Status($this->zdb);
        if (count($status->getList()) === 0) {
            //status are not yet instantiated.
            $res = $status->installInit();
            $this->boolean($res)->isTrue();
        }
    }

    /**
     * Initialize default contributions types in database
     *
     * @return void
     */
    protected function initContributionsTypes(): void
    {
        $ct = new \Galette\Entity\ContributionsTypes($this->zdb);
        if (count($ct->getCompleteList()) === 0) {
            //status are not yet instanciated.
            $res = $ct->installInit();
            $this->boolean($res)->isTrue();
        }
    }

    /**
     * Initialize default titles in database
     *
     * @return void
     */
    protected function initTitles(): void
    {
        $titles = new \Galette\Repository\Titles($this->zdb);
        if (count($titles->getList($this->zdb)) === 0) {
            $res = $titles->installInit($this->zdb);
            $this->boolean($res)->isTrue();
        }
    }

    /**
     * Clean history
     *
     * @return void
     */
    protected function cleanHistory(): void
    {
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Log-in as super administrator
     *
     * @return void
     */
    protected function logSuperAdmin(): void
    {
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isSuperAdmin())->isTrue();
    }
}
