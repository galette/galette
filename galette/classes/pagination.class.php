<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Global pagination
 *
 * PHP version 5
 *
 * Copyright © 2010 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-03
 */

/**
 * Pagination and ordering facilities
 *
 * @name      GalettePagination
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

abstract class GalettePagination
{
    private $_current_page;
    private $_orderby;
    private $_ordered;
    private $_show;
    private $_pages = 1;
    private $_counter = null;

    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    protected $pagination_fields = array(
        'current_page',
        'orderby',
        'ordered',
        'show',
        'pages',
        'counter'
    );

    /**
    * Default constructor
    */
    public function __construct()
    {
        $this->reinit();
    }

    /**
    * Returns the field we want to default set order to
    *
    * @return string field name
    */
    abstract protected function getDefaultOrder();

    /**
    * Reinit default parameters
    *
    * @return void
    */
    public function reinit()
    {
        global $preferences;

        $this->current_page = 1;
        $this->orderby = $this->getDefaultOrder();
        $this->ordered = self::ORDER_ASC;
        $this->show = $preferences->pref_numrows;
    }

    /**
    * Invert sort order
    *
    * @return void
    */
    public function invertorder()
    {
        $actual=$this->_ordered;
        if ($actual == self::ORDER_ASC) {
                $this->_ordered = self::ORDER_DESC;
        }
        if ($actual == self::ORDER_DESC) {
                $this->_ordered = self::ORDER_ASC;
        }
    }

    /**
    * Get current sort direction
    *
    * @return self::ORDER_ASC|self::ORDER_DESC
    */
    public function getDirection()
    {
        return $this->_ordered;
    }

    /**
    * Update or set pages count
    *
    * @return void
    */
    protected function countPages()
    {
        if ($this->_counter % $this->_show == 0) {
            $this->_pages = intval($this->_counter / $this->_show);
        } else {
            $this->_pages = intval($this->_counter / $this->_show) + 1;
        }
        if ($this->_pages == 0) {
            $this->_pages = 1;
        }
    }

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return object the called property
    */
    public function __get($name)
    {
        global $log;

        $log->log(
            '[' . get_class($this) .
            '|GalettePagination] Getting property `' . $name . '`',
            PEAR_LOG_DEBUG
        );

        if ( in_array($name, $this->pagination_fields) ) {
            $name = '_' . $name;
            return $this->$name;
        } else {
            $log->log(
                '[' . get_class($this) .
                '|GalettePagination] Unable to get proprety `' .$name . '`',
                PEAR_LOG_WARNING
            );
        }
    }

    /**
    * Global setter method
    *
    * @param string $name  name of the property we want to assign a value to
    * @param object $value a relevant value for the property
    *
    * @return void
    */
    public function __set($name, $value)
    {
        global $log;

        $log->log(
            '[' . get_class($this) . '|GalettePagination] Setting property `' .
            $name . '`',
            PEAR_LOG_DEBUG
        );

        switch($name) {
        case 'ordered':
            if ( $value == self::ORDER_ASC || $value == self::ORDER_DESC ) {
                $name = '_' . $name;
                $this->$name = $value;
            } else {
                $log->log(
                    '[' . get_class($this) .
                    '|GalettePagination] Possibles values for field `' .
                    $name . '` are: `' . self::ORDER_ASC . '` or `' .
                    self::ORDER_DESC . '` - `' . $value . '` given',
                    PEAR_LOG_WARNING
                );
            }
            break;
        case 'orderby':
            $name = '_' . $name;
            $this->$name = $value;
            break;
        case 'current_page':
        case 'counter':
        case 'pages':
            if ( is_int($value) && $value > 0 ) {
                $name = '_' . $name;
                $this->$name = $value;
            } else {
                $log->log(
                    '[' . get_class($this) .
                    '|GalettePagination] Value for field `' .
                    $name . '` should be a positive integer - (' .
                    gettype($value) . ')' . $value . ' given',
                    PEAR_LOG_WARNING
                );
            }
            break;
        case 'show':
            if (   $value == 'all'
                || preg_match('/[[:digit:]]/', $value)
                && $value >= 0
            ) {
                $name = '_' . $name;
                $this->$name = (int)$value;
            } else {
                $log->log(
                    '[' . get_class($this) . '|GalettePagination] Value for `' .
                    $name . '` should be a positive integer or \'all\' - (' .
                    gettype($value) . ')' . $value . ' given',
                    PEAR_LOG_WARNING
                );
            }
            break;
        default:
            $log->log(
                '[' . get_class($this) .
                '|GalettePagination] Unable to set proprety `' . $name . '`',
                PEAR_LOG_WARNING
            );
            break;
        }
    }
}
?>