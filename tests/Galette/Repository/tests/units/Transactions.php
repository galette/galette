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

declare(strict_types=1);

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Transactions repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Transactions extends GaletteTestCase
{
    protected int $seed = 20230328103438;
    private \Galette\Entity\Transaction $transaction;

    /**
     * Tear down tests
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

        //then, remove transactions
        $delete = $this->zdb->delete(\Galette\Entity\Transaction::TABLE);
        $delete->where(['trans_desc' => 'FAKER' . $this->seed]);
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
        $this->transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Create test transactions in database
     *
     * @return void
     */
    private function createTransaction(): void
    {
        $date = new \DateTime();
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
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList(): void
    {
        $this->logSuperAdmin();
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login);
        $list = $transactions->getList(true, null);

        $this->assertIsArray($list);
        $this->assertCount(0, $list);
        $this->assertSame(0, $transactions->getCount());

        $member2 = $this->getMemberTwo();
        $this->getMemberOne();
        $this->createTransaction();

        $list = $transactions->getList(true);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\TransactionsList();
        $filters->filtre_cotis_adh = $member2->id;
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\TransactionsList();
        $filters->filtre_cotis_adh = $this->adh->id;
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\TransactionsList();
        $filters->start_date_filter = $this->transaction->date;
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(1, $list);

        $odate = new \DateTime($this->transaction->date);
        $odate->modify('+10 day');
        $filters = new \Galette\Filters\TransactionsList();
        $filters->start_date_filter = $odate->format('Y-m-d');
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\TransactionsList();
        $filters->end_date_filter = $this->transaction->date;
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(1, $list);

        $odate = new \DateTime($this->transaction->date);
        $odate->modify('-10 day');
        $filters = new \Galette\Filters\TransactionsList();
        $filters->end_date_filter = $odate->format('Y-m-d');
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(0, $list);

        //member with a transaction
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->setId($this->adh->id);
        $transactions = new \Galette\Repository\Transactions($this->zdb, $login);
        $list = $transactions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\TransactionsList();
        $filters->filtre_cotis_children = $this->adh->id;
        $transactions = new \Galette\Repository\Transactions($this->zdb, $login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(1, $list);

        //member does not have any transaction
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->setId($member2->id);
        $transactions = new \Galette\Repository\Transactions($this->zdb, $login);
        $list = $transactions->getList(true);
        $this->assertCount(0, $list);

        //cannot load another simple member's transactions
        $filters = new \Galette\Filters\TransactionsList();
        $filters->filtre_cotis_adh = $this->adh->id;
        $transactions = new \Galette\Repository\Transactions($this->zdb, $login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(0, $list);
    }

    /**
     * Test remove
     *
     * @return void
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login);

        $this->getMemberOne();
        $this->createTransaction();

        $list = $transactions->getList(true);
        $this->assertCount(1, $list);

        $this->assertTrue($transactions->remove($this->transaction->id, $this->history));

        $list = $transactions->getList(true);
        $this->assertCount(0, $list);
    }
}
