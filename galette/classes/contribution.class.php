<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contribution class for galette
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
 * @since     Available since 0.7dev - 2010-03-11
 */

/** @ignore */
require_once 'adherent.class.php';
require_once 'contributions_types.class.php';

/*require_once 'politeness.class.php';
require_once 'status.class.php';
require_once 'fields_config.class.php';
require_once 'fields_categories.class.php';
require_once 'picture.class.php';*/

/**
 * Contribution class for galette
 *
 * @category  Classes
 * @name      Contribution
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-11
 */
class Contribution
{
    const TABLE = 'cotisations';
    const PK = 'id_cotis';

    private $_id;
    private $_date;
    private $_member;
    private $_type;
    private $_amount;
    private $_info;
    private $_begin_date;
    private $_end_date;
    private $_transaction = null; /** FIXME: unused for now */
    private $_is_cotis;

    //fields list and their translation
    public static $fields = array(
        'id_cotis',
        'id_adh',
        'id_type_cotis',
        'montant_cotis',
        'info_cotis',
        'date_enreg',
        'date_debut_cotis',
        'date_fin_cotis',
        'trans_id' /** FIXME: use Transaction class PK constant */
    );

    /**
    * Default constructor
    *
    * @param null|int|ResultSet $args Either a ResultSet row or its id for to load
    *                                   a specific contribution, or null to just
    *                                   instanciate object
    */
    public function __construct($args = null)
    {
        if ( $args == null || is_int($args) ) {
            $this->_date = date("Y-m-d");
            $this->_member = new Adherent();
            $this->_type = new ContributionsTypes();
            $this->_begin_date = $this->_date;

            if ( is_int($args) && $args > 0 ) {
                $this->load($args);
            }
        } elseif ( is_object($args) ) {
            $this->_loadFromRS($args);
        }
    }

    /**
    * Loads a contribution from its id
    *
    * @param int $id the identifiant for the contribution to load
    *
    * @return bool true if query succeed, false otherwise
    */
    public function load($id)
    {
        global $mdb, $log;

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' .
            self::PK . '=' . $id;

        $result = $mdb->query($requete);

        if (MDB2::isError($result)) {
            $log->log(
                'Cannot load contribution form id `' . $id . '` | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $this->_loadFromRS($result->fetchRow());
        $result->free();

        return true;
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
        $pk = self::PK;
        $this->_id = $r->$pk;
        $this->_date = $r->date_enreg;
        $this->_amount = $r->montant_cotis;
        $this->_info = $r->info_cotis;
        $this->_begin_date = $r->date_debut_cotis;
        $enddate = $r->date_fin_cotis;
        if ( $enddate == '0000-00-00' ) {
        } else {
            $this->_end_date = $r->date_fin_cotis;
        }
        $adhpk = Adherent::PK;
        $this->_member = (int)$r->$adhpk;

        $this->_type = new ContributionsTypes();
        $this->_type->load($r->id_type_cotis);
        if ( $this->_type->extension == 1 ) {
            $this->_is_cotis = true;
        } else {
            $this->_is_cotis = false;
        }
    }

    /**
    * Get the relevant CSS class for current contribution
    *
    * @return string current contribution row class
    */
    public function getRowClass()
    {
        return ( $this->_end_date != $this->_begin_date && $this->_is_cotis) ?
            'cotis-normal' :
            'cotis-give';
    }

    /**
    * Is member admin?
    *
    * @return bool
    */
    /*public function isAdmin()
    {
        return $this->_admin;
    }*/

    /**
    * Is member freed of dues?
    *
    * @return bool
    */
    /*public function isDueFree()
    {
        return $this->_due_free;
    }*/

    /**
    * Can member appears in public members list?
    *
    * @return bool
    */
    /*public function appearsInMembersList()
    {
        return $this->_appears_in_list;
    }*/

    /**
    * Is member active?
    *
    * @return bool
    */
    /*public function isActive()
    {
        return $this->_active;
    }*/

    /**
    * Does member have uploaded a picture?
    *
    * @return bool
    */
    /*public function hasPicture()
    {
        return $this->_picture->hasPicture();
    }*/

    /**
    * Get row class related to current fee status
    *
    * @return string the class to apply
    */
    /*public function getRowClass()
    {
        $strclass = ($this->isActive()) ? 'active' : 'inactive';
        $strclass .= $this->_row_classes;
        return $strclass;
    }*/

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return false|object the called property
    */
    public function __get($name)
    {
        $forbidden = array();
        $virtuals = array('duration');

        $rname = '_' . $name;
        if ( !in_array($name, $forbidden) && isset($this->$rname) || in_array($name, $virtuals) ) {
            switch($name) {
            case 'date':
            case 'begin_date':
                return date_db2text($this->$rname);
                break;
            case 'end_date':
                if ( !$this->_end_date ) {
                    return '';
                } else {
                    return date_db2text($this->$rname);
                }
                break;
            case 'duration':
                if ( $this->_is_cotis ) {
                    /*$date_now = new DateTime($this->_end_date);
                    return $date_now->diff(
                        new DateTime($this->_begin_date)
                    )->format('%d jours');*/
                    return distance_months($this->begin_date, $this->end_date);
                } else {
                    return '';
                }
                break;
            default:
                return $this->$rname;
                break;
            }
        } else {
            return false;
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
        /*$forbidden = array('fields');*/
        /** TODO: What to do ? :-) */
    }
}
?>