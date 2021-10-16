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

use Galette\GaletteTestCase;

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
class Contribution extends GaletteTestCase
{
    protected $seed = 95842354;

    /**
     * Cleanup after testeach test method
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
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
        parent::beforeTestMethod($testMethod);
        $this->initContributionsTypes();

        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Create test contribution in database
     *
     * @return void
     */
    private function createContribution()
    {
        $bdate = new \DateTime(); // 2020-11-07
        $bdate->sub(new \DateInterval('P5M')); // 2020-06-07
        $bdate->add(new \DateInterval('P3D')); // 2020-06-10

        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1,
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
        $this->variable($contrib->isFee())->isNull();
        $this->variable($contrib->is_cotis)->isNull();
        $this->variable($contrib->date)->isNull();
        $this->variable($contrib->begin_date)->isNull();
        $this->variable($contrib->end_date)->isNull();
        $this->variable($contrib->raw_date)->isNull();
        $this->variable($contrib->raw_begin_date)->isNull();
        $this->variable($contrib->raw_end_date)->isNull();
        $this->string($contrib->duration)->isEmpty();
        $this->variable($contrib->payment_type)->isIdenticalTo((int)$this->preferences->pref_default_paymenttype);
        $this->string($contrib->spayment_type)->isIdenticalTo('Check');
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
        $this->string($contrib->getPaymentType())->isIdenticalTo('Check');
        $this->variable($contrib->unknown_property)->isNull();
    }

    /**
     * Test getter and setter special cases
     *
     * @return void
     */
    public function testGetterSetter()
    {
        $contrib = $this->contrib;

        //set a bad date
        $contrib->begin_date = 'not a date';
        $this->variable($contrib->raw_begin_date)->isNull();
        $this->variable($contrib->begin_date)->isNull();

        $contrib->begin_date = '2017-06-17';
        $this->object($contrib->raw_begin_date)->isInstanceOf('DateTime');
        $this->string($contrib->begin_date)->isIdenticalTo('2017-06-17');

        $contrib->amount = 'not an amount';
        $this->variable($contrib->amount)->isNull();
        $contrib->amount = 0;
        $this->variable($contrib->amount)->isNull();
        $contrib->amount = 42;
        $this->integer($contrib->amount)->isIdenticalTo(42);
        $contrib->amount = '42';
        $this->string($contrib->amount)->isIdenticalTo('42');

        $contrib->type = 'not a type';
        $this->variable($contrib->type)->isNull();
        $contrib->type = 156;
        $this->object($contrib->type)->isInstanceOf('\Galette\Entity\ContributionsTypes');
        $this->boolean($contrib->type->id)->isFalse();
        $contrib->type = 1;
        $this->object($contrib->type)->isInstanceOf('\Galette\Entity\ContributionsTypes');
        $this->variable($contrib->type->id)->isEqualTo(1);

        $contrib->transaction = 'not a transaction id';
        $this->variable($contrib->transaction)->isNull();
        $contrib->transaction = 46;
        $this->object($contrib->transaction)->isInstanceOf('\Galette\Entity\Transaction');
        $this->boolean($contrib->transaction->id)->isFalse();

        $contrib->member = 'not a member';
        $this->variable($contrib->member)->isNull();
        $contrib->member = 118218;
        $this->integer($contrib->member)->isIdenticalTo(118218);

        $contrib->not_a_property = 'abcde';
        $this->boolean(property_exists($contrib, 'not_a_property'))->isFalse();

        $contrib->payment_type = \Galette\Entity\PaymentType::CASH;
        $this->string($contrib->getPaymentType())->isIdenticalTo('Cash');
        $this->string($contrib->spayment_type)->isIdenticalTo('Cash');

        $contrib->payment_type = \Galette\Entity\PaymentType::CHECK;
        $this->string($contrib->getPaymentType())->isIdenticalTo('Check');
        $this->string($contrib->spayment_type)->isIdenticalTo('Check');

        $contrib->payment_type = \Galette\Entity\PaymentType::OTHER;
        $this->string($contrib->getPaymentType())->isIdenticalTo('Other');
        $this->string($contrib->spayment_type)->isIdenticalTo('Other');

        $contrib->payment_type = \Galette\Entity\PaymentType::CREDITCARD;
        $this->string($contrib->getPaymentType())->isIdenticalTo('Credit card');
        $this->string($contrib->spayment_type)->isIdenticalTo('Credit card');

        $contrib->payment_type = \Galette\Entity\PaymentType::TRANSFER;
        $this->string($contrib->getPaymentType())->isIdenticalTo('Transfer');
        $this->string($contrib->spayment_type)->isIdenticalTo('Transfer');

        $contrib->payment_type = \Galette\Entity\PaymentType::PAYPAL;
        $this->string($contrib->getPaymentType())->isIdenticalTo('Paypal');
        $this->string($contrib->spayment_type)->isIdenticalTo('Paypal');
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
            'id_type_cotis' => 1,
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
    }

    /**
     * Test contribution creation
     *
     * @return void
     */
    public function testCreation()
    {
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();
    }

    /**
     * Test end date retrieving
     * This is based on some Preferences parameters
     *
     * @return void
     */
    public function testRetrieveEndDate()
    {
        global $preferences;
        $orig_pref_beg_membership = $this->preferences->pref_beg_membership;
        $orig_pref_membership_ext = $this->preferences->pref_membership_ext;
        $orig_pref_membership_offermonths = $this->preferences->pref_membership_offermonths;

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //anual fee
        );

        // First, check for 12 months renewal
        $expected = new \DateTime();
        $expected->add(new \DateInterval('P1Y'));
        $this->string($contrib->end_date)->isIdenticalTo($expected->format('Y-m-d'));

        //unset pref_beg_membership and pref_membership_ext
        $preferences->pref_beg_membership = '';
        $preferences->pref_membership_ext = '';

        $this->exception(
            function () {
                $contrib = new \Galette\Entity\Contribution(
                    $this->zdb,
                    $this->login,
                    ['type' => 1] //anual fee
                );
            }
        )
            ->isInstanceOf('RuntimeException')
            ->hasMessage('Unable to define end date; none of pref_beg_membership nor pref_membership_ext are defined!');

        // Second, test with beginning of membership date
        $preferences->pref_beg_membership = '29/05';
        $expected = new \DateTime();
        $expected->setDate(date('Y'), 5, 29);
        if ($expected < new \DateTime()) {
            $expected->add(new \DateInterval('P1Y'));
        }

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] // anual fee
        );
        $this->string($contrib->end_date)->isIdenticalTo($expected->format('Y-m-d'));

        // Third, test with beginning of membership date and i2 last months offered
        $beginning = new \DateTime();
        $beginning->add(new \DateInterval('P1M'));
        $preferences->pref_beg_membership = $beginning->format('t/m'); // end of next month
        $preferences->pref_membership_offermonths = 2;
        $expected = clone $beginning;
        $expected->add(new \DateInterval('P1Y'));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] // anual fee
        );
        $this->string($contrib->end_date)->isIdenticalTo($expected->format('Y-m-t'));

        //reset
        $preferences->pref_beg_membership = $orig_pref_beg_membership;
        $preferences->pref_membership_ext = $orig_pref_membership_ext;
        $preferences->pref_membership_offermonths = $orig_pref_membership_offermonths;
    }

    /**
     * Test checkOverlap method
     *
     * @return void
     */
    public function testCheckOverlap()
    {
        $adh = new \Galette\Entity\Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $check = $adh->check(
            [
                'nom_adh'                   => 'Overlapped',
                'date_crea_adh'             => date(_T("Y-m-d")),
                \Galette\Entity\Status::PK  => \Galette\Entity\Status::DEFAULT_STATUS,
                'fingerprint'               => 'FAKER' . $this->seed
            ],
            [],
            []
        );
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $adh->store();
        $this->boolean($store)->isTrue();

        //create first contribution for member
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $now = new \DateTime();
        $end_date = clone $now;
        $end_date->add(new \DateInterval('P1Y'));
        $data = [
            \Galette\Entity\Adherent::PK            => $adh->id,
            \Galette\Entity\ContributionsTypes::PK  => 1, //anual fee
            'montant_cotis'                         => 20,
            'type_paiement_cotis'                   => \Galette\Entity\PaymentType::CHECK,
            'date_enreg'                            => $now->format(_T("Y-m-d")),
            'date_debut_cotis'                      => $now->format(_T("Y-m-d")),
            'date_fin_cotis'                        => $end_date->format(_T("Y-m-d")),
            'info_cotis'                            => 'FAKER' . $this->seed
        ];

        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();
        $this->boolean($contrib->checkOverlap())->isTrue();

        $store = $contrib->store();
        $this->boolean($store)->isTrue();

        //load member from db
        $adh = new \Galette\Entity\Adherent($this->zdb, $adh->id);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $begin = clone $end_date;
        $begin->sub(new \DateInterval('P3M'));
        $end_date = clone $begin;
        $end_date->add(new \DateInterval('P1Y'));
        $data = [
            \Galette\Entity\Adherent::PK            => $adh->id,
            \Galette\Entity\ContributionsTypes::PK  => 1, //anual fee
            'montant_cotis'                         => 20,
            'type_paiement_cotis'                   => \Galette\Entity\PaymentType::CHECK,
            'date_enreg'                            => $now->format(_T("Y-m-d")),
            'date_debut_cotis'                      => $begin->format(_T("Y-m-d")),
            'date_fin_cotis'                        => $end_date->format(_T("Y-m-d")),
            'info_cotis'                            => 'FAKER' . $this->seed
        ];

        $check = $contrib->check($data, [], []);
        $this->array($check)->isIdenticalTo([
            '- Membership period overlaps period starting at ' . $now->format('Y-m-d')
        ]);

        $this->exception(
            function () use ($contrib) {
                $store = $contrib->store();
            }
        )
            ->isInstanceOf('RuntimeException')
            ->message->startWith('Existing errors prevents storing contribution');
    }

    /**
     * Test checkOverlap method that throws an exception
     *
     * @return void
     */
    public function testCheckOverlapWException()
    {
        $zdb = new \mock\Galette\Core\Db();
        $this->calling($zdb)->execute = function ($o) {
            if ($o instanceof \Zend\Db\Sql\Select) {
                throw new \LogicException('Error executing query!', 123);
            }
        };

        $contrib = new \Galette\Entity\Contribution($zdb, $this->login);
        $this->boolean($contrib->checkOverlap())->isFalse();
    }


    /**
     * Test fields labels
     *
     * @return void
     */
    public function testGetFieldLabel()
    {
        $this->string($this->contrib->getFieldLabel('montant_cotis'))
            ->isIdenticalTo('Amount');

        $this->string($this->contrib->getFieldLabel('date_debut_cotis'))
            ->isIdenticalTo('Date of contribution');

        $this->contrib->type = 1;
        $this->string($this->contrib->getFieldLabel('date_debut_cotis'))
            ->isIdenticalTo('Start date of membership');
    }

    /**
     * Test contribution loading
     *
     * @return void
     */
    public function testLoad()
    {
        $this->login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->calling($this->login)->isLogged = true;
        $this->calling($this->login)->isStaff = true;
        $this->calling($this->login)->isAdmin = true;

        $this->getMemberOne();

        //create contribution for member
        $this->createContribution();

        $id = $this->contrib->id;
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->boolean($contrib->load((int)$id))->isTrue();
        $this->checkContribExpected($contrib);

        $this->boolean($contrib->load(1355522012))->isFalse();
    }

    /**
     * Test contribution removal
     *
     * @return void
     */
    public function testRemove()
    {
        $this->getMemberOne();
        $this->createContribution();

        $this->boolean($this->contrib->remove())->isTrue();

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $this->boolean($this->contrib->remove())->isFalse();
    }

    /**
     * Test can* methods
     *
     * @return void
     */
    public function testCan()
    {
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();
        $contrib = $this->contrib;

        $this->boolean($contrib->canShow($this->login))->isFalse();

        //Superadmin can fully change contributions
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isSuperAdmin())->isTrue();

        $this->boolean($contrib->canShow($this->login))->isTrue();

        //logout
        $this->login->logOut();
        $this->boolean($this->login->isLogged())->isFalse();

        //Member can fully change its own contributions
        $mdata = $this->dataAdherentOne();
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isStaff())->isFalse();

        $this->boolean($contrib->canShow($this->login))->isTrue();

        //logout
        $this->login->logOut();
        $this->boolean($this->login->isLogged())->isFalse();

        //Another member has no access
        $this->getMemberTwo();
        $mdata = $this->dataAdherentTwo();
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isStaff())->isFalse();

        $this->boolean($contrib->canShow($this->login))->isFalse();

        //parents can chow change children contributions
        $this->getMemberOne();
        $member = $this->adh;
        $mdata = $this->dataAdherentOne();
        global $login;
        $login = $this->login;
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isSuperAdmin())->isTrue();

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

        //contribution for child
        $bdate = new \DateTime(); // 2020-11-07
        $bdate->sub(new \DateInterval('P5M')); // 2020-06-07
        $bdate->add(new \DateInterval('P3D')); // 2020-06-10

        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));

        $data = [
            'id_adh' => $cid,
            'id_type_cotis' => 1,
            'montant_cotis' => 25,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $bdate->format('Y-m-d'),
            'date_debut_cotis' => $bdate->format('Y-m-d'),
            'date_fin_cotis' => $edate->format('Y-m-d'),
        ];
        $ccontrib = $this->createContrib($data);

        $this->login->logOut();

        //load child from db
        $child = new \Galette\Entity\Adherent($this->zdb);
        $child->enableDep('parent');
        $this->boolean($child->load($cid))->isTrue();

        $this->string($child->name)->isIdenticalTo($child_data['nom_adh']);
        $this->object($child->parent)->isInstanceOf('\Galette\Entity\Adherent');
        $this->integer($child->parent->id)->isIdenticalTo($member->id);
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();

        $mdata = $this->dataAdherentOne();
        $this->boolean($this->login->login($mdata['login_adh'], $mdata['mdp_adh']))->isTrue();
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isStaff())->isFalse();

        $this->boolean($ccontrib->canShow($this->login))->isTrue();

        //logout
        $this->login->logOut();
        $this->boolean($this->login->isLogged())->isFalse();
    }
}
