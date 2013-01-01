<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract dynamic field type
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @category  DynamicFields
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-07-28
 */

namespace Galette\DynamicFieldsTypes;

use Analog\Analog as Analog;
use Galette\Entity\DynamicFields as DynamicFields;

/**
 * Abstrac dynamic field type
 *
 * @name      DynamicFieldType
 * @category  DynamicFields
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

abstract class DynamicFieldType
{
    const TABLE = 'field_types';
    const PK = 'field_id';

    protected $has_data = false;
    protected $has_width = false;
    protected $has_height = false;
    protected $has_size = false;
    protected $multi_valued = false;
    protected $fixed_values = false;

    protected $id;
    protected $name;
    protected $perm;
    protected $required;
    protected $width;
    protected $height;
    protected $repeat;
    protected $size;
    protected $values;

    /**
     * Default constructor
     *
     * @param int $id Optionnal field id to load data
     */
    public function __construct($id = null)
    {
        if ( $id !== null ) {
            $this->id = $id;
        }
    }

    /**
     * Load field
     *
     * @return void
     */
    public function load()
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . self::TABLE
            )->where('field_id = ?', $this->id);
            $sql = $select->__toString();
            $result = $select->query()->fetch();

            if ($result !== false) {
                $this->name = $result->field_name;
                /*$this->type = $result->field_name;*/
                $this->perm = $result->field_perm;
                $this->required = $result->field_required;
                $this->width = $result->field_width;
                $this->height = $result->field_height;
                $this->repeat = $result->field_repeat;
                $this->size = $result->field_size;
                if ( $this->hasFixedValues() ) {
                    $this->_loadFixedValues();
                }
            } // $result != false
        } catch (Exception $e) {
            Analog::log(
                'Unable to retrieve fields types for field ' . $this->id . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Returns an array of fixed valued for a field of type 'choice'.
     *
     * @return void
     */
    private function _loadFixedValues()
    {
        global $zdb;

        try {
            $val_select = new \Zend_Db_Select($zdb->db);

            $val_select->from(
                DynamicFields::getFixedValuesTableName($this->id),
                'val'
            )->order('id');

            $results = $val_select->query()->fetchAll();
            $this->values = array();
            if ( $results ) {
                foreach ( $results as $val ) {
                    $this->values[] = $val->val;
                }
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $val_select->__toString() . ' ' . $e->__toString(),
                Analog::INFO
            );
        }
    }

    /**
     * Get field type name
     *
     * @return String
     */
    public abstract function getTypeName();

    /**
     * Does the field handle data?
     *
     * @return boolean
     */
    public function hasData()
    {
        return $this->has_data;
    }

    /**
     * Does the field has width?
     *
     * @return boolean
     */
    public function hasWidth()
    {
        return $this->has_width;
    }

    /**
     * Does the field has height?
     *
     * @return boolean
     */
    public function hasHeight()
    {
        return $this->has_height;
    }

    /**
     * Does the field has a size?
     *
     * @return boolean
     */
    public function hasSize()
    {
        return $this->has_size;
    }

    /**
     * Is the field multi valued?
     *
     * @return boolean
     */
    public function isMultiValued()
    {
        return $this->multi_valued;
    }

    /**
     * Does the field has fixed values?
     *
     * @return boolean
     */
    public function hasFixedValues()
    {
        return $this->fixed_values;
    }

    /**
     * Get field id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get field name
     *
     * @return String
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get field Permissions
     *
     * @return integer
     */
    public function getPerm()
    {
        return $this->perm;
    }

    /**
     * Is field required?
     *
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Get field width
     *
     * @return integer
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Get field height
     *
     * @return integer
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Get fields repetitions
     *
     * @return integer|boolean
     */
    public function getRepeat()
    {
        return $this->repeat;
    }

    /**
     * Get field size
     *
     * @return integer
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Get field values
     *
     * @return array
     */
    public function getValues()
    {

        if ( $this->fixed_values ) {
            return implode("\n", $this->values);
        } else {
            Analog::log(
                'Field do not have fixed values, cannot retrieve values.',
                Analog::INFO
            );
            return false;
        }
    }
}
