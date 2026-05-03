<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Safe\DateTime;
use Galette\Tests\GaletteTestCase;

/**
 * Scheduled payment tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScheduledPayment extends GaletteTestCase
{
    protected int $seed = 20240321210526;

    /**
     * Test add
     */
    public function testAdd(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $this->assertFalse($this->contrib->hasSchedule());
        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $this->assertFalse($scheduledPayment->isContributionHandled($this->contrib->id));
        $now = new DateTime();

        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'amount' => 10.0,
            'comment' => 'FAKER' . $this->seed
        ];
        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());

        $check = $scheduledPayment->check($data);
        if ($scheduledPayment->getErrors() !== []) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $pid = $scheduledPayment->getId();
        $this->assertTrue($this->contrib->hasSchedule());
        $this->assertTrue($scheduledPayment->isContributionHandled($this->contrib->id));

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb, $pid);
        $this->assertSame($data[\Galette\Entity\Contribution::PK], $scheduledPayment->getContribution()->id);
        $this->assertSame($data['id_paymenttype'], $scheduledPayment->getPaymentType()->id);
        $this->assertSame($data['scheduled_date'], $scheduledPayment->getScheduledDate());
        $this->assertSame($data['scheduled_date'], $scheduledPayment->getScheduledDate(false)->format('Y-m-d'));
        $this->assertSame($data['amount'], $scheduledPayment->getAmount());
        $this->assertSame($data['comment'], $scheduledPayment->getComment());
    }

    /**
     * Test update
     */
    public function testUpdate(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        //no amount, will take contribution amount
        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'comment' => 'FAKER' . $this->seed
        ];
        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());

        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $pid = $scheduledPayment->getId();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb, $pid);
        $this->assertSame($data[\Galette\Entity\Contribution::PK], $scheduledPayment->getContribution()->id);
        $this->assertSame($data['id_paymenttype'], $scheduledPayment->getPaymentType()->id);
        $this->assertSame($data['scheduled_date'], $scheduledPayment->getScheduledDate());
        $this->assertSame($data['scheduled_date'], $scheduledPayment->getScheduledDate(false)->format('Y-m-d'));
        $this->assertSame($this->contrib->amount, $scheduledPayment->getAmount());
        $this->assertSame($data['comment'], $scheduledPayment->getComment());

        $data['amount'] = 20.0;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb, $pid);
        $this->assertSame($data[\Galette\Entity\Contribution::PK], $scheduledPayment->getContribution()->id);
        $this->assertSame($data['id_paymenttype'], $scheduledPayment->getPaymentType()->id);
        $this->assertSame($data['scheduled_date'], $scheduledPayment->getScheduledDate());
        $this->assertSame($data['scheduled_date'], $scheduledPayment->getScheduledDate(false)->format('Y-m-d'));
        $this->assertSame($data['amount'], $scheduledPayment->getAmount());
        $this->assertSame($data['comment'], $scheduledPayment->getComment());
    }

    /**
     * Test update
     */
    public function testCheck(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        $data = [];
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Contribution is required',
                'Payment type is required',
                'Scheduled date is required'
            ],
            $scheduledPayment->getErrors()
        );

        $data = [
            'scheduled_date' => $now->format('Y-m-d')
        ];
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Contribution is required',
                'Payment type is required'
            ],
            $scheduledPayment->getErrors()
        );

        $data += [
            'id_paymenttype' => \Galette\Entity\PaymentType::CREDITCARD
        ];
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Contribution is required'
            ],
            $scheduledPayment->getErrors()
        );

        $data += [
            \Galette\Entity\Contribution::PK => $this->contrib->id
        ];
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Payment type for contribution must be set to scheduled'
            ],
            $scheduledPayment->getErrors()
        );

        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);

        $data += [
            'amount' => -1
        ];
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Amount must be a positive number'
            ],
            $scheduledPayment->getErrors()
        );

        $data['amount'] = 0;
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Amount must be a positive number'
            ],
            $scheduledPayment->getErrors()
        );

        $data['amount'] = 200.0;
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Amount cannot be greater than non allocated amount'
            ],
            $scheduledPayment->getErrors()
        );

        $data['amount'] = 10.0;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);

        $data['id_paymenttype'] = \Galette\Entity\PaymentType::SCHEDULED;
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(
            [
                'Cannot schedule a scheduled payment!'
            ],
            $scheduledPayment->getErrors()
        );
    }

    /**
     * Test delete
     */
    public function testDelete(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        //no amount, will take contribution amount
        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'comment' => 'FAKER' . $this->seed
        ];
        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());

        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $pid = $scheduledPayment->getId();

        $this->assertTrue($scheduledPayment->load($pid));
        $this->assertTrue($scheduledPayment->remove());
        $this->assertFalse($scheduledPayment->load($pid));
    }

    /**
     * Test restrictions on contributions with a scheduled payment
     */
    public function testContributionRestriction(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        //no amount, will take contribution amount
        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'comment' => 'FAKER' . $this->seed
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

        //test it's not possible to change payment type if there is a scheduled payment
        $this->contrib->payment_type = \Galette\Entity\PaymentType::CASH;
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Existing errors prevents storing contribution: Array
(
    [0] => Cannot change payment type if there is an attached scheduled payment
)
');
        $this->assertFalse($this->contrib->store());
        $this->assertSame(
            ['Cannot change payment type if there is an attached scheduled payment'],
            $this->contrib->getErrors()
        );
    }

    /**
     * Test getNotFullyAllocated
     */
    public function testGetNotFullyAllocated(): void
    {
        // retrieve contributions with schedule as payment type and that are not allocated, or not fully allocated
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new DateTime();

        $nonfulls = $scheduledPayment->getNotFullyAllocated();
        $this->assertCount(0, $nonfulls); //no contributiopn with SCHEDULED payment type

        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());
        $this->assertSame([], $this->contrib->getErrors());

        $nonfulls = $scheduledPayment->getNotFullyAllocated();
        $this->assertCount(1, $nonfulls);
        $test = array_pop($nonfulls);
        $this->assertEquals(
            [
                \Galette\Entity\Contribution::PK => $this->contrib->id,
                'montant_cotis' => '92.00',
                'allocated' => null,
            ],
            $test
        );

        $data = [
            \Galette\Entity\Contribution::PK => $this->contrib->id,
            'id_paymenttype' => \Galette\Entity\PaymentType::CASH,
            'scheduled_date' => $now->format('Y-m-d'),
            'comment' => 'FAKER' . $this->seed,
            'amount' => 10.0
        ];

        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $data['amount'] = 24.5;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $nonfulls = $scheduledPayment->getNotFullyAllocated();
        $this->assertCount(1, $nonfulls);
        $test = array_pop($nonfulls);
        $this->assertEquals(
            [
                \Galette\Entity\Contribution::PK => $this->contrib->id,
                'montant_cotis' => '92.00',
                'allocated' => '34.50',
            ],
            $test
        );

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $data['amount'] = 92 - 34.5;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $nonfulls = $scheduledPayment->getNotFullyAllocated();
        $this->assertCount(0, $nonfulls);
    }

    /**
     * Test getAllocation
     */
    public function testGetAllocation(): void
    {
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

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $data['amount'] = 25.0;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $this->assertSame(35.0, $scheduledPayment->getAllocation($this->contrib->id));
    }

    /**
     * Test isFullyAllocated
     */
    public function testIsFullyAllocated(): void
    {
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

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $data['amount'] = 25.0;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $this->assertSame(35.0, $scheduledPayment->getAllocation($this->contrib->id));
        $this->assertSame(92.0 - 35.0, $scheduledPayment->getMissingAmount());
        $this->assertFalse($scheduledPayment->isFullyAllocated($this->contrib));

        //contribution amount is 92
        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $data['amount'] = 92 - 35 + 1;
        $check = $scheduledPayment->check($data);
        $this->assertFalse($check);
        $this->assertSame(['Amount cannot be greater than non allocated amount'], $scheduledPayment->getErrors());

        $data['amount'] = 92 - 35;
        $check = $scheduledPayment->check($data);
        if ($check !== true) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $this->assertSame(92.0, $scheduledPayment->getAllocation($this->contrib->id));
        $this->assertSame(92.0, $scheduledPayment->getAllocated());
        $this->assertSame(0.0, $scheduledPayment->getMissingAmount());
        $this->assertTrue($scheduledPayment->isFullyAllocated($this->contrib));
    }

    /**
     * Test isDue
     */
    public function testIsDue(): void
    {
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
        $this->assertFalse($scheduledPayment->isDue());

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $scheduled_date = clone $now;
        $scheduled_date = $scheduled_date->modify('+1 month');
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
        $this->assertFalse($scheduledPayment->isDue());

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $scheduled_date = clone $now;
        $scheduled_date = $scheduled_date->modify('-1 month');
        $data['scheduled_date'] = $scheduled_date->format('Y-m-d');
        $data['amount'] = 15.0;
        $data['paid'] = false;
        $data['id_paymenttype'] = \Galette\Entity\PaymentType::CASH;
        $check = $scheduledPayment->check($data);
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());
        $this->assertTrue($scheduledPayment->isDue());
    }
}
