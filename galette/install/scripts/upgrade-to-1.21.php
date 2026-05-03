<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\Entity\PaymentType;
use Galette\Updater\AbstractUpdater;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;

/**
 * Galette 1.2.1 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo121 extends AbstractUpdater
{
    protected ?string $db_version = '1.21';

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        $types = [
            PaymentType::STRIPE => 'stripe', //payment type 8 is "Stripe"
            PaymentType::HELLOASSO => 'helloasso' //payment type 9 is "HelloAsso"
        ];

        $toupdate = [];
        foreach ($types as $type => $name) {
            $select = $this->zdb->select(PaymentType::TABLE);
            $select->where([PaymentType::PK => $type]);
            $results = $this->zdb->execute($select);
            if ($results->count() === 1) {
                $result = $results->current();
                if (strtolower((string)$result->type_name) != $name) {
                    //existing type is not same as new one. Move it.
                    $toupdate[$type] = $result->type_name;
                }
            }
        }

        if (count($toupdate)) {
            $select_max = $this->zdb->select(PaymentType::TABLE);
            $select_max->columns(['max_id' => new Expression('MAX(' . PaymentType::PK . ')')]);
            $results = $this->zdb->execute($select_max);
            $result = $results->current();
            $max_id = $result->max_id;
            foreach ($toupdate as $type => $name) {
                $update = $this->zdb->update(PaymentType::TABLE);
                $update->set([PaymentType::PK => ++$max_id]);
                $update->where([PaymentType::PK => $type]);
                $this->zdb->execute($update);
                $this->addReportEntry(
                    sprintf(_T('Payment type \'%1$s\' has been successfully modified.'), $name),
                    self::REPORT_SUCCESS
                );
            }

            //update auto increment
            if ($this->zdb->isPostgres()) {
                $seq = $this->zdb->getSequenceName(PaymentType::TABLE, PaymentType::PK);
                $this->zdb->db->query(
                    'SELECT setval(\'' . PREFIX_DB . $seq . '\', ' . $max_id . ')',
                    Adapter::QUERY_MODE_EXECUTE
                );
            } else {
                $this->zdb->db->query(
                    'ALTER TABLE ' . PREFIX_DB . PaymentType::TABLE . ' AUTO_INCREMENT=' . $max_id,
                    Adapter::QUERY_MODE_EXECUTE
                );
            }
        }

        return true;
    }
}
