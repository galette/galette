<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members list parameters class for galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2011 The Galette Team
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
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     march, 3rd 2009
 */

require_once 'pagination.class.php';

/**
 * Members list parameters class for galette
 *
 * @name      VarsList
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class VarsList extends GalettePagination
{
    //filters
    private $_filter_str;
    private $_field_filter;
    private $_membership_filter;
    private $_account_status_filter;

    private $_selected;
    private $_unreachable;

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
    protected function getDefaultOrder()
    {
        return 'nom_adh';
    }

    /**
    * Reinit default parameters
    *
    * @return void
    */
    public function reinit()
    {
        parent::reinit();
        $this->filter_str = null;
        $this->_field_filter = null;
        $this->_membership_filter = null;
        $this->_account_status_filter = null;
        $this->_selected = array();
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
            '[VarsList] Getting property `' . $name . '`',
            PEAR_LOG_DEBUG
        );

        if ( in_array($name, $this->pagination_fields) ) {
            return parent::__get($name);
        } else {
            $return_ok = array(
                'filter_str',
                'field_filter',
                'membership_filter',
                'account_status_filter',
                'selected',
                'unreachable'
            );
            if (in_array($name, $return_ok)) {
                $name = '_' . $name;
                return $this->$name;
            } else {
                $log->log(
                    '[VarsList] Unable to get proprety `' .$name . '`',
                    PEAR_LOG_WARNING
                );
            }
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

        if ( in_array($name, $this->pagination_fields) ) {
            parent::__set($name, $value);
        } else {
            $log->log(
                '[VarsList] Setting property `' . $name . '`',
                PEAR_LOG_DEBUG
            );

            switch($name) {
            case 'selected':
            case 'unreachable':
                if (is_array($value)) {
                    $name = '_' . $name;
                    $this->$name = $value;
                } else {
                    $log->log(
                        '[VarsList] Value for property `' . $name .
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
                        '[VarsList] Value for property `' . $name .
                        '` should be an integer (' . gettype($value) . ' given)',
                        PEAR_LOG_WARNING
                    );
                }
                break;
            default:
                $log->log(
                    '[VarsList] Unable to set proprety `' . $name . '`',
                    PEAR_LOG_WARNING
                );
                break;
            }
        }
    }

    public function setLimit() {
        return $this->setLimits();
    }

    public function setCounter($c)
    {
        $this->counter = (int)$c;
        $this->countPages();
    }
}
?>