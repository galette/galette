<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\DynamicFields\DynamicField;
use Galette\Entity\ContributionsTypes;
use Galette\Updater\AbstractUpdater;
use GalettePaypal\Paypal;

/**
 * Galette 1.1.0 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo110 extends AbstractUpdater
{
    protected ?string $db_version = '1.10';

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts('1.10');
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        $this->zdb->beginTransaction();

        $results = $this->zdb->selectAll(DynamicField::TABLE);
        $results = $results->toArray();

        $mapping = [
            0 => 1, //DynamicField::PERM_USER_WRITE / 0 => FieldsConfig::USER_WRITE / 1
            2 => 3, //DynamicField::PERM_STAFF      / 2 => FieldsConfig::STAFF      / 3
            1 => 2, //DynamicField::PERM_ADMIN      / 1 => FieldsConfig::ADMIN      / 2
            3 => 4, //DynamicField::PERM_MANAGER    / 3 => FieldsConfig::MANAGER    / 4
            4 => 5 //DynamicField::PERM_USER_READ   / 4 => FieldsConfig::USER_READ  / 5
        ];

        $stmt = null;
        foreach ($results as $result) {
            if ($stmt === null) {
                $update = $this->zdb->update(DynamicField::TABLE);
                $update->set(['field_perm' => ':perm']);
                $update->where([DynamicField::PK => ':' . DynamicField::PK]);
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);
            }

            $stmt->execute(
                [
                    'perm' => $mapping[$result['field_perm']],
                    DynamicField::PK => $result[DynamicField::PK]
                ]
            );
        }

        $this->zdb->commit();
        return true;
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     */
    protected function postUpdate(): bool
    {
        //migrate paypal plugin amounts - if plugin table is present
        if (class_exists('\GalettePaypal\Paypal')) {
            //get core contribution types
            $ct = new ContributionsTypes($this->zdb);
            $ctypes = $ct->getCompleteList();

            //get paypal amounts
            $results = $this->zdb->selectAll('paypal_' . Paypal::TABLE);
            $results = $results->toArray();

            $queries = [];
            foreach (array_keys($ctypes) as $k) {
                if (count($results) > 0) {
                    //for each entry in types, we want to get the associated amount
                    foreach ($results as $paypal) {
                        if ($paypal[ContributionsTypes::PK] == $k) {
                            $queries[] = [
                                'id'   => $k,
                                'amount' => (float)$paypal['amount']
                            ];
                        }
                    }
                }
            }
            if (count($queries) > 0) {
                $update = $this->zdb->update(ContributionsTypes::TABLE);
                $update->set(['amount' => ':amount']);
                $update->where([ContributionsTypes::PK => ':' . ContributionsTypes::PK]);
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

                foreach ($queries as $q) {
                    $stmt->execute(
                        [
                            ContributionsTypes::PK => $q['id'],
                            'amount' => $q['amount']
                        ]
                    );
                }
            }
        }
        return true;
    }
}
