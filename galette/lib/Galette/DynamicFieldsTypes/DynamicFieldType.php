<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract dynamic field type
 *
 * PHP version 5
 *
 * Copyright © 2012 The Galette Team
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
 * @copyright 2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-07-28
 */

namespace Galette\DynamicFieldsTypes;

use Galette\Common\KLogger as KLogger;

/**
 * Abstrac dynamic field type
 *
 * @name      DynamicFieldType
 * @category  DynamicFields
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012 The Galette Team
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

    /**
     * Default constructor
     */
    public function __construct()
    {
    }

    /**
     * Get field type name
     *
     * @return String
     */
    public abstract static function getName();

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
     * Get field width
     *
     * @return integer
     */
    /*public function getWidth()
    {
        return $this->width;
    }*/

    /**
     * Get field height
     *
     * @return integer
     */
    /*public function getHeight()
    {
        return $this->height;
    }*/

    /**
     * Get field size
     *
     * @return integer
     */
    /*public function getSize()
    {
        return $this->size;
    }*/
}
