<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Payment type
 *
 * PHP version 5
 *
 * Copyright © 2018-2021 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.2dev - 2018-07-23
 */

namespace Galette\Entity;

use Throwable;
use Galette\Core\Db;
use Analog\Analog;
use Galette\Features\I18n;
use Galette\Features\Translatable;

/**
 * Payment type
 *
 * @category  Entity
 * @name      PaymentType
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.2dev - 2018-07-23
 */

class PaymentType
{
    use Translatable;
    use I18n;

    public const TABLE = 'paymenttypes';
    public const PK = 'type_id';

    private $zdb;
    private $id;

    public const OTHER = 6;
    public const CASH = 1;
    public const CREDITCARD = 2;
    public const CHECK = 3;
    public const TRANSFER = 4;
    public const PAYPAL = 5;

    /**
     * Main constructor
     *
     * @param Db    $zdb  Database instance
     * @param mixed $args Arguments
     */
    public function __construct(Db $zdb, $args = null)
    {
        $this->zdb = $zdb;
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a payment type from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
     */
    private function load($id)
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $res = $results->current();

            $this->id = $id;
            $this->name = $res->type_name;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading payment type #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load payment type from a db ResultSet
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs($rs)
    {
        $pk = self::PK;
        $this->id = $rs->$pk;
        $this->name = $rs->type_name;
    }

    /**
     * Store payment type in database
     *
     * @return boolean
     */
    public function store()
    {
        $data = array(
            'type_name' => $this->name
        );
        try {
            if ($this->id !== null && $this->id > 0) {
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
        }
    }

    /**
     * Remove current title
     *
     * @return boolean
     */
    public function remove()
    {
        $id = (int)$this->id;
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
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        global $lang;

        switch ($name) {
            case 'id':
            case 'name':
                return $this->$name;
                break;
            default:
                Analog::log(
                    'Unable to get Title property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
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
    }

    /**
     * Get system payment types
     *
     * @param boolean $translated Return translated types (default) or not
     *
     * @return array
     */
    public function getSystemTypes($translated = true)
    {
        if ($translated) {
            $systypes = [
                self::OTHER         => _T("Other"),
                self::CASH          => _T("Cash"),
                self::CREDITCARD    => _T("Credit card"),
                self::CHECK         => _T("Check"),
                self::TRANSFER      => _T("Transfer"),
                self::PAYPAL        => _T("Paypal")
            ];
        } else {
            $systypes = [
                self::OTHER         => "Other",
                self::CASH          => "Cash",
                self::CREDITCARD    => "Credit card",
                self::CHECK         => "Check",
                self::TRANSFER      => "Transfer",
                self::PAYPAL        => "Paypal"
            ];
        }
        return $systypes;
    }

    /**
     * Is current payment a system one
     *
     * @return boolean
     *
     */
    public function isSystemType()
    {
        return isset($this->getSystemTypes()[$this->id]);
    }

    /**
     * Simple text representation
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}
