<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updater;

use Analog\Analog;
use Galette\Entity\Contribution;
use Galette\Entity\PaymentType;
use Galette\Entity\ScheduledPayment;
use Laminas\Db\Adapter\Adapter;

/**
 * Fix scheduled payments table engine, charset and relations.
 *
 * The table was originally created (1.10 upgrade) without ENGINE nor CHARSET specification,
 * which could result in MyISAM (foreign keys silently ignored) and/or a legacy charset on old servers.
 *
 * Used from both 1.21 and 1.30 upgrade scripts, since 1.2.1 has been released without the fix.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait ScheduledPaymentsFix
{
    /**
     * Check and fix scheduled payments table engine, charset and relations
     */
    protected function fixScheduledPaymentsTable(): bool
    {
        if ($this->zdb->isPostgres()) {
            return true;
        }

        $table_name = PREFIX_DB . ScheduledPayment::TABLE;

        // Check that galette_payments_schedules uses InnoDB engine and utf8mb4 charset.
        // InnoDB is required for the foreign key ON UPDATE CASCADE to work during payment type ID migration.
        $sql = sprintf(
            'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'%s\'',
            $table_name
        );
        $result = $this->zdb->db->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $row = $result->current();

        if (
            $row
            && (
                strtolower((string)$row['ENGINE']) !== 'innodb'
                || !str_starts_with(strtolower((string)$row['TABLE_COLLATION']), 'utf8mb4')
            )
        ) {
            Analog::log(
                sprintf(
                    'Table %s is using %s engine and %s collation instead of InnoDB/utf8mb4, converting...',
                    $table_name,
                    $row['ENGINE'],
                    $row['TABLE_COLLATION']
                ),
                Analog::WARNING
            );
            try {
                $this->zdb->db->query(
                    sprintf(
                        'ALTER TABLE %s ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci',
                        $table_name
                    ),
                    Adapter::QUERY_MODE_EXECUTE
                );
                $this->addReportEntry(
                    sprintf(_T('Table %1$s has been converted to InnoDB/utf8mb4.'), $table_name),
                    self::REPORT_SUCCESS
                );
            } catch (\Throwable $e) {
                Analog::log(
                    sprintf('Unable to convert table %s to InnoDB/utf8mb4: %s', $table_name, $e->getMessage()),
                    Analog::WARNING
                );
                $this->addReportEntry(
                    sprintf(_T('Unable to convert table %1$s to InnoDB/utf8mb4: %2$s'), $table_name, $e->getMessage()),
                    self::REPORT_WARNING
                );
            }
        }

        // Check if foreign keys exist.
        // They would be missing if the table was previously created as MyISAM (FK silently ignored).
        $foreign_keys = [
            'id_cotis' => [
                'table' => Contribution::TABLE,
                'pk'    => Contribution::PK
            ],
            'id_paymenttype' => [
                'table' => PaymentType::TABLE,
                'pk'    => PaymentType::PK
            ]
        ];

        foreach ($foreign_keys as $column => $reference) {
            $fk_sql = sprintf(
                'SELECT COUNT(*) AS cnt FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = \'%1$s\'
                 AND COLUMN_NAME = \'%2$s\'
                 AND REFERENCED_TABLE_NAME = \'%3$s\'',
                $table_name,
                $column,
                PREFIX_DB . $reference['table']
            );
            $fk_result = $this->zdb->db->query($fk_sql, Adapter::QUERY_MODE_EXECUTE);
            $fk_row = $fk_result->current();

            if ((int)$fk_row['cnt'] === 0) {
                Analog::log(
                    sprintf('Foreign key from %s.%s to %s is missing, adding...', $table_name, $column, PREFIX_DB . $reference['table']),
                    Analog::WARNING
                );
                try {
                    // Remove orphaned rows the missing ON DELETE CASCADE would have removed;
                    // they would prevent the foreign key from being added.
                    $subselect = $this->zdb->select($reference['table']);
                    $subselect->columns([$reference['pk']]);
                    $delete = $this->zdb->delete(ScheduledPayment::TABLE);
                    $delete->where->notIn($column, $subselect);
                    $deleted = $this->zdb->execute($delete);
                    if ($deleted->count() > 0) {
                        $this->addReportEntry(
                            sprintf(
                                _T('%1$s orphaned rows (unknown %2$s) have been removed from %3$s.'),
                                $deleted->count(),
                                $column,
                                $table_name
                            ),
                            self::REPORT_WARNING
                        );
                    }

                    $this->zdb->db->query(
                        sprintf(
                            'ALTER TABLE %1$s ADD FOREIGN KEY (%2$s) REFERENCES %3$s (%4$s) ON DELETE CASCADE ON UPDATE CASCADE',
                            $table_name,
                            $column,
                            PREFIX_DB . $reference['table'],
                            $reference['pk']
                        ),
                        Adapter::QUERY_MODE_EXECUTE
                    );
                    $this->addReportEntry(
                        sprintf(_T('Foreign key on %1$s.%2$s has been added.'), $table_name, $column),
                        self::REPORT_SUCCESS
                    );
                } catch (\Throwable $e) {
                    Analog::log(
                        sprintf('Unable to add foreign key on %s.%s: %s', $table_name, $column, $e->getMessage()),
                        Analog::WARNING
                    );
                    $this->addReportEntry(
                        sprintf(_T('Unable to add foreign key on %1$s.%2$s: %3$s'), $table_name, $column, $e->getMessage()),
                        self::REPORT_WARNING
                    );
                }
            }
        }

        return true;
    }
}
