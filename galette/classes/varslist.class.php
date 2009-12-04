<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members list parameters class for galette
 *
 * PHP version 5
 *
 * Copyright © 2009 The Galette Team
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
 * @copyright 2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     march, 3rd 2009
 */

/**
 * Members list parameters class for galette
 *
 * @name      VarsList
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class VarsList
{
    private $_current_page;
    private $_orderby;
    private $_ordered;
    private $_show;

    //filters
    private $_filter_str;
    private $_field_filter;
    private $_membership_filter;
    private $_account_status_filter;

    private $_selected;
    private $_unreachable;

    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    /**
    * Default constructor
    */
    public function __construct()
    {
        $this->reinit();
    }

    /**
    * Reinit default parameters
    *
    * @return void
    */
    public function reinit()
    {
        global $preferences;
        $this->current_page = 1;
        $this->orderby = 'nom_adh';
        $this->ordered = self::ORDER_ASC;
        $this->show = $preferences->pref_numrows;
        $this->filter_str = null;
        $this->_field_filter = null;
        $this->_membership_filter = null;
        $this->_account_status_filter = null;
        $this->selected = array();
    }

    /**
    * Reset selected array
    *
    * @return void
    */
    public function clearSelected()
    {
        $this->_selected = array();
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
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return object the called property
    */
    public function __get($name)
    {
        global $log;
        $return_ok = array(
            'current_page',
            'orderby',
            'ordered',
            'show',
            'filter_str',
            'field_filter',
            'membership_filter',
            'account_status_filter',
            'selected',
            'unreachable',
        );
        if (in_array($name, $return_ok)) {
            $name = '_' . $name;
            return $this->$name;
        } else {
            $log->log(
                '[varslist.class.php] Unable to get proprety `' .$name . '`',
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
            '[varslist.class.php] Setting property `' . $name . '`',
            PEAR_LOG_DEBUG
        );

        switch($name) {
        case 'ordered':
            if ( $value == self::ORDER_ASC || $value == self::ORDER_DESC ) {
                $name = '_' . $name;
                $this->$name = $value;
            } else {
                $log->log(
                    '[varslist.class.php] Possibles values for field `' . $name .
                    '` are: `' . self::ORDER_ASC . '` or `' .
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
            if ( is_int($value) && $value > 0 ) {
                $name = '_' . $name;
                $this->$name = $value;
            } else {
                $log->log(
                    '[varslist.class.php] Value for field `' . $name .
                    '` should be a positive integer - (' .
                    gettype($value) . ')' . $value . ' given',
                    PEAR_LOG_WARNING
                );
            }
            break;
        case 'show':
            if (   $value == 'all'
                || preg_match('/[[:digit:]]/', $value)
                && $value > 0
            ) {
                $name = '_' . $name;
                $this->$name = (int)$value;
            } else {
                $log->log(
                    '[varslist.class.php] Value for `' . $name .
                    '` should be a positive integer or \'all\' - (' .
                    gettype($value) . ')' . $value . ' given',
                    PEAR_LOG_WARNING
                );
            }
            break;
        case 'selected':
        case 'unreachable':
            if (is_array($value)) {
                $name = '_' . $name;
                $this->$name = $value;
            } else {
                $log->log(
                    '[varslist.class.php] Value for property `' . $name .
                    '` should be an array (' . gettype($value) . ' given)',
                    PEAR_LOG_WARNING
                );
            }
            break;
        case 'filter_str':
            $this->$name = $value;
            break;
        case 'field_filter':
        case 'membership_filter':
        case 'account_status_filter':
            if ( is_numeric($value) ) {
                $name = '_' . $name;
                $this->$name = $value;
            } else {
                $log->log(
                    '[varslist.class.php] Value for property `' . $name .
                    '` should be an integer (' . gettype($value) . ' given)',
                    PEAR_LOG_WARNING
                );
            }
            break;
        default:
            $log->log(
                '[varslist.class.php] Unable to set proprety `' . $name . '`',
                PEAR_LOG_WARNING
            );
            break;
        }
    }
}
?>