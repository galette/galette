<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Reminders repository tests
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Repository
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-09-14
 */

namespace Galette\Repository\test\units;

use atoum;

/**
 * Reminders repository tests
 *
 * @category  Repository
 * @name      Remoinders
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-09-14
 */
class Reminders extends atoum
{
    private $zdb;
    private $seed = 95842355;
    private $preferences;
    private $session;
    private $login;
    private $history;
    //private $remove = [];
    private $i18n;
    private $contrib;
    private $members_fields;
    private $adh;
    private $ids = [];
    private $contribs_ids = [];

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp()
    {
        $this->zdb = new \Galette\Core\Db();
        $ct = new \Galette\Entity\ContributionsTypes($this->zdb);
        if (count($ct->getCompleteList()) === 0) {
            //status are not yet instanciated.
            $res = $ct->installInit();
            $this->boolean($res)->isTrue();
        }
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

        global $zdb, $login, $hist, $i18n; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;

        $status = new \Galette\Entity\Status($this->zdb);
        if (count($status->getList()) === 0) {
            $res = $status->installInit();
            $this->boolean($res)->isTrue();
        }

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
     * Tear down tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function afterTestMethod($testMethod)
    {
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Reminder::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        //impendings
        $ireminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::IMPENDING]);
        $this->array($ireminders->getList($this->zdb))->isIdenticalTo([]);

        //lates
        $lreminders = new \Galette\Repository\Reminders([\Galette\Entity\Reminder::LATE]);
        $this->array($lreminders->getList($this->zdb))->isIdenticalTo([]);

        //all
        $reminders = new \Galette\Repository\Reminders();
        $this->array($reminders->getList($this->zdb))->isIdenticalTo([]);

        //create member
        $this->createAdherent();
        $id = current($this->ids);

        //create contribution, just about to be impending
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->add(new \DateInterval('P1M'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is up to date, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->isIdenticalTo([]);

        //create contribution, just impending
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->add(new \DateInterval('P1M'));
        $date_begin->sub(new \DateInterval('P1D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is up to date, there is one impending reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(1);

        //create contribution, just impending less than 7 days
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->add(new \DateInterval('P4D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is up to date, there is one impending reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(1);

        //add a sent first impending reminder
        $send = clone $now;
        $send->sub(new \DateInterval('P1M'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::IMPENDING,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->integer($add->count())->isGreaterThan(0);

        //there is still one reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(1);

        //add a sent second impending reminder, yesterday
        $send = clone $now;
        $send->sub(new \DateInterval('P1D'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::IMPENDING,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->integer($add->count())->isGreaterThan(0);

        //nothing to send!
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, expiration day
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is up to date, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isTrue();
        $this->array($reminders->getList($this->zdb))->isIdenticalTo([]);

        //create contribution, just late
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P1D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date, but less than one month, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 1 month minus 1 day
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P1M'));
        $date_begin->add(new \DateInterval('P1D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date, but less than one month, no reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 31 days
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P31D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date for one month, one late reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(1);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 40 days
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P40D'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date for one month, one late reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(1);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //add a sent late reminder, at it should have been
        $send = clone $now;
        $send->sub(new \DateInterval('P5D'));
        $data = array(
            'reminder_type'     => \Galette\Entity\Reminder::LATE,
            'reminder_dest'     => $id,
            'reminder_date'     => $send->format('Y-m-d'),
            'reminder_success'  => true,
            'reminder_nomail'   => ($this->zdb->isPostgres() ? 'false' : 0)
        );

        $insert = $this->zdb->insert(\Galette\Entity\Reminder::TABLE);
        $insert->values($data);

        $add = $this->zdb->execute($insert);
        $this->integer($add->count())->isGreaterThan(0);

        //nothing to send!
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(0);
        $this->array($lreminders->getList($this->zdb))->hasSize(0);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);

        //create contribution, late by 2 months
        $now = new \DateTime();
        $date_begin = clone $now;
        $date_begin->sub(new \DateInterval('P1Y'));
        $date_begin->sub(new \DateInterval('P2M'));
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->createContrib([
            'id_adh'                => $id,
            'id_type_cotis'         => 3,
            'montant_cotis'         => '111',
            'type_paiement_cotis'   => '6',
            'info_cotis'            => 'FAKER' . $this->seed,
            'date_fin_cotis'        => $date_end->format('Y-m-d'),
            'date_enreg'            => $now->format('Y-m-d'),
            'date_debut_cotis'      => $now->format('Y-m-d')
        ]);
        $cid = current($this->contribs_ids);

        $adh = $this->adh;
        $this->boolean($adh->load($id))->isTrue();

        //member is not up to date for one month, one late reminder to send
        $this->boolean($this->adh->isUp2Date())->isFalse();
        $this->array($reminders->getList($this->zdb))->hasSize(1);
        $this->array($lreminders->getList($this->zdb))->hasSize(1);
        $this->array($ireminders->getList($this->zdb))->hasSize(0);
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
        $this->checkMemberExpected();
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
            'ddn_adh' => '1992-02-22',
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
        $this->boolean($store)->isTrue($store);

        $this->contribs_ids[] = (int)$contrib->id;
    }
}
