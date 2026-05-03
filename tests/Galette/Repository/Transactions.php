<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Safe\DateTime;

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
     * Set up tests
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
     */
    private function createTransaction(): void
    {
        $date = new DateTime();
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
     */
    #[AllowMockObjectsWithoutExpectations]
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

        $odate = new DateTime($this->transaction->date);
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

        $odate = new DateTime($this->transaction->date);
        $odate->modify('-10 day');
        $filters = new \Galette\Filters\TransactionsList();
        $filters->end_date_filter = $odate->format('Y-m-d');
        $transactions = new \Galette\Repository\Transactions($this->zdb, $this->login, $filters);
        $list = $transactions->getList(true);
        $this->assertCount(0, $list);

        //member with a transaction
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
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
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
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
        $this->expectLogEntry(\Analog\Analog::WARNING, "Trying to display transactions for member #{$this->adh->id} without appropriate ACLs");
    }

    /**
     * Test remove
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
