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
 * Transaction tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Transaction extends GaletteTestCase
{
    protected int $seed = 95842354;
    private \Galette\Entity\Transaction $transaction;

    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->zdb = new \Galette\Core\Db();

        //first, remove contributions
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        //then, remove transactions
        $delete = $this->zdb->delete(\Galette\Entity\Transaction::TABLE);
        $delete->where(['trans_desc' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        //Remove groups
        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSUSERS_TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSMANAGERS_TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);

        //remove members with parents
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
        $this->zdb->execute($delete);

        //remove all others members
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $this->preferences->pref_bool_groupsmanagers_see_transactions = false;
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->preferences->pref_bool_groupsmanagers_create_transactions = false;
        $this->preferences->pref_bool_groupsmanagers_create_contributions = false;
        $this->assertTrue($this->preferences->store());
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
        $this->transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Create test transaction in database
     *
     * @return \Galette\Entity\Transaction
     */
    private function createTransaction(): \Galette\Entity\Transaction
    {
        $date = new \DateTime(); // 2020-11-07
        $data = [
            'id_adh' => $this->adh->id,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'trans_desc' => 'FAKER' . $this->seed,
            'type_paiement_trans' => 6,
        ];

        $this->transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $check = $this->transaction->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $this->transaction->store($this->history);
        $this->assertTrue($store);

        return $this->transaction;
    }

    /**
     * Test empty transaction
     *
     * @return void
     */
    public function testEmpty(): void
    {
        $this->assertNull($this->transaction->id);
        $this->assertEquals(date('Y-m-d'), $this->transaction->date);
        $this->assertNull($this->transaction->amount);
        $this->assertNull($this->transaction->description);

        $this->assertSame((double)0, $this->transaction->getDispatchedAmount());
        $this->assertSame((double)0, $this->transaction->getMissingAmount());
        $this->assertSame('transaction-normal', $this->transaction->getRowClass());
        $this->assertCount(6, $this->transaction->fields);
        $this->assertArrayHasKey(\Galette\Entity\Transaction::PK, $this->transaction->fields);
        $this->assertArrayHasKey(\Galette\Entity\Adherent::PK, $this->transaction->fields);
        $this->assertArrayHasKey('trans_date', $this->transaction->fields);
        $this->assertArrayHasKey('trans_amount', $this->transaction->fields);
        $this->assertArrayHasKey('trans_desc', $this->transaction->fields);
        $this->assertArrayHasKey('type_paiement_trans', $this->transaction->fields);

        $this->assertEquals(false, $this->transaction->unknown_property);
        $this->expectLogEntry(
            \Analog::WARNING,
            'Property unknown_property does not exist for transaction'
        );
    }

    /**
     * Test getter and setter special cases
     *
     * @return void
     */
    public function testGetterSetter(): void
    {
        $transaction = $this->transaction;

        //set a bad date
        $data = ['trans_date' => 'mypassword'];
        $expected = ['- Wrong date format (Y-m-d) for Date!'];
        $check = $transaction->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        //set a correct date
        $data = ['trans_date' => '1999-01-01'];
        $check = $transaction->check($data, [], []);
        $this->assertTrue($check);
        $this->assertSame('1999-01-01', $transaction->date);

        //set a bad amount
        $data = ['trans_amount' => 'mypassword'];
        $expected = ['- The amount must be an integer!'];
        $check = $transaction->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);

        //set a correct amount
        $data = ['trans_amount' => 1256];
        $check = $transaction->check($data, ['trans_amount' => 1], []);
        $this->assertTrue($check);
        $this->assertSame(1256.00, $transaction->amount);

        //set a bad description
        $data = ['trans_desc' => 'this is a very long description that should give an error; because the length of transaction description is limited to 150 characters long, even if this is quite hard to find something to write.'];
        $expected = ['- Transaction description must be 150 characters long maximum.'];
        $check = $transaction->check($data, [], []);
        $this->assertSame($expected, $check);
        $this->expectLogEntry(\Analog::ERROR, $expected[0]);
    }

    /**
     * Test transaction creation
     *
     * @return void
     */
    public function testCreation(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();
        $this->login->logOut();
    }

    /**
     * Test transaction update
     *
     * @return void
     */
    public function testUpdate(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();

        $data = [
            'trans_amount' => 42
        ];
        $check = $this->transaction->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $this->transaction->store($this->history);
        $this->assertTrue($store);

        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login, $this->transaction->id);
        $this->assertSame(42.00, $transaction->amount);
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
            $this->transaction->getFieldLabel('trans_amount')
        );

        $this->assertSame(
            'Date',
            $this->transaction->getFieldLabel('trans_date')
        );

        $this->assertSame(
            'Description',
            $this->transaction->getFieldLabel('trans_desc')
        );

        $this->assertSame(
            'Originator',
            $this->transaction->getFieldLabel(\Galette\Entity\Adherent::PK)
        );
    }

    /**
     * Test transaction loading
     *
     * @return void
     */
    public function testLoad(): void
    {
        $this->login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isAdmin', 'isStaff'))
            ->getMock();
        $this->login->method('isLogged')->willReturn(true);
        $this->login->method('isAdmin')->willReturn(true);
        $this->login->method('isStaff')->willReturn(true);

        $this->logSuperAdmin();
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();
        $this->login->logOut();

        $id = $this->transaction->id;
        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);

        $this->assertTrue($transaction->load((int)$id));
        $this->assertFalse($transaction->load(1355522012));
        $this->expectLogEntry(
            \Analog::ERROR,
            'No transaction #1355522012'
        );
    }

    /**
     * Test transaction removal
     *
     * @return void
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();

        $this->getMemberOne();
        $this->createTransaction();

        $tid = $this->transaction->id;
        $this->assertTrue($this->transaction->load($tid));
        $this->assertTrue($this->transaction->remove($this->history));
        $this->assertFalse($this->transaction->load($tid));
        $this->expectLogEntry(
            \Analog::ERROR,
            'No transaction #' . $tid
        );
        $this->assertFalse($this->transaction->remove($this->history));
        $this->expectLogEntry(
            \Analog::WARNING,
            'Transaction has not been removed!'
        );
    }

    /**
     * Test can* methods
     *
     * @return void
     */
    public function testCan(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();
        $transaction = $this->transaction;
        $this->login->logOut();

        $this->assertFalse($transaction->canCreate($this->login));
        $this->assertFalse($transaction->canShow($this->login));
        $this->assertFalse($transaction->canEdit($this->login));
        $this->assertFalse($transaction->canAttachAndDetach($this->login));
        $this->assertFalse($transaction->canDelete($this->login));

        //Superadmin can fully change transactions
        $this->logSuperAdmin();

        $this->assertTrue($transaction->canCreate($this->login));
        $this->assertTrue($transaction->canShow($this->login));
        $this->assertTrue($transaction->canEdit($this->login));
        $this->assertTrue($transaction->canAttachAndDetach($this->login));
        $this->assertTrue($transaction->canDelete($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Member can fully change its own transactions
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertFalse($transaction->canCreate($this->login));
        $this->assertTrue($transaction->canShow($this->login));
        $this->assertFalse($transaction->canEdit($this->login));
        $this->assertFalse($transaction->canAttachAndDetach($this->login));
        $this->assertFalse($transaction->canDelete($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Another member has no access
        $member_two = $this->getMemberTwo();
        $mdata = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertFalse($transaction->canShow($this->login));

        //parents can chow change children transactions
        $mdata = $this->dataAdherentOne();
        global $login;
        $login = $this->login;
        $this->logSuperAdmin();

        $child_data = [
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $member_one->id,
            'attach'        => true,
            'login_adh'     => 'child.johny.doe',
            'fingerprint' => 'FAKER' . $this->seed
        ];
        $child = $this->createMember($child_data);
        $cid = $child->id;

        //transaction for child
        $date = new \DateTime(); // 2020-11-07

        $data = [
            'id_adh' => $cid,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'trans_desc' => 'FAKER' . $this->seed
        ];

        $ctransaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $check = $ctransaction->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $ctransaction->store($this->history);
        $this->assertTrue($store);

        $this->login->logOut();

        //load child from db
        $child = new \Galette\Entity\Adherent($this->zdb);
        $child->enableDep('parent');
        $this->assertTrue($child->load($cid));

        $this->assertSame($child_data['nom_adh'], $child->name);
        $this->assertInstanceOf('\Galette\Entity\Adherent', $child->parent);
        $this->assertSame($member_one->id, $child->parent->id);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertFalse($ctransaction->canCreate($this->login));
        $this->assertTrue($ctransaction->canShow($this->login));
        $this->assertFalse($ctransaction->canEdit($this->login));
        $this->assertFalse($ctransaction->canAttachAndDetach($this->login));
        $this->assertFalse($ctransaction->canDelete($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        $mdata = $this->dataAdherentTwo();
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));

        //by default, groups manager can't do anything
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($transaction->canCreate($this->login));
        $this->assertFalse($transaction->canShow($this->login));
        $this->assertFalse($transaction->canEdit($this->login));
        $this->assertFalse($transaction->canAttachAndDetach($this->login));
        $this->assertFalse($transaction->canDelete($this->login));

        //change preferences so managers can see group members contributions
        $this->preferences->pref_bool_groupsmanagers_see_transactions = true;
        $this->assertTrue($this->preferences->store());

        $this->assertFalse($transaction->canCreate($this->login));
        $this->assertTrue($transaction->canShow($this->login));
        $this->assertFalse($transaction->canEdit($this->login));
        $this->assertFalse($transaction->canAttachAndDetach($this->login));
        $this->assertFalse($transaction->canDelete($this->login));

        //can create && can see
        $this->preferences->pref_bool_groupsmanagers_create_transactions = true;
        $this->assertTrue($this->preferences->store());

        $this->assertTrue($transaction->canCreate($this->login));
        $this->assertTrue($transaction->canShow($this->login));
        $this->assertFalse($transaction->canEdit($this->login));
        $this->assertFalse($transaction->canAttachAndDetach($this->login));
        $this->assertFalse($transaction->canDelete($this->login));

        //can attach and detach
        $this->preferences->pref_bool_groupsmanagers_create_contributions = true;
        $this->assertTrue($this->preferences->store());

        $this->assertTrue($transaction->canAttachAndDetach($this->login));
    }

    /**
     * Test a transaction
     *
     * @return void
     */
    public function testTransaction(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();

        $contribs_ids = [];
        $tid = $this->transaction->id;

        //create a contribution attached to transaction
        $bdate = new \DateTime(); // 2020-11-07
        $bdate->sub(new \DateInterval('P5M')); // 2020-06-07
        $bdate->add(new \DateInterval('P3D')); // 2020-06-10

        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1,
            'montant_cotis' => 25,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $bdate->format('Y-m-d'),
            'date_debut_cotis' => $bdate->format('Y-m-d'),
            'date_fin_cotis' => $edate->format('Y-m-d'),
            \Galette\Entity\Transaction::PK => $tid
        ];
        $contrib = new \Galette\Entity\Contribution(
            $this->zdb,
            $this->login,
            [
                'type' => $data['id_type_cotis'],
                'trans' => $tid
            ]
        );
        $this->assertSame($this->transaction->payment_type, $contrib->payment_type);

        $contrib = $this->createContrib($data, $contrib);
        $contribs_ids[] = $contrib->id;

        $this->assertTrue($contrib->isTransactionPart());
        $this->assertTrue($contrib->isTransactionPartOf($this->transaction->id));
        $this->assertNotEquals($this->transaction->payment_type, $contrib->payment_type);

        $this->assertSame(
            (double)25,
            $this->transaction->getDispatchedAmount()
        );
        $this->assertSame(
            (double)67,
            $this->transaction->getMissingAmount()
        );
        $this->assertSame('transaction-uncomplete', $this->transaction->getRowClass());

        //complete the transaction
        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 67,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $bdate->format('Y-m-d'),
            'date_debut_cotis' => $bdate->format('Y-m-d'),
            'date_fin_cotis' => $edate->format('Y-m-d'),
            \Galette\Entity\Transaction::PK => $tid
        ];
        $contrib = $this->createContrib($data);
        $contribs_ids[] = $contrib->id;

        $this->assertTrue($contrib->isTransactionPart());
        $this->assertTrue($contrib->isTransactionPartOf($this->transaction->id));
        $this->assertFalse($contrib->isFee());
        $this->assertSame('Donation', $contrib->getTypeLabel());
        $this->assertSame('donation', $contrib->getRawType());
        $this->assertSame($this->transaction->payment_type, $contrib->payment_type);

        $this->assertSame(
            (double)92,
            $this->transaction->getDispatchedAmount()
        );
        $this->assertSame(
            (double)0,
            $this->transaction->getMissingAmount()
        );
        $this->assertSame('transaction-normal', $this->transaction->getRowClass());

        //cannot add more
        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 4, //donation
            'montant_cotis' => 36,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $bdate->format('Y-m-d'),
            'date_debut_cotis' => $bdate->format('Y-m-d'),
            'date_fin_cotis' => $edate->format('Y-m-d'),
            \Galette\Entity\Transaction::PK => $tid
        ];
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $check = $contrib->check($data, [], []);
        $this->assertSame(['- Sum of all contributions exceed corresponding transaction amount.'], $check);
        $this->expectLogEntry(
            \Analog::ERROR,
            '- Sum of all contributions exceed corresponding transaction amount.'
        );

        $contrib_id = $contribs_ids[0];
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $contrib_id);
        $this->assertTrue($contrib->unsetTransactionPart($tid));

        $this->assertSame(
            (double)67,
            $this->transaction->getDispatchedAmount()
        );
        $this->assertSame(
            (double)25,
            $this->transaction->getMissingAmount()
        );
        $this->assertSame('transaction-uncomplete', $this->transaction->getRowClass());

        $this->assertTrue($contrib->setTransactionPart($tid));

        $this->assertSame(
            (double)92,
            $this->transaction->getDispatchedAmount()
        );
        $this->assertSame(
            (double)0,
            $this->transaction->getMissingAmount()
        );
        $this->assertSame('transaction-normal', $this->transaction->getRowClass());

        //delete transaction, and ensures all contributions has been removed as well
        $this->assertTrue($this->transaction->remove($this->history));
        $this->expectNoLogEntry();
        $this->assertFalse($this->transaction->load($tid));
        $this->expectLogEntry(
            \Analog::ERROR,
            'No transaction #' . $tid
        );
        foreach ($contribs_ids as $contrib_id) {
            $this->assertFalse($this->contrib->load($contrib_id));
            $this->expectLogEntry(
                \Analog::ERROR,
                'No contribution #' . $contrib_id
            );
        }
    }
}
