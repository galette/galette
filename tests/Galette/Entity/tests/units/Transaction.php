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
 * Transaction tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Transaction extends GaletteTestCase
{
    protected int $seed = 95842354;
    /** @var \Galette\Entity\Transaction */
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

        //remove members with parents
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
        $this->zdb->execute($delete);

        //remove all others members
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
     * @return void
     */
    private function createTransaction()
    {
        $date = new \DateTime(); // 2020-11-07
        $data = [
            'id_adh' => $this->adh->id,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'trans_desc' => 'FAKER' . $this->seed
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
    public function testEmpty()
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
    }

    /**
     * Test getter and setter special cases
     *
     * @return void
     */
    public function testGetterSetter()
    {
        $transaction = $this->transaction;

        //set a bad date
        $data = ['trans_date' => 'mypassword'];
        $expected = ['- Wrong date format (Y-m-d) for Date!'];
        $check = $transaction->check($data, [], []);
        $this->assertSame($expected, $check);

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
    }

    /**
     * Test transaction creation
     *
     * @return void
     */
    public function testCreation()
    {
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();
    }

    /**
     * Test transaction update
     *
     * @return void
     */
    public function testUpdate()
    {
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();

        $this->logSuperAdmin();
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
    public function testGetFieldLabel()
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
    public function testLoad()
    {
        $this->login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isAdmin', 'isStaff'))
            ->getMock();
        $this->login->method('isLogged')->willReturn(true);
        $this->login->method('isAdmin')->willReturn(true);
        $this->login->method('isStaff')->willReturn(true);

        $this->getMemberOne();

        //create transaction for member
        $this->createTransaction();

        $id = $this->transaction->id;
        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);

        $this->assertTrue($transaction->load((int)$id));
        $this->assertFalse($transaction->load(1355522012));
    }

    /**
     * Test transaction removal
     *
     * @return void
     */
    public function testRemove()
    {
        $this->logSuperAdmin();

        $this->getMemberOne();
        $this->createTransaction();

        $tid = $this->transaction->id;
        $this->assertTrue($this->transaction->load($tid));
        $this->assertTrue($this->transaction->remove($this->history));
        $this->assertFalse($this->transaction->load($tid));
        $this->assertFalse($this->transaction->remove($this->history));
    }

    /**
     * Test can* methods
     *
     * @return void
     */
    public function testCan()
    {
        $this->getMemberOne();
        //create transaction for member
        $this->createTransaction();
        $transaction = $this->transaction;

        $this->assertFalse($transaction->canShow($this->login));

        //Superadmin can fully change transactions
        $this->logSuperAdmin();

        $this->assertTrue($transaction->canShow($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());

        //Member can fully change its own transactions
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertTrue($transaction->canShow($this->login));

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

        $this->assertFalse($transaction->canShow($this->login));

        //parents can chow change children transactions
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
        $this->assertSame($member->id, $child->parent->id);
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->login->isLogged());
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        $this->assertTrue($ctransaction->canShow($this->login));

        //logout
        $this->login->logOut();
        $this->assertFalse($this->login->isLogged());
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
        $contrib = $this->createContrib($data);
        $contribs_ids[] = $contrib->id;

        $this->assertTrue($contrib->isTransactionPart());
        $this->assertTrue($contrib->isTransactionPartOf($this->transaction->id));

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
            'type_paiement_cotis' => 3,
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

        $contrib_id = $contribs_ids[0];
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login, $contrib_id);
        $this->assertTrue($contrib->unsetTransactionPart($this->zdb, $this->login, $tid, $contrib_id));

        $this->assertSame(
            (double)67,
            $this->transaction->getDispatchedAmount()
        );
        $this->assertSame(
            (double)25,
            $this->transaction->getMissingAmount()
        );
        $this->assertSame('transaction-uncomplete', $this->transaction->getRowClass());

        $this->assertTrue($contrib->setTransactionPart($this->zdb, $tid, $contrib_id));

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
        $this->assertFalse($this->transaction->load($tid));
        foreach ($contribs_ids as $contrib_id) {
            $this->assertFalse($this->contrib->load($contrib_id));
        }
    }
}
