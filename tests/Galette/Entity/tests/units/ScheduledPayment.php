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
 * Scheduled payment tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScheduledPayment extends GaletteTestCase
{
    private array $remove = [];
    protected int $seed = 20240321210526;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->initPaymentTypes();
        $this->initContributionsTypes();
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
        $this->deleteScheduledPayments();
    }

    /**
     * Delete payment type
     *
     * @return void
     */
    private function deleteScheduledPayments()
    {
        $delete = $this->zdb->delete(\Galette\Entity\ScheduledPayment::TABLE);
        $delete->where(['comment' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test add
     *
     * @return void
     */
    public function testAdd(): void
    {
        $this->getMemberOne();
        //create contribution for member
        $this->createContribution();

        $scheduledPayment = new \Galette\Entity\ScheduledPayment($this->zdb);
        $scheduledPayment
            ->setContribution($this->contrib)
            ->setPaymentType(\Galette\Entity\PaymentType::CASH)
            ->setScheduledDate(new \DateTime())
            ->setAmount(10.0)
            ->setComment('FAKER' . $this->seed);
    }

    /**
     * Test update
     *
     * @return void
     */
    public function testUpdate(): void
    {
        //todo
    }

    /**
     * Test delete
     *
     * @return void
     */
    public function testDelete(): void
    {
        //todo
    }

    /**
     * Test restrictions on contributions with a scheduled payment
     *
     * @return void
     */
    public function testContributionRestriction(): void
    {
        //test it's not possible to change payment type if there is a scheduled payment
    }

    /**
     * Test getNotFullyAllocated
     *
     * @return void
     */
    public function testGetNotFullyAllocated(): void
    {
        //retrieve contributions with schedule as payment type and that are not allocated, or not fully allocated
    }

    /**
     * Test getAllocation
     *
     * @return void
     */
    public function testGetAllocation(): void
    {
        //todo
    }

    /**
     * Test isFullyAllocated
     *
     * @return void
     */
    public function testIsFullyAllocated(): void
    {
        //todo
    }
}
