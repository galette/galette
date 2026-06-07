<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Updates;

use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\PaymentType;
use Galette\Repository\PaymentTypes;
use Galette\Tests\GaletteTestCase;
use Galette\Updater\AbstractUpdater;
use Laminas\Db\Sql\Expression;

/**
 * Payment types ids fix tests (1.10 and 1.21 upgrade scripts)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PaymentTypesIdsFix extends GaletteTestCase
{
    //sequence handling issues DDL statements, which implicitly commit transactions on MySQL
    protected bool $db_transactions = false;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->seed = 20260608;
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if (isset($this->zdb)) {
            $delete = $this->zdb->delete(Contribution::TABLE);
            $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
            $this->zdb->execute($delete);

            $delete = $this->zdb->delete(Adherent::TABLE);
            $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
            $this->zdb->execute($delete);

            //restore default payment types, even if test has failed
            $ptypes = new PaymentTypes($this->zdb, $this->preferences, $this->login);
            $ptypes->installInit(false);
        }
        parent::tearDown();
    }

    /**
     * Test a user defined payment type using a newly reserved id is moved, and relations follow
     */
    public function testFreeSystemPaymentTypesIds(): void
    {
        //create member and contribution first: instantiating PaymentTypes repository
        //would re-add missing system types (this is the bug being tested!)
        $this->logSuperAdmin();
        $this->getMemberOne();
        $this->createContribution();

        //simulate a pre-1.1 database: id 7 is a user defined type, ids 8 and 9 do not exist
        $delete = $this->zdb->delete(PaymentType::TABLE);
        $delete->where->in(PaymentType::PK, [PaymentType::STRIPE, PaymentType::HELLOASSO]);
        $this->zdb->execute($delete);

        $update = $this->zdb->update(PaymentType::TABLE);
        $update->set(['type_name' => 'Money transfer via owl']);
        $update->where([PaymentType::PK => PaymentType::SCHEDULED]);
        $this->zdb->execute($update);

        //contribution uses the user defined payment type
        $update = $this->zdb->update(Contribution::TABLE);
        $update->set(['type_paiement_cotis' => PaymentType::SCHEDULED]);
        $update->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($update);

        $updater = $this->getUpdater();
        $this->assertTrue(
            $this->invokeFreeIds($updater, [PaymentType::SCHEDULED => 'payment schedule'])
        );

        //id 7 is now free
        $select = $this->zdb->select(PaymentType::TABLE);
        $select->where([PaymentType::PK => PaymentType::SCHEDULED]);
        $this->assertSame(0, $this->zdb->execute($select)->count());

        //user defined type has been moved to next available id...
        $select = $this->zdb->select(PaymentType::TABLE);
        $select->where(['type_name' => 'Money transfer via owl']);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());
        $moved_id = (int)$results->current()->{PaymentType::PK};
        $this->assertSame(8, $moved_id);

        //...and contribution followed
        $select = $this->zdb->select(Contribution::TABLE);
        $select->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->assertSame(
            $moved_id,
            (int)$this->zdb->execute($select)->current()->type_paiement_cotis
        );

        $report = $updater->getReport();
        $this->assertCount(1, $report);
        $this->assertStringContainsString('Money transfer via owl', $report[0]['message']);

        //second run is a no-op
        $this->assertTrue(
            $this->invokeFreeIds($updater, [PaymentType::SCHEDULED => 'payment schedule'])
        );
        $this->assertCount(1, $updater->getReport());

        //sequence has been updated: a newly added payment type gets next id
        $insert = $this->zdb->insert(PaymentType::TABLE);
        $insert->values(['type_name' => 'Brand new type']);
        $this->zdb->execute($insert);
        $select = $this->zdb->select(PaymentType::TABLE);
        $select->columns(['max_id' => new Expression('MAX(' . PaymentType::PK . ')')]);
        $this->assertSame(9, (int)$this->zdb->execute($select)->current()->max_id);
    }

    /**
     * Test nothing is moved when system types are already present
     */
    public function testSystemPaymentTypesAreKept(): void
    {
        $updater = $this->getUpdater();
        $this->assertTrue(
            $this->invokeFreeIds(
                $updater,
                [
                    PaymentType::SCHEDULED => 'payment schedule',
                    PaymentType::STRIPE => 'stripe',
                    PaymentType::HELLOASSO => 'helloasso'
                ]
            )
        );
        $this->assertCount(0, $updater->getReport());

        //system types are still at their place
        $select = $this->zdb->select(PaymentType::TABLE);
        $select->where([PaymentType::PK => PaymentType::SCHEDULED]);
        $this->assertSame('Payment schedule', $this->zdb->execute($select)->current()->type_name);
    }

    /**
     * Get an updater instance, with database set
     */
    private function getUpdater(): AbstractUpdater
    {
        include_once GALETTE_BASE_PATH . 'install/scripts/upgrade-to-1.10.php';
        $updater = new \Galette\Updates\UpgradeTo110();
        $zdb = new \ReflectionProperty($updater, 'zdb');
        $zdb->setValue($updater, $this->zdb);
        return $updater;
    }

    /**
     * Invoke protected freeSystemPaymentTypesIds method
     *
     * @param AbstractUpdater   $updater Updater instance
     * @param array<int,string> $types   System payment types ids and names
     */
    private function invokeFreeIds(AbstractUpdater $updater, array $types): bool
    {
        $free_ids = new \ReflectionMethod($updater, 'freeSystemPaymentTypesIds');
        return $free_ids->invoke($updater, $types);
    }
}
