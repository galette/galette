<?php
/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Updates;

use Analog\Analog;
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
     *
     * @return boolean
     */
    protected function update(): bool
    {
        $this->zdb->connection->beginTransaction();

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
                array(
                    'perm' => $mapping[$result['field_perm']],
                    DynamicField::PK => $result[DynamicField::PK]
                )
            );
        }

        $this->zdb->connection->commit();
        return true;
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     *
     * @return boolean
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

            $queries = array();
            foreach (array_keys($ctypes) as $k) {
                if (count($results) > 0) {
                    //for each entry in types, we want to get the associated amount
                    foreach ($results as $paypal) {
                        if ($paypal[ContributionsTypes::PK] == $k) {
                            $queries[] = array(
                                'id'   => $k,
                                'amount' => (float)$paypal['amount']
                            );
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
                        array(
                            ContributionsTypes::PK => $q['id'],
                            'amount' => $q['amount']
                        )
                    );
                }
            }
        }
        return true;
    }
}
