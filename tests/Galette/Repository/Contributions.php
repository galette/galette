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
 * Contributions repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Contributions extends GaletteTestCase
{
    protected int $seed = 20230327215258;

    /**
     * Test getList
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetList(): void
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $list = $contributions->getList(true, null);
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
        $date = new DateTime();
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
        $filters->contrib_type_filter = $this->contrib->type->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        //member with a contribution
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
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
        $filters->filtre_cotis_children = $this->adh->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        //member does not have any contribution
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
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
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            "Trying to display contributions for member #{$this->adh->id} without appropriate ACLs"
        );
    }

    /**
     * Test getList as a group manager
     */
    public function testGetListAsGroupManager(): void
    {
        $this->logSuperAdmin();

        //two contributions for first member
        $member_one = $this->getMemberOne();
        $this->createContribution();
        $data = $this->getContribData();
        $data['id_type_cotis'] = 4; //donation
        $data['montant_cotis'] = 8;
        $this->createContrib($data);

        //one contribution for second member, that will manage groups
        $member_two = $this->getMemberTwo();
        $this->createContribution();

        //as admin, all contributions are listed and counted
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);
        $list = $contributions->getList(true);
        $this->assertCount(3, $list);
        $this->assertSame(3, $contributions->getCount());
        $this->assertSame(192.0, $contributions->getSum());

        //second member manages two groups, first member is part of both of them
        $g1 = new \Galette\Entity\Group();
        $g1->setName('Group 1');
        $this->assertTrue($g1->store());
        $this->assertTrue($g1->setManagers([$member_two]));
        $this->assertTrue($g1->setMembers([$member_one, $member_two]));

        $g2 = new \Galette\Entity\Group();
        $g2->setName('Group 2');
        $this->assertTrue($g2->store());
        $this->assertTrue($g2->setManagers([$member_two]));
        $this->assertTrue($g2->setMembers([$member_one]));

        $this->login->logOut();

        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $this->assertTrue($this->login->isGroupManager());

        $orig_pref = $this->preferences->pref_bool_groupsmanagers_see_contributions;
        $this->preferences->pref_bool_groupsmanagers_see_contributions = false;
        $this->assertTrue($this->preferences->store());

        //group manager only sees its own contributions
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);
        $this->assertSame(1, $contributions->getCount());
        $this->assertSame(92.0, $contributions->getSum());

        //let groups managers see their groups members contributions
        $this->preferences->pref_bool_groupsmanagers_see_contributions = true;
        $this->assertTrue($this->preferences->store());

        //first member is part of two managed groups, its contributions are listed only once
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);
        $list = $contributions->getList(true);
        $this->assertCount(3, $list);
        $this->assertSame(3, $contributions->getCount());
        $this->assertSame(192.0, $contributions->getSum());

        //count is required for pagination to work
        $filters = new \Galette\Filters\ContributionsList();
        $filters->show = 2;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(2, $list);
        $this->assertSame(3, $contributions->getCount());
        $this->assertSame(2, $filters->pages);

        $filters->current_page = 2;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);
        $this->assertSame(3, $contributions->getCount());

        //reset
        $this->preferences->pref_bool_groupsmanagers_see_contributions = $orig_pref;
        $this->assertTrue($this->preferences->store());
    }

    /**
     * Test getArrayList
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
     * @throws \Throwable
     */
    public function testOrderBy(): void
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $list = $contributions->getList(true, null);
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
            $filters->orderby = $order_field; //@phpstan-ignore assign.propertyType (class handle that)
            $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
            $list = $contributions->getList(true);
            $this->assertIsArray($list);
            $this->assertCount(1, $list);
            $this->assertSame(92.0, $contributions->getSum());
        }
    }
}
