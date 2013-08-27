<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Import model
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.6dev - 2013-09-26
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Import model entity
 *
 * @category  Entity
 * @name      ImportModel
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.6dev - 2013-09-26
 */
class ImportModel
{
    const TABLE = 'import_model';
    const PK = 'model_id';

    private $_id;
    private $_fields;
    private $_creation_date;

    /**
     * Loads model
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load()
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);

            $select->from(PREFIX_DB . self::TABLE)
                ->limit(1);
            $result = $select->query()->fetchObject();
            if ( $result ) {
                $this->_loadFromRS($result);
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load import model | ' . $e->getMessage() .
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ResultSet $r the resultset row
     *
     * @return void
     */
    private function _loadFromRS($r)
    {
        $this->_id = $r->model_id;
        $this->_fields = unserialize($r->model_fields);
        $this->_creation_date = $r->model_creation_date;
    }

    /**
     * Remove model
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove($zdb)
    {
        try {
            $result = $zdb->db->query('TRUNCATE TABLE ' . PREFIX_DB . self::TABLE);
            return $result;
        } catch (\Exception $e) {
            $zdb->db->rollBack();
            Analog::log(
                'Unable to remove import model ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Store the model
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function store($zdb)
    {
        try {
            $values = array(
                self::PK        => $this->_id,
                'model_fields'  => serialize($this->_fields)
            );

            if ( !isset($this->_id) || $this->_id == '') {
                //we're inserting a new model
                unset($values[self::PK]);
                $this->_creation_date = date("Y-m-d H:i:s");
                $values['model_creation_date'] = $this->_creation_date;
                $add = $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
                if ( $add > 0) {
                    return true;
                } else {
                    throw new \Exception(
                        'An error occured inserting new import model!'
                    );
                }
            } else {
                //we're editing an existing group
                $edit = $zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    $values,
                    self::PK . '=' . $this->_id
                );
                return true;
            }
        } catch ( \Exception $e ) {
            Analog::log(
                'Something went wrong storing import model :\'( | ' .
                $e->getMessage() . "\n" . $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get fields
     *
     * @return array
     */
    public function getFields()
    {
        return $this->_fields;
    }

    /**
     * Get creation date
     *
     * @param boolean $formatted Return date formatted, raw if false
     *
     * @return string
     */
    public function getCreationDate($formatted = true)
    {
        if ( $formatted === true ) {
            $date = new \DateTime($this->_creation_date);
            return $date->format(_T("Y-m-d"));
        } else {
            return $this->_creation_date;
        }
    }

    /**
     * Set fields
     *
     * @param array $fields Fields list
     *
     * @return void
     */
    public function setFields($fields)
    {
        $this->_fields = $fields;
    }
}
