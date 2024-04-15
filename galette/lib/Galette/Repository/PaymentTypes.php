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
     * Get payments types
     *
     * @return array<int, PaymentType>
     */
    public static function getAll(): array
    {
        global $zdb, $preferences, $login;
        $ptypes = new self($zdb, $preferences, $login);
        return $ptypes->getList();
    }

    /**
     * Get list
     *
     * @return array<int, PaymentType>|ResultSet
     */
    public function getList(): array|ResultSet
    {
        try {
            $select = $this->zdb->select(PaymentType::TABLE, 'a');
            $select->order(PaymentType::PK);

            $types = array();
            $results = $this->zdb->execute($select);
            foreach ($results as $row) {
                $types[$row->type_id] = new PaymentType($this->zdb, $row);
            }
            return $types;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list payment types | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Add default payment types in database
     *
     * @param boolean $check_first Check first if it seems initialized
     *
     * @return boolean
     */
    public function installInit(bool $check_first = true): bool
    {
        try {
            $ent = $this->entity;
            //first of all, let's check if data seem to have already
            //been initialized
            $proceed = false;
            if ($check_first === true) {
                $select = $this->zdb->select(PaymentType::TABLE);
                $select->columns(
                    array(
                        'counter' => new Expression('COUNT(' . $ent::PK . ')')
                    )
                );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $count = $result->counter;
                if ($count == 0) {
                    //if we got no values in table, let's proceed
                    $proceed = true;
                } else {
                    if ($count < count($this->defaults)) {
                        return $this->checkUpdate();
                    }
                    return false;
                }
            } else {
                $proceed = true;
            }

            if ($proceed === true) {
                $this->zdb->connection->beginTransaction();

                //first, we drop all values
                $delete = $this->zdb->delete($ent::TABLE);
                $this->zdb->execute($delete);

                $this->zdb->handleSequence(
                    $ent::TABLE,
                    count($this->defaults)
                );
                $this->insert($ent::TABLE, $this->defaults);

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
            foreach ($this->defaults as $key => $value) {
                $exists = false;
                foreach ($list as $type) {
                    if ($type->type_id == $key) {
                        $exists = true;
                        break;
                    }
                }

                if ($exists === false) {
                    //model does not exists in database, insert it.
                    $missing[$key] = $value;
                }
            }

            if (count($missing) > 0) {
                $this->zdb->connection->beginTransaction();
                $this->insert($ent::TABLE, $missing);
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
    protected function loadDefaults(): array
    {
        if (!count($this->defaults)) {
            $paytype = new PaymentType($this->zdb);
            $this->defaults = $paytype->getSystemTypes(false);
        }
        return $this->defaults;
    }
}
