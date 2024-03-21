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

namespace Galette\Entity;

use ArrayObject;
use Laminas\Db\Sql\Expression;
use Throwable;
use Galette\Core\Db;
use Analog\Analog;
use Galette\Features\I18n;
use Galette\Features\Translatable;

/**
 * Scheduled payment
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ScheduledPayment
{
    public const TABLE = 'payments_schedules';
    public const PK = 'id_schedule';

    private Db $zdb;
    private int $id;

    /**
     * Main constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param ArrayObject<string,int|string>|int|null $args Arguments
     */
    public function __construct(Db $zdb, ArrayObject|int $args = null)
    {
        $this->zdb = $zdb;
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a scheduled payment from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
     */
    private function load(int $id): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $rs = $results->current();

            $this->loadFromRs($rs);
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading scheduled payment #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load scheduled payment from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs(ArrayObject $rs): void
    {
        $pk = self::PK;
        $this->id = $rs->$pk;
        //$this->name = $rs->type_name;
    }

    /**
     * Store scheduled payment in database
     *
     * @return boolean
     */
    public function store(): bool
    {
        return false;
        /*$data = array(
            'type_name' => $this->name
        );
        try {
            if (isset($this->id) && $this->id > 0) {
                if ($this->old_name !== null) {
                    $this->deleteTranslation($this->old_name);
                    $this->addTranslation($this->name);
                }

                $update = $this->zdb->update(self::TABLE);
                $update->set($data)->where([self::PK => $this->id]);
                $this->zdb->execute($update);
            } else {
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $this->zdb->getLastGeneratedValue($this);

                $this->addTranslation($this->name);
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing payment type: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }*/
    }

    /**
     * Remove current
     *
     * @return boolean
     */
    public function remove(): bool
    {
        /*$id = $this->id;
        if ($this->isSystemType()) {
            throw new \RuntimeException(_T("You cannot delete system payment types!"));
        }

        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            $this->zdb->execute($delete);
            $this->deleteTranslation($this->name);
            Analog::log(
                'Payment type #' . $id . ' (' . $this->name
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete payment type ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }*/
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    /*public function __get(string $name)
    {
        switch ($name) {
            case 'id':
            case 'name':
                return $this->$name;
            default:
                Analog::log(
                    'Unable to get Title property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }*/

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     *
     * @return bool
     */
    /*public function __isset(string $name): bool
    {
        switch ($name) {
            case 'id':
            case 'name':
                return true;
        }

        return false;
    }*/

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    /*public function __set(string $name, $value): void
    {
        switch ($name) {
            case 'name':
                if (trim($value) === '') {
                    Analog::log(
                        'Name cannot be empty',
                        Analog::WARNING
                    );
                } else {
                    $this->old_name = $this->name;
                    $this->name     = $value;
                }
                break;
            default:
                Analog::log(
                    'Unable to set property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }*/

    /**
     * Get identifier
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Is a contribution handled from a scheduled payment?
     *
     * @param int $id_cotis Contribution identifier
     *
     * @return bool
     * @throws Throwable
     */
    public function isContributionHandled(int $id_cotis): bool
    {
        $select = $this->zdb->select(self::TABLE);
        $select->limit(1)->where([Contribution::PK => $id_cotis]);

        $results = $this->zdb->execute($select);
        return ($results->count() > 0);
    }

    /**
     * Get allocated amount
     *
     * @param int $id_cotis Contribution identifier
     *
     * @return float
     * @throws Throwable
     */
    public function getAllocation(int $id_cotis): float
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['allocation' => new Expression('SUM(amount)')]);
        $select->where([Contribution::PK => $id_cotis]);

        $results = $this->zdb->execute($select);
        $result = $results->current();
        return $result->allocation;
    }

    /**
     * Is scheduled payment fully allocated?
     *
     * @param Contribution $contrib Contribution
     *
     * @return bool
     */
    public function isFullyAllocated(Contribution $contrib): bool
    {
        return !($this->getAllocation($contrib->id) < $contrib->amount);
    }
}
