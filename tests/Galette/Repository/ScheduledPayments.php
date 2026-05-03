<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Galette\Tests\GaletteTestCase;
use Safe\DateTime;

/**
 * Scheduled payments repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScheduledPayments extends GaletteTestCase
{
    protected int $seed = 20240407181603;

    /**
     * Test getList
     */
    public function testGetList(): void
    {
        $this->logSuperAdmin();
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login);

        $list = $scheduledPayments->getList(true, null);
        $this->assertIsArray($list);
        $this->assertCount(0, $list);
        $this->assertSame(0, $scheduledPayments->getCount());
        $this->assertSame(0.0, $scheduledPayments->getSum());

        //create contribution, and associated scheduled payments
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'comment' => 'FAKER' . $this->seed,
            'amount' => 10.0,
            'paid' => true
        ];
        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());
        $this->assertSame([], $this->contrib->getErrors());

        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $scheduled_date = $now->modify('+1 month');
        $data['scheduled_date'] = $scheduled_date->format('Y-m-d');
        $data['amount'] = 25.0;
        $data['paid'] = false;
        $data['id_paymenttype'] = \Galette\Entity\PaymentType::CREDITCARD;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $list = $scheduledPayments->getList(true);
        $this->assertIsArray($list);
        $this->assertCount(2, $list);
        $this->assertSame(35.0, $scheduledPayments->getSum());

        //filters
        $filters = new \Galette\Filters\ScheduledPaymentsList();
        $filters->from_contribution = $this->contrib->id + 1;
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login, $filters);
        $list = $scheduledPayments->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ScheduledPaymentsList();
        $filters->date_field = \Galette\Filters\ScheduledPaymentsList::DATE_SCHEDULED;
        $filters->start_date_filter = $scheduled_date->modify('-1 day')->format('Y-m-d');
        $filters->end_date_filter = $scheduled_date->modify('+1 day')->format('Y-m-d');
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login, $filters);
        $list = $scheduledPayments->getList(true);
        $this->assertCount(1, $list);
        $this->assertSame(25.0, $scheduledPayments->getSum());

        $filters = new \Galette\Filters\ScheduledPaymentsList();
        $filters->payment_type_filter = \Galette\Entity\PaymentType::CASH;
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login, $filters);
        $list = $scheduledPayments->getList(true);
        $this->assertCount(1, $list);
        $this->assertSame(10.0, $scheduledPayments->getSum());

        $filters = new \Galette\Filters\ScheduledPaymentsList();
        $filters->paid = \Galette\Filters\ScheduledPaymentsList::PAID_DC;
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login, $filters);
        $list = $scheduledPayments->getList(true);
        $this->assertCount(2, $list);
        $this->assertSame(35.0, $scheduledPayments->getSum());

        $filters->paid = \Galette\Filters\ScheduledPaymentsList::PAID_YES;
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login, $filters);
        $list = $scheduledPayments->getList(true);
        $this->assertCount(1, $list);
        $this->assertSame(10.0, $scheduledPayments->getSum());

        $filters->paid = \Galette\Filters\ScheduledPaymentsList::PAID_NO;
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login, $filters);
        $list = $scheduledPayments->getList(true);
        $this->assertCount(1, $list);
        $this->assertSame(25.0, $scheduledPayments->getSum());
    }

    /**
     * Test getArrayList
     */
    public function testGetArrayList(): void
    {
        $this->logSuperAdmin();
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login);

        $list = $scheduledPayments->getList(true, null);
        $this->assertIsArray($list);
        $this->assertCount(0, $list);
        $this->assertSame(0, $scheduledPayments->getCount());
        $this->assertSame(0.0, $scheduledPayments->getSum());

        //create contribution, and associated scheduled payments
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'comment' => 'FAKER' . $this->seed,
            'amount' => 10.0
        ];
        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());
        $this->assertSame([], $this->contrib->getErrors());

        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());
        $id_1 = $scheduledPayment->getId();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $scheduled_date = $now->modify('+1 month');
        $data['scheduled_date'] = $scheduled_date->format('Y-m-d');
        $data['amount'] = 25.0;
        $data['id_paymenttype'] = \Galette\Entity\PaymentType::CREDITCARD;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());
        $id_2 = $scheduledPayment->getId();

        $list = $scheduledPayments->getArrayList([$id_1, $id_2], true);
        $this->assertIsArray($list);
        $this->assertCount(2, $list);
        $contrib = array_pop($list);
        $this->assertTrue($contrib instanceof \Galette\Entity\ScheduledPayment);

        $list = $scheduledPayments->getArrayList([$id_1, $id_2], false);
        $this->assertIsArray($list);
        $this->assertCount(2, $list);
        $contrib = array_pop($list);
        $this->assertFalse($contrib instanceof \Galette\Entity\ScheduledPayment);
    }

    /**
     * Test remove
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $scheduledPayments = new \Galette\Repository\ScheduledPayments($this->zdb, $this->login);

        $list = $scheduledPayments->getList(true, null);
        $this->assertIsArray($list);
        $this->assertCount(0, $list);
        $this->assertSame(0, $scheduledPayments->getCount());
        $this->assertSame(0.0, $scheduledPayments->getSum());

        //create contribution, and associated scheduled payments
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'comment' => 'FAKER' . $this->seed,
            'amount' => 10.0
        ];
        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());
        $this->assertSame([], $this->contrib->getErrors());

        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());
        $id_1 = $scheduledPayment->getId();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $scheduled_date = $now->modify('+1 month');
        $data['scheduled_date'] = $scheduled_date->format('Y-m-d');
        $data['amount'] = 25.0;
        $data['id_paymenttype'] = \Galette\Entity\PaymentType::CREDITCARD;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());
        $id_2 = $scheduledPayment->getId();

        $list = $scheduledPayments->getList(true);
        $this->assertIsArray($list);
        $this->assertCount(2, $list);

        $this->assertTrue($scheduledPayments->remove([$id_1, $id_2], $this->history));

        $list = $scheduledPayments->getList(true);
        $this->assertCount(0, $list);
    }
}
