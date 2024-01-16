<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Import model
 *
 * PHP version 5
 *
 * Copyright © 2013-2023 The Galette Team
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
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.6dev - 2013-09-26
 */

namespace Galette\Entity;

use ArrayObject;
use Galette\Core\Db;
use Throwable;
use Analog\Analog;
use Laminas\Db\Adapter\Adapter;

/**
 * Import model entity
 *
 * @category  Entity
 * @name      ImportModel
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.6dev - 2013-09-26
 */
class ImportModel
{
    public const TABLE = 'import_model';
    public const PK = 'model_id';

    private ?int $id;
    private ?array $fields;
    private ?string $creation_date;

    /**
     * Loads model
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(): bool
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE);
            $select->limit(1);

            $results = $zdb->execute($select);
            $result = $results->current();

            if ($result) {
                $this->loadFromRS($result);
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load import model | ' . $e->getMessage() .
                "\n" . $e->__toString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = $r->model_id;
        $this->fields = unserialize($r->model_fields);
        $this->creation_date = $r->model_creation_date;
    }

    /**
     * Remove model
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove(Db $zdb): bool
    {
        try {
            $zdb->db->query(
                'TRUNCATE TABLE ' . PREFIX_DB . self::TABLE,
                Adapter::QUERY_MODE_EXECUTE
            );

            $this->id = null;
            $this->fields = null;
            $this->creation_date = null;
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to remove import model ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Store the model
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function store(Db $zdb): bool
    {
        try {
            $values = array(
                self::PK        => $this->id,
                'model_fields'  => serialize($this->fields)
            );

            if (!isset($this->id) || $this->id == '') {
                //we're inserting a new model
                unset($values[self::PK]);
                $this->creation_date = date("Y-m-d H:i:s");
                $values['model_creation_date'] = $this->creation_date;

                $insert = $zdb->insert(self::TABLE);
                $insert->values($values);
                $results = $zdb->execute($insert);

                if ($results->count() > 0) {
                    return true;
                } else {
                    throw new \Exception(
                        'An error occurred inserting new import model!'
                    );
                }
            } else {
                //we're editing an existing model
                $update = $zdb->update(self::TABLE);
                $update->set($values);
                $update->where([self::PK => $this->id]);
                $zdb->execute($update);
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Something went wrong storing import model :\'( | ' .
                $e->getMessage() . "\n" . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get fields
     *
     * @return ?array
     */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    /**
     * Get creation date
     *
     * @param boolean $formatted Return date formatted, raw if false
     *
     * @return string
     */
    public function getCreationDate(bool $formatted = true): string
    {
        if ($formatted === true) {
            $date = new \DateTime($this->creation_date);
            return $date->format(__("Y-m-d"));
        } else {
            return $this->creation_date;
        }
    }

    /**
     * Set fields
     *
     * @param array $fields Fields list
     *
     * @return self
     */
    public function setFields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }
}
