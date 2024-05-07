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

namespace Galette\Repository;

use Laminas\Db\ResultSet\ResultSet;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Entity\PaymentType;

/**
 * Payment types
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PaymentTypes extends Repository
{
    use RepositoryTrait;

    public const TABLE = PaymentType::TABLE;
    public const PK = PaymentType::PK;



    /**
     * Checks for missing payment types in the database
     *
     * @return boolean
     */
    protected function checkUpdate(): bool
    {
        try {
            $ent = $this->entity;
            $select = $this->zdb->select($ent::TABLE);
            $list = $this->zdb->execute($select);
            $list->buffer();

            $missing = array();
            foreach ($this->getInstallDefaultValues() as $row) {
                $exists = false;
                foreach ($list as $type) {
                    if ($type->type_id == $row['type_id']) {
                        $exists = true;
                        break;
                    }
                }

                if ($exists === false) {
                    //does not exists in database, insert it.
                    $missing[] = $row;
                }
            }

            if (count($missing) > 0) {
                $this->zdb->connection->beginTransaction();
                $this->multipleInsert($ent::TABLE, $missing);
                Analog::log(
                    'Missing payment types were successfully stored into database.',
                    Analog::INFO
                );
                $this->zdb->connection->commit();
                return true;
            }
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }
            throw $e;
        }
        return false;
    }



    /**
     * Get defaults values
     *
     * @return array<string, mixed>
     */
    protected function getInstallDefaultValues(): array
    {
        /*if (!count($this->defaults)) {
            $paytype = new PaymentType($this->zdb);
            $this->defaults = $paytype->getSystemTypes(false);
        }
        return $this->defaults;*/
        $paytype = new PaymentType($this->zdb);
        return self::convertArrayKeyValueForDBInsert($paytype->getSystemTypes(false), self::PK, 'type_name');
    }
}
