<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * Contribution tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Contribution extends GaletteTestCase
{
    protected int $seed = 95842354;

    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\ContributionsTypes::TABLE);
        $delete->where(['libelle_type_cotis' => 'FAKER' . $this->seed]);
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
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Test empty contribution
     *
     * @return void
     */
    public function testEmpty(): void
    {
        $contrib = $this->contrib;
        $this->assertNull($contrib->id);
        $this->assertNull($contrib->date);
        $this->assertNull($contrib->begin_date);
        $this->assertNull($contrib->end_date);
        $this->assertNull($contrib->raw_date);
        $this->assertNull($contrib->raw_begin_date);
        $this->assertNull($contrib->raw_end_date);
        $this->assertEmpty($contrib->duration);
        $this->assertSame((int)$this->preferences->pref_default_paymenttype, $contrib->payment_type);
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->model);
        $this->assertNull($contrib->member);
        $this->assertNull($contrib->type);
        $this->assertNull($contrib->amount);
        $this->assertNull($contrib->orig_amount);
        $this->assertNull($contrib->info);
        $this->assertNull($contrib->transaction);
        $this->assertCount(11, $contrib->fields);
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Contribution::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\Adherent::PK]));
        $this->assertTrue(isset($contrib->fields[\Galette\Entity\ContributionsTypes::PK]));
        $this->assertTrue(isset($contrib->fields['montant_cotis']));
        $this->assertTrue(isset($contrib->fields['type_paiement_cotis']));
        $this->assertTrue(isset($contrib->fields['info_cotis']));
        $this->assertTrue(isset($contrib->fields['date_debut_cotis']));

        $this->assertSame('cotis-give', $contrib->getRowClass());
        $this->assertNull($contrib::getDueDate($this->zdb, 1));
        $this->assertFalse($contrib->isTransactionPart());
        $this->assertFalse($contrib->isTransactionPartOf(1));
        $this->assertSame('Check', $contrib->getPaymentType());
        $this->assertNull($contrib->unknown_property);
    }

    /**
     * Test getter and setter special cases
     *
     * @return void
     */
    public function testGetterSetter(): void
    {
        $contrib = $this->contrib;

        //set a bad date
        $contrib->begin_date = 'not a date';
        $this->assertNull($contrib->raw_begin_date);
        $this->assertNull($contrib->begin_date);

        $contrib->begin_date = '2017-06-17';
        $this->assertInstanceOf('DateTime', $contrib->raw_begin_date);
        $this->assertSame('2017-06-17', $contrib->begin_date);

        $contrib->amount = 'not an amount';
        $this->assertNull($contrib->amount);
        $contrib->amount = 0;
        $this->assertNull($contrib->amount);
        $contrib->amount = 42;
        $this->assertSame(42.0, $contrib->amount);
        $contrib->amount = '42';
        $this->assertSame(42.0, $contrib->amount);

        $contrib->type = 156;
        $this->assertInstanceOf('\Galette\Entity\ContributionsTypes', $contrib->type);
        $this->assertFalse($contrib->type->id);
        $contrib->type = 1;
        $this->assertInstanceOf('\Galette\Entity\ContributionsTypes', $contrib->type);
        $this->assertEquals(1, $contrib->type->id);

        $contrib->transaction = 'not a transaction id';
        $this->assertNull($contrib->transaction);
        $contrib->transaction = 46;
        $this->assertInstanceOf('\Galette\Entity\Transaction', $contrib->transaction);
        $this->assertNull($contrib->transaction->id);

        $contrib->member = 'not a member';
        $this->assertNull($contrib->member);
        $contrib->member = 118218;
        $this->assertSame(118218, $contrib->member);

        $contrib->not_a_property = 'abcde';
        $this->assertFalse(property_exists($contrib, 'not_a_property'));

        $contrib->payment_type = \Galette\Entity\PaymentType::CASH;
        $this->assertSame('Cash', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::CHECK;
        $this->assertSame('Check', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::OTHER;
        $this->assertSame('Other', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::CREDITCARD;
        $this->assertSame('Credit card', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::TRANSFER;
        $this->assertSame('Transfer', $contrib->getPaymentType());

        $contrib->payment_type = \Galette\Entity\PaymentType::PAYPAL;
        $this->assertSame('Paypal', $contrib->getPaymentType());
    }

    /**
     * Test contribution creation
     *
     * @return void
     */
    public function testCreation(): void
    {
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();
    }

    /**
     * Test donation update
     *
     * @return void
     */
    public function testDonationUpdate(): void
    {
        $this->getMemberOne();
        //create contribution for member
        $begin_date = new \DateTime(); // 2020-11-07
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-07
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-10

        $due_date = clone $begin_date;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 12,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->createContrib($data);
        $this->assertSame(
            [
                'id_type_cotis'     => 1,
                'id_adh'            => 1,
                'date_enreg'        => 1,
                'date_debut_cotis'  => 1,
                'date_fin_cotis'    => 0,
                'montant_cotis'     => 0
            ],
            $this->contrib->getRequired()
        );

        $this->logSuperAdmin();
        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 1280,
            'type_paiement_cotis' => 4,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->createContrib($data);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(1280.00, $contrib->amount);

        //empty amount
        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 0,
            'type_paiement_cotis' => 4,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->createContrib($data);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(0.00, $contrib->amount);
    }

    /**
     * Test contribution update
     *
     * @return void
     */
    public function testContributionUpdate(): void
    {
        $this->logSuperAdmin();

        $this->getMemberOne();
        //create contribution for member
        $begin_date = new \DateTime(); // 2020-11-07
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-07
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-10

        $due_date = clone $begin_date;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));

        //instanciate contribution as annual fee
        $this->contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            [
                'type' => 1 //annual fee
            ]
        );
        $this->assertSame(
            [
                'id_type_cotis'     => 1,
                'id_adh'            => 1,
                'date_enreg'        => 1,
                'date_debut_cotis'  => 1,
                'date_fin_cotis'    => 1, //should be 1
                'montant_cotis'     => 1 // should be 1
            ],
            $this->contrib->getRequired()
        );

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //annual fee
            'montant_cotis' => 0,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];

        $this->createContrib($data, $this->contrib);

        $this->assertSame(0.0, $this->contrib->amount);
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(0.0, $contrib->amount);

        //change amount
        $data['montant_cotis'] = 42;
        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(42.0, $contrib->amount);

        //change amount back to 0
        $data['montant_cotis'] = 0;
        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(0.0, $contrib->amount);
    }

    /**
     * Test end date retrieving
     * This is based on some Preferences parameters
     *
     * @return void
     */
    public function testRetrieveEndDate(): void
    {
        global $preferences;
        $orig_pref_beg_membership = $this->preferences->pref_beg_membership;
        $orig_pref_membership_ext = $this->preferences->pref_membership_ext;
        $orig_pref_membership_offermonths = $this->preferences->pref_membership_offermonths;

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );

        // First, check for 12 months renewal
        $due_date = new \DateTime();
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);

        // Second, test with beginning of membership date
        $preferences->pref_beg_membership = '29/05';
        $due_date = new \DateTime();
        $due_date->setDate(date('Y'), 5, 28);
        if ($due_date <= new \DateTime()) {
            $due_date->add(new \DateInterval('P1Y'));
        }

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] // annual fee
        );
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);

        // Third, test with beginning of membership date and 2 last months offered
        $begin_date = new \DateTime();
        $begin_date->add(new \DateInterval('P1M'));
        $preferences->pref_beg_membership = $begin_date->format('01/m');
        $preferences->pref_membership_offermonths = 2;
        $due_date = new \DateTime($begin_date->format('Y-m-01'));
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] // annual fee
        );
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);

        //reset
        $preferences->pref_beg_membership = $orig_pref_beg_membership;
        $preferences->pref_membership_ext = $orig_pref_membership_ext;
        $preferences->pref_membership_offermonths = $orig_pref_membership_offermonths;

        //unset pref_beg_membership and pref_membership_ext
        $preferences->pref_beg_membership = '';
        $preferences->pref_membership_ext = '';

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Unable to define end date; none of pref_beg_membership nor pref_membership_ext are defined!');
        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => 1] //annual fee
        );
    }

    /**
     * Test monthly contribution
     *
     * @return void
     */
    public function testMonthlyContribution(): void
    {
        //create monthly fee type - 2 months extension
        $contribtype = new \Galette\Entity\ContributionsTypes($this->zdb);
        $this->assertTrue($contribtype->add('FAKER' . $this->seed, 10.00, 2));

        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            ['type' => $contribtype->id] //monthly fee
        );

        $due_date = new \DateTime();
        $due_date->add(new \DateInterval('P2M'));
        $due_date->sub(new \DateInterval('P1D'));
        $this->assertSame($due_date->format('Y-m-d'), $contrib->end_date);
    }

    /**
     * Test checkOverlap method
     *
     * @return void
     */
    public function testCheckOverlap(): void
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
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);

        //create first contribution for member
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $now = new \DateTime();
        $due_date = clone $now;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));
        $data = [
            \Galette\Entity\Adherent::PK            => $adh->id,
            \Galette\Entity\ContributionsTypes::PK  => 1, //annual fee
            'montant_cotis'                         => 20,
            'type_paiement_cotis'                   => \Galette\Entity\PaymentType::CHECK,
            'date_enreg'                            => $now->format(_T("Y-m-d")),
            'date_debut_cotis'                      => $now->format(_T("Y-m-d")),
            'date_fin_cotis'                        => $due_date->format(_T("Y-m-d")),
            'info_cotis'                            => 'FAKER' . $this->seed
        ];

        $check = $contrib->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);
        $this->assertTrue($contrib->checkOverlap());

        $store = $contrib->store();
        $this->assertTrue($store);

        //load member from db
        $adh = new \Galette\Entity\Adherent($this->zdb, $adh->id);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $begin_date = clone $due_date;
        $begin_date->add(new \DateInterval('P1D'));
        $begin_date->sub(new \DateInterval('P3M'));
        $due_date = clone $begin_date;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));
        $data = [
            \Galette\Entity\Adherent::PK            => $adh->id,
            \Galette\Entity\ContributionsTypes::PK  => 1, //annual fee
            'montant_cotis'                         => 20,
            'type_paiement_cotis'                   => \Galette\Entity\PaymentType::CHECK,
            'date_enreg'                            => $now->format(_T("Y-m-d")),
            'date_debut_cotis'                      => $begin_date->format(_T("Y-m-d")),
            'date_fin_cotis'                        => $due_date->format(_T("Y-m-d")),
            'info_cotis'                            => 'FAKER' . $this->seed
        ];

        $check = $contrib->check($data, [], []);
        $this->assertSame(
            [
                '- Membership period overlaps period starting at ' . $now->format('Y-m-d')
            ],
            $check
        );

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Existing errors prevents storing contribution');
        $store = $contrib->store();
    }

    /**
     * Test fields labels
     *
     * @return void
     */
    public function testGetFieldLabel(): void
    {
        $this->assertSame(
            'Amount',
            $this->contrib->getFieldLabel('montant_cotis')
        );

        $this->assertSame(
            'Date of contribution',
            $this->contrib->getFieldLabel('date_debut_cotis')
        );

        $this->contrib->type = 1;
        $this->assertSame(
            'Start date of membership',
            $this->contrib->getFieldLabel('date_debut_cotis')
        );

        $this->assertSame(
            'Comments',
            $this->contrib->getFieldLabel('info_cotis')
        );
    }

    /**
     * Test contribution loading
     *
     * @return void
     */
    public function testLoad(): void
    {
        $this->login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin'))
            ->getMock();
        $this->login->method('isLogged')->willReturn(true);
        $this->login->method('isStaff')->willReturn(true);
        $this->login->method('isAdmin')->willReturn(true);

        $this->getMemberOne();

        //create contribution for member
        $this->createContribution();

        $id = $this->contrib->id;
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->assertTrue($contrib->load((int)$id));
        $this->checkContribExpected($contrib);

        $this->assertFalse($contrib->load(1355522012));
    }

    /**
     * Test contribution removal
     *
     * @return void
     */
    public function testRemove(): void
    {
        $this->getMemberOne();
        $this->createContribution();

        $this->assertTrue($this->contrib->remove());
        $this->assertFalse($this->contrib->remove());
    }

    /**
     * Test can* methods
     *
     * @return void
     */
    public function testCan(): void
    {
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();
        $contrib = $this->contrib;

        $this->assertFalse($contrib->canShow($this->login));

        //Superadmin can fully change contributions
        $this->logSuperAdmin();

        $this->assertTrue($contrib->canShow($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Member can fully change its own contributions
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertTrue($contrib->canShow($this->login));

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

        $this->assertFalse($contrib->canShow($this->login));

        //parents can chow change children contributions
        $this->getMemberOne();
        $member = $this->adh;
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

        //contribution for child
        $begin_date = new \DateTime(); // 2020-11-07
        $begin_date->sub(new \DateInterval('P5M')); // 2020-06-07
        $begin_date->add(new \DateInterval('P3D')); // 2020-06-10

        $due_date = clone $begin_date;
        $due_date->add(new \DateInterval('P1Y'));
        $due_date->sub(new \DateInterval('P1D'));

        $data = [
            'id_adh' => $cid,
            'id_type_cotis' => 1,
            'montant_cotis' => 25,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $ccontrib = $this->createContrib($data);

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

        $this->assertTrue($ccontrib->canShow($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());
    }

    /**
     * Test next year contribution
     *
     * @return void
     */
    public function testNextYear(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();

        //create contribution for member
        $begin_date = new \DateTime(); // 2023-12-30
        $ny_begin_date = clone $begin_date; // 2023-12-30
        $end_date = clone $begin_date;
        $begin_date->sub(new \DateInterval('P1Y')); // 2022-12-30
        $end_date->sub(new \DateInterval('P1D')); // 2023-12-29

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1, //contribution
            'montant_cotis' => 100,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'duree_mois_cotis' => 12
        ];
        $this->createContrib($data);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $this->contrib->id);
        $this->assertSame(100.00, $contrib->amount);
        $this->assertSame($end_date->format('Y-m-d'), $contrib->end_date);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, ['type' => 1, 'adh' => $this->adh->id]);
        $this->assertSame($ny_begin_date->format('Y-m-d'), $contrib->begin_date);
    }

    /**
     * Test next year contribution from a 0.9.x
     *
     * @return void
     */
    public function testNextYearFrom096(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();

        //create contribution for member
        $begin_date = new \DateTime(); // 2023-12-30
        $ny_begin_date = clone $begin_date; // 2023-12-30
        $end_date = clone $begin_date;
        $due_date = clone $begin_date;

        $begin_date->sub(new \DateInterval('P1Y')); // 2022-12-30

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $insert = $this->zdb->insert(\Galette\Entity\Contribution::TABLE);
        $insert->values(
            [
                'id_adh' => $this->adh->id,
                'id_type_cotis' => 1, //contribution
                'montant_cotis' => 100,
                'type_paiement_cotis' => 3,
                'info_cotis' => 'FAKER' . $this->seed,
                'date_enreg' => $begin_date->format('Y-m-d'),
                'date_debut_cotis' => $begin_date->format('Y-m-d'),
                'date_fin_cotis' => $due_date->format('Y-m-d')
            ]
        );
        $add = $this->zdb->execute($insert);
        $this->assertSame(1, $add->count());
        $contribution_id = (int)($add->getGeneratedValue() ?? $this->zdb->getLastGeneratedValue($contrib));

        $update = $this->zdb->update(\Galette\Entity\Adherent::TABLE);
        $update->set(
            array('date_echeance' => $due_date->format('Y-m-d'))
        )->where(
            [\Galette\Entity\Adherent::PK => $this->adh->id]
        );
        $this->zdb->execute($update);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $contribution_id);
        $this->assertSame(100.00, $contrib->amount);
        $this->assertSame($end_date->format('Y-m-d'), $contrib->end_date);

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, ['type' => 1, 'adh' => $this->adh->id, 'payment_type' => 1]);
        $this->assertSame($ny_begin_date->format('Y-m-d'), $contrib->begin_date);

        $check = $contrib->check(['type_paiement_cotis' => 1, 'montant_cotis' => 1, 'info_cotis' => 'FAKER' . $this->seed], [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $contrib->store();
        $this->assertTrue($store);
    }
}
