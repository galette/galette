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

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * Scheduled payment tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScheduledPayment extends GaletteTestCase
{
    protected int $seed = 20240321210526;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->deleteScheduledPayments();
    }

    /**
     * Delete scheduled payments
     *
     * @return void
     */
    private function deleteScheduledPayments(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\ScheduledPayment::TABLE);
        $delete->where(['comment' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Test add
     *
     * @return void
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
        $now = new \DateTime();

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
        if (count($scheduledPayment->getErrors())) {
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
     *
     * @return void
     */
    public function testUpdate(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new \DateTime();

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
     *
     * @return void
     */
    public function testCheck(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new \DateTime();

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
     *
     * @return void
     */
    public function testDelete(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new \DateTime();

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
     *
     * @return void
     */
    public function testContributionRestriction(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new \DateTime();

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
     *
     * @return void
     */
    public function testGetNotFullyAllocated(): void
    {
        // retrieve contributions with schedule as payment type and that are not allocated, or not fully allocated
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new \DateTime();

        $nonfulls = $scheduledPayment->getNotFullyAllocated();
        $this->assertCount(0, $nonfulls); //no contributiopn with SCHEDULED payment type

        $this->contrib->payment_type = \Galette\Entity\PaymentType::SCHEDULED;
        $this->assertTrue($this->contrib->store());
        $this->assertSame([], $this->contrib->getErrors());

        $nonfulls = $scheduledPayment->getNotFullyAllocated();
        $this->assertCount(1, $nonfulls);
        $test = array_pop($nonfulls);
        $this->assertSame(
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
        $this->assertSame(
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
     *
     * @return void
     */
    public function testGetAllocation(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new \DateTime();

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
     *
     * @return void
     */
    public function testIsFullyAllocated(): void
    {
        $this->logSuperAdmin();
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $now = new \DateTime();

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
        if (count($scheduledPayment->getErrors())) {
            var_dump($scheduledPayment->getErrors());
        }
        $this->assertTrue($check);
        $this->assertTrue($scheduledPayment->store());

        $this->assertSame(92.0, $scheduledPayment->getAllocation($this->contrib->id));
        $this->assertSame(92.0, $scheduledPayment->getAllocated());
        $this->assertSame(0.0, $scheduledPayment->getMissingAmount());
        $this->assertTrue($scheduledPayment->isFullyAllocated($this->contrib));
    }
}
