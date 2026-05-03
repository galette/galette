<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
     * @param bool $schedulable Types that can be used in schedules only
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
     * @param bool $schedulable Types that can be used in schedules only
     *
     * @return array<int, PaymentType>|ResultSet
     */
    public function getList(bool $schedulable = true): array|ResultSet
    {
        global $login;
        try {
            $select = $this->zdb->select(PaymentType::TABLE, 'a');
            $select->order(PaymentType::PK);

            if ($schedulable === false || !$login->isAdmin() && !$login->isStaff()) {
                $select->where->notEqualTo('a.' . PaymentType::PK, PaymentType::SCHEDULED);
            }

            $types = [];
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
     * @param bool $check_first Check first if it seems initialized
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
                    [
                        'counter' => new Expression('COUNT(' . $ent::PK . ')')
                    ]
                );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $count = $result->counter;
                if ($count < count($this->defaults)) {
                    return $this->checkUpdate();
                }
            }

            $this->zdb->beginTransaction();

            //first, we drop all values
            $delete = $this->zdb->delete($ent::TABLE);
            $this->zdb->execute($delete);

            $this->zdb->handleSequence(
                $ent::TABLE,
                $ent::PK,
                count($this->defaults)
            );
            $this->insert($ent::TABLE, $this->defaults);

            $this->zdb->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->zdb->inTransaction()) {
                $this->zdb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Checks for missing payment types in the database
     */
    protected function checkUpdate(): bool
    {
        try {
            $ent = $this->entity;
            $select = $this->zdb->select($ent::TABLE);
            $list = $this->zdb->execute($select);
            $list->buffer();

            $missing = [];
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
                $this->zdb->beginTransaction();
                $this->insert($ent::TABLE, $missing);
                Analog::log(
                    'Missing payment types were successfully stored into database.',
                    Analog::INFO
                );

                $this->zdb->handleSequence(
                    $ent::TABLE,
                    $ent::PK,
                    count($this->defaults)
                );

                $this->zdb->commit();
                return true;
            }
        } catch (Throwable $e) {
            if ($this->zdb->inTransaction()) {
                $this->zdb->rollback();
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
     */
    private function insert(string $table, array $values): void
    {
        $insert = $this->zdb->insert($table);
        $insert->values(
            [
                'type_id'   => ':type_id',
                'type_name' => ':type_name'
            ]
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
