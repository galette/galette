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

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Contributions repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Contributions extends GaletteTestCase
{
    protected int $seed = 20230327215258;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->zdb = new \Galette\Core\Db();

        $delete = $this->zdb->delete(\Galette\Entity\Transaction::TABLE);
        $delete->where(['trans_desc' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

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
     * Test getList
     *
     * @return void
     */
    public function testGetList(): void
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $list = $contributions->getList(true, null, true);
        $this->assertIsArray($list);
        $this->assertCount(0, $list);
        $this->assertSame(0, $contributions->getCount());
        $this->assertSame(0.0, $contributions->getSum());
        $member2 = $this->getMemberTwo();
        $this->getMemberOne();
        $this->createContribution();

        $list = $contributions->getList(true);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertSame(92.0, $contributions->getSum());

        //filters
        $filters = new \Galette\Filters\ContributionsList();
        $filters->filtre_cotis_adh = $member2->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->filtre_cotis_adh = $this->adh->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->max_amount = 90;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->max_amount = 95;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->start_date_filter = $this->contrib->begin_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->start_date_filter = $this->contrib->end_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_END;
        $filters->end_date_filter = $this->contrib->end_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_RECORD;
        $filters->start_date_filter = $this->contrib->date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_RECORD;
        $filters->start_date_filter = $this->contrib->end_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->payment_type_filter = $this->contrib->payment_type;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->contrib_type_filter = $this->contrib->type->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        //create a transaction
        $date = new \DateTime();
        $data = [
            'id_adh' => $this->adh->id,
            'trans_date' => $date->format('Y-m-d'),
            'trans_amount' => 92,
            'trans_desc' => 'FAKER' . $this->seed
        ];

        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $check = $transaction->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $transaction->store($this->history);
        $this->assertTrue($store);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->from_transaction = false;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->from_transaction = $transaction->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->contrib_type_filter = $this->contrib->id_type_cotis;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        //member with a contribution
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->setId($this->adh->id);
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_END;
        $filters->filtre_cotis_children = true;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        //member does not have any contribution
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->setId($member2->id);
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        //cannot load another simple member's contribution
        $filters = new \Galette\Filters\ContributionsList();
        $filters->filtre_cotis_adh = $this->adh->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);
    }

    /**
     * Test getArrayList
     *
     * @return void
     */
    public function testGetArrayList(): void
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $this->getMemberOne();
        $this->createContribution();

        $list = $contributions->getArrayList([$this->contrib->id], true);

        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $contrib = array_pop($list);
        $this->assertTrue($contrib instanceof \Galette\Entity\Contribution);

        $list = $contributions->getArrayList([$this->contrib->id], false);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $contrib = array_pop($list);
        $this->assertFalse($contrib instanceof \Galette\Entity\Contribution);

        $this->assertFalse($contributions->getArrayList([]));
    }

    /**
     * Test remove
     *
     * @return void
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $this->getMemberOne();
        $this->createContribution();

        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $this->assertTrue($contributions->remove($this->contrib->id, $this->history));

        $list = $contributions->getList(true);
        $this->assertCount(0, $list);
    }

    /**
     * Test order by
     *
     * @return void
     * @throws \Throwable
     */
    public function testOrderBy(): void
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $list = $contributions->getList(true, null, true);
        $this->assertIsArray($list);
        $this->assertCount(0, $list);
        $this->assertSame(0, $contributions->getCount());
        $this->assertSame(0.0, $contributions->getSum());
        $this->getMemberOne();
        $this->createContribution();

        $list = $contributions->getList(true);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertSame(92.0, $contributions->getSum());

        $order_fields = [
            \Galette\Filters\ContributionsList::ORDERBY_DATE,
            \Galette\Filters\ContributionsList::ORDERBY_BEGIN_DATE,
            \Galette\Filters\ContributionsList::ORDERBY_END_DATE,
            \Galette\Filters\ContributionsList::ORDERBY_MEMBER,
            \Galette\Filters\ContributionsList::ORDERBY_TYPE,
            \Galette\Filters\ContributionsList::ORDERBY_AMOUNT,
            \Galette\Filters\ContributionsList::ORDERBY_PAYMENT_TYPE,
            \Galette\Filters\ContributionsList::ORDERBY_ID,
        ];

        foreach ($order_fields as $order_field) {
            $filters = new \Galette\Filters\ContributionsList();
            $filters->orderby = $order_field;
            $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
            $list = $contributions->getList(true);
            $this->assertIsArray($list);
            $this->assertCount(1, $list);
            $this->assertSame(92.0, $contributions->getSum());
        }
    }
}
