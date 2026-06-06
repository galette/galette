<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\DynamicFields\DynamicField;
use Galette\DynamicFields\ChoiceSpecifications;
use Galette\Updater\AbstractUpdater;
use Galette\Updater\ScheduledPaymentsFix;
use Throwable;

use function Safe\json_encode;

/**
 * Galette 1.3.0 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo130 extends AbstractUpdater
{
    use ScheduledPaymentsFix;

    protected ?string $db_version = '1.30';

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts($this->db_version);
    }

    /**
     * Pre stuff, if any.
     * Will be executed first.
     */
    protected function preUpdate(): bool
    {
        //users already on 1.2.1 did not get the scheduled payments table fix; replay it (no-op if already applied)
        return $this->fixScheduledPaymentsTable();
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        // Nothing to do here, all is done in postUpdate
        return true;
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     */
    protected function postUpdate(): bool
    {
        // Select all dynamic fields
        $select = $this->zdb->select(DynamicField::TABLE);
        $select->where(['field_type' => DynamicField::CHOICE]);
        $results = $this->zdb->execute($select);

        foreach ($results as $result) {
            $id = (int)$result->field_id;
            $contents_table = 'field_contents_' . $id;

            try {
                // Try to load values from the old table
                $val_select = $this->zdb->select($contents_table);
                $val_select->columns(['id', 'val'])->order('id');
                $val_results = $this->zdb->execute($val_select);

                $choices = [];
                foreach ($val_results as $val) {
                    $choices[] = $val->val;
                }

                if (count($choices) > 0) {
                    // Store choices in the new field_specifications column
                    $spec = new ChoiceSpecifications();
                    $spec->setChoices($choices);

                    $update = $this->zdb->update(DynamicField::TABLE);
                    $update->set(['field_specifications' => json_encode($spec)]);
                    $update->where([DynamicField::PK => $id]);
                    $this->zdb->execute($update);

                    $this->addReportEntry(
                        sprintf(_T('Choices for field %d have been migrated.'), $id),
                        self::REPORT_SUCCESS
                    );
                }

                // Drop the old table
                $this->zdb->drop($contents_table, true);
            } catch (Throwable $e) {
                // Table might not exist or other error, just log and continue
                $this->addReportEntry(
                    sprintf(_T('Unable to migrate choices for field %d: %s'), $id, $e->getMessage()),
                    self::REPORT_WARNING
                );
            }
        }

        return true;
    }
}
