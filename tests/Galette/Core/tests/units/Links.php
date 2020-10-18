<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password tests
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-03-15
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * Password tests class
 *
 * @category  Core
 * @name      Password
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-03-15
 */
class Links extends atoum
{
    //private $pass = null;
    private $zdb;
    private $i18n;
    private $preferences;
    private $session;
    private $login;
    private $history;
    private $seed = 95842355;
    private $adh;
    private $links;
    private $ids = [];
    private $contribs_ids = [];
    private $members_fields;
    private $contrib;

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
     * @param string $testMethod Method name
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->links = new \Galette\Core\Links($this->zdb, false);

        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );

        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);
        $this->history = new \Galette\Core\History($this->zdb, $this->login, $this->preferences);

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
     * Cleanup after testeach test method
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Core\Links::TABLE);
        $this->zdb->execute($delete);
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
     * Test new PasswordImage generation
     *
     * @return void
     */
    public function testGenerateNewLink()
    {
        $links = $this->links;
        $this->createAdherent();
        $id = current($this->ids);

        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        );

        $this->string($res)->isNotEmpty();

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(1);

        $this->array($links->isHashValid($res, 'phoarau@tele2.fr'))->isIdenticalTo([
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        ]);

        $this->boolean($links->isHashValid($res, 'any@mail.com'))->isFalse();
        $this->boolean($links->isHashValid(base64_encode('sthingthatisnotahash'), 'phoarau@tele2.fr'))->isFalse();

        $this->createContribution();
        $cid = current($this->contribs_ids);
        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_INVOICE,
            $cid
        );

        $this->string($res)->isNotEmpty();
        $this->array($links->isHashValid($res, 'phoarau@tele2.fr'))->isIdenticalTo([
            \Galette\Core\Links::TARGET_INVOICE,
            $cid
        ]);
    }

    /**
     * Test expired is invalid
     *
     * @return void
     */
    public function testExpiredValidate()
    {
        $links = $this->links;
        $this->createAdherent();
        $id = current($this->ids);

        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        );

        $this->string($res)->isNotEmpty();

        $this->array($links->isHashValid($res, 'phoarau@tele2.fr'))->isIdenticalTo([
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        ]);

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(1);

        $update = $this->zdb->update(\Galette\Core\Links::TABLE);
        $old_date = new \DateTime();
        $old_date->sub(new \DateInterval('P2W'));
        $update
            ->set(['creation_date' => $old_date->format('Y-m-d')])
            ->where(['hash' => base64_decode($res)]);
        $this->zdb->execute($update);

        $this->boolean($links->isHashValid($res, 'phoarau@tele2.fr'))->isFalse();
    }

    /**
     * Test cleanExpired
     *
     * @return void
     */
    public function testCleanExpired()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT48H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Not expired link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );
        $this->zdb->execute($insert);

        $date->sub(new \DateInterval('P1W'));
        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Expired link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 2
            ]
        );
        $this->zdb->execute($insert);

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(2);

        $links = new \Galette\Core\Links($this->zdb, true);

        $results = $this->zdb->execute($select);
        $result = $results->current();
        $this->integer($results->count())->isIdenticalTo(1);
        $this->string($result['hash'])->isIdenticalTo('Not expired link');
    }

    /**
     * Test duplicate target
     *
     * @return void
     */
    public function testDuplicateLinkTarget()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT48H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Unique link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );
        $this->zdb->execute($insert);

        $date->sub(new \DateInterval('PT1H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Unique link (but we did not know before)',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );

        $this->exception(
            function () use ($insert) {
                $this->zdb->execute($insert);
            }
        )
            ->hasMessage('Duplicate entry');
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
        $this->checkContribExpected();
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

        $this->contribs_ids[] = (int)$contrib->id;
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

        $date_begin = $contrib->raw_begin_date;
        $date_end = clone $date_begin;
        $date_end->add(new \DateInterval('P1Y'));

        $this->object($contrib->raw_date)->isInstanceOf('DateTime');
        $this->object($contrib->raw_begin_date)->isInstanceOf('DateTime');
        $this->object($contrib->raw_end_date)->isInstanceOf('DateTime');

        $expecteds = [
            'id_adh' => "{$this->adh->id}",
            'id_type_cotis' => 3,
            'montant_cotis' => '111',
            'type_paiement_cotis' => '6',
            'info_cotis' => 'FAKER' . $this->seed,
            'date_fin_cotis' => $date_end->format('Y-m-d')
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
        $this->string($this->adh->getRowClass())->isIdenticalTo('active cotis-late');
        $this->string($this->adh->due_date)->isIdenticalTo($this->contrib->end_date);
        $this->boolean($this->adh->isUp2Date())->isFalse();
    }
}
