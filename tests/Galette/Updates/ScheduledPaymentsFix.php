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
use Galette\Entity\ScheduledPayment;
use Galette\Tests\GaletteTestCase;
use Galette\Updater\AbstractUpdater;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scheduled payments table fix tests (1.21 and 1.30 upgrade scripts)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScheduledPaymentsFix extends GaletteTestCase
{
    //DDL statements implicitly commit transactions on MySQL
    protected bool $db_transactions = false;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->seed = 20260607;
        if ($this->zdb->isPostgres()) {
            $this->markTestSkipped('MyISAM legacy handling is MySQL only');
        }
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if (isset($this->zdb) && !$this->zdb->isPostgres()) {
            $delete = $this->zdb->delete(ScheduledPayment::TABLE);
            $this->zdb->execute($delete);

            $delete = $this->zdb->delete(Contribution::TABLE);
            $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
            $this->zdb->execute($delete);

            $delete = $this->zdb->delete(Adherent::TABLE);
            $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
            $this->zdb->execute($delete);

            //restore canonical table schema, even if test has failed
            $this->invokePreUpdate($this->getUpdater('1.21'));
        }
        parent::tearDown();
    }

    /**
     * Versions upgrade scripts providing the fix
     *
     * @return array<string, array{string}>
     */
    public static function updateScriptsProvider(): array
    {
        return [
            '1.21' => ['1.21'],
            '1.30' => ['1.30']
        ];
    }

    /**
     * Test engine, charset and foreign keys are fixed on a legacy table
     *
     * @param string $version Upgrade script version
     */
    #[DataProvider('updateScriptsProvider')]
    public function testPreUpdate(string $version): void
    {
        $table_name = PREFIX_DB . ScheduledPayment::TABLE;

        //simulate a table created from upgrade-to-1.10-mysql.sql on an old server:
        //MyISAM engine (foreign keys silently ignored), legacy default charset.
        foreach ($this->getForeignKeysNames($table_name) as $fk_name) {
            $this->zdb->db->query(
                sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table_name, $fk_name),
                Adapter::QUERY_MODE_EXECUTE
            );
        }
        $this->zdb->db->query(
            sprintf('ALTER TABLE %s ENGINE=MyISAM, CONVERT TO CHARACTER SET latin1', $table_name),
            Adapter::QUERY_MODE_EXECUTE
        );

        $this->logSuperAdmin();
        $this->getMemberOne();
        $this->createContribution();

        //one valid schedule, one orphan per relation
        $rows = [
            [Contribution::PK => $this->contrib->id, 'id_paymenttype' => PaymentType::CASH],
            [Contribution::PK => 999999, 'id_paymenttype' => PaymentType::CASH],
            [Contribution::PK => $this->contrib->id, 'id_paymenttype' => 999999],
        ];
        foreach ($rows as $row) {
            $insert = $this->zdb->insert(ScheduledPayment::TABLE);
            $insert->values(
                $row + [
                    'creation_date'  => date('Y-m-d'),
                    'scheduled_date' => date('Y-m-d'),
                    'amount'         => 10
                ]
            );
            $this->zdb->execute($insert);
        }

        $updater = $this->getUpdater($version);
        $this->assertTrue($this->invokePreUpdate($updater));

        //engine and charset have been fixed
        $result = $this->zdb->db->query(
            sprintf(
                'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'%s\'',
                $table_name
            ),
            Adapter::QUERY_MODE_EXECUTE
        )->current();
        $this->assertSame('InnoDB', $result['ENGINE']);
        $this->assertSame('utf8mb4_unicode_520_ci', $result['TABLE_COLLATION']);

        //both foreign keys have been restored, with CASCADE rules
        $fks = [];
        $results = $this->zdb->db->query(
            sprintf(
                'SELECT kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, rc.DELETE_RULE, rc.UPDATE_RULE
                 FROM information_schema.KEY_COLUMN_USAGE kcu
                 INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                 WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.TABLE_NAME = \'%s\' AND kcu.REFERENCED_TABLE_NAME IS NOT NULL',
                $table_name
            ),
            Adapter::QUERY_MODE_EXECUTE
        );
        foreach ($results as $fk) {
            $fks[$fk['COLUMN_NAME']] = sprintf(
                '%s.%s %s %s',
                $fk['REFERENCED_TABLE_NAME'],
                $fk['REFERENCED_COLUMN_NAME'],
                $fk['DELETE_RULE'],
                $fk['UPDATE_RULE']
            );
        }
        ksort($fks);
        $this->assertSame(
            [
                Contribution::PK => PREFIX_DB . Contribution::TABLE . '.' . Contribution::PK . ' CASCADE CASCADE',
                'id_paymenttype' => PREFIX_DB . PaymentType::TABLE . '.' . PaymentType::PK . ' CASCADE CASCADE'
            ],
            $fks
        );

        //orphaned rows have been removed, valid one is still present
        $results = $this->zdb->execute($this->zdb->select(ScheduledPayment::TABLE));
        $this->assertSame(1, $results->count());
        $this->assertSame($this->contrib->id, (int)$results->current()->{Contribution::PK});

        //report: table conversion, one orphan removal and one added key per relation
        $report = $updater->getReport();
        $this->assertCount(5, $report);
        $this->assertStringContainsString('has been converted to InnoDB/utf8mb4', $report[0]['message']);
        $this->assertStringContainsString('orphaned rows (unknown id_cotis)', $report[1]['message']);
        $this->assertStringContainsString(sprintf('Foreign key on %s.id_cotis', $table_name), $report[2]['message']);
        $this->assertStringContainsString('orphaned rows (unknown id_paymenttype)', $report[3]['message']);
        $this->assertStringContainsString(sprintf('Foreign key on %s.id_paymenttype', $table_name), $report[4]['message']);

        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'is using MyISAM engine and latin1_swedish_ci collation instead of InnoDB/utf8mb4'
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            sprintf('Foreign key from %s.id_cotis', $table_name)
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            sprintf('Foreign key from %s.id_paymenttype', $table_name)
        );

        //second run is a no-op
        $this->assertTrue($this->invokePreUpdate($updater));
        $this->assertCount(5, $updater->getReport());
    }

    /**
     * Get an updater instance, with database set
     *
     * @param string $version Upgrade script version
     */
    private function getUpdater(string $version): AbstractUpdater
    {
        include_once GALETTE_BASE_PATH . sprintf('install/scripts/upgrade-to-%s.php', $version);
        $class_name = '\\Galette\\Updates\\UpgradeTo' . str_replace('.', '', $version);
        /** @var AbstractUpdater $updater */
        $updater = new $class_name();
        $zdb = new \ReflectionProperty($updater, 'zdb');
        $zdb->setValue($updater, $this->zdb);
        return $updater;
    }

    /**
     * Invoke protected preUpdate method
     *
     * @param AbstractUpdater $updater Updater instance
     */
    private function invokePreUpdate(AbstractUpdater $updater): bool
    {
        $pre_update = new \ReflectionMethod($updater, 'preUpdate');
        return $pre_update->invoke($updater);
    }

    /**
     * Get foreign keys constraints names for a table
     *
     * @param string $table_name Prefixed table name
     *
     * @return string[]
     */
    private function getForeignKeysNames(string $table_name): array
    {
        $names = [];
        $results = $this->zdb->db->query(
            sprintf(
                'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = \'%s\'',
                $table_name
            ),
            Adapter::QUERY_MODE_EXECUTE
        );
        foreach ($results as $row) {
            $names[] = $row['CONSTRAINT_NAME'];
        }
        return $names;
    }
}
