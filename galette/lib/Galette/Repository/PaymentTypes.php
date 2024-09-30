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
    /**
     * Get payments types
     *
     * @param boolean $schedulable Types that can be used in schedules only
     *
     * @return array<int, PaymentType>
     */
    public static function getAll(bool $schedulable = true): array
    {
        global $zdb, $preferences, $login;
        $ptypes = new self($zdb, $preferences, $login);
        return $ptypes->getList($schedulable);
    }

    /**
     * Get list
     *
     * @param boolean $schedulable Types that can be used in schedules only
     *
     * @return array<int, PaymentType>|ResultSet
     */
    public function getList(bool $schedulable = true): array|ResultSet
    {
        try {
            $select = $this->zdb->select(PaymentType::TABLE, 'a');
            $select->order(PaymentType::PK);

            if ($schedulable === false) {
                $select->where->notEqualTo('a.' . PaymentType::PK, PaymentType::SCHEDULED);
            }

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
                if ($count < count($this->defaults)) {
                    return $this->checkUpdate();
                }
            }

            $this->zdb->connection->beginTransaction();

            //first, we drop all values
            $delete = $this->zdb->delete($ent::TABLE);
            $this->zdb->execute($delete);

            $this->zdb->handleSequence(
                $ent::TABLE,
                $ent::PK,
                count($this->defaults)
            );
            $this->insert($ent::TABLE, $this->defaults);

            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }
            throw $e;
        }
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
     * Insert values in database
     *
     * @param string              $table  Table name
     * @param array<string,mixed> $values Values to insert
     *
     * @return void
     */
    private function insert(string $table, array $values): void
    {
        $insert = $this->zdb->insert($table);
        $insert->values(
            array(
                'type_id'   => ':type_id',
                'type_name' => ':type_name'
            )
        );
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

        foreach ($values as $k => $v) {
            $value = [
                ':type_id'      => $k,
                ':type_name'    => $v
            ];
            $stmt->execute($value);
        }
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
        return parent::loadDefaults();
    }
}
