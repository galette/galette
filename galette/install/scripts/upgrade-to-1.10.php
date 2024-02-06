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

namespace Galette\Updates;

use Analog\Analog;
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
        //to satisfy inheritance
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
            foreach ($ctypes as $k => $v) {
                if (count($results) > 0) {
                    //for each entry in types, we want to get the associated amount
                    foreach ($results as $paypal) {
                        if ($paypal[ContributionsTypes::PK] == $k) {
                            $queries[] = array(
                                'id'   => $k,
                                'amount' => (double)$paypal['amount']
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
