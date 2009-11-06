<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members class
 *
 * PHP version 5
 *
 * Copyright © 2009 Johan Cwiklinski
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
 * @version   $Id$
 * @link      http://galette.tuxfamily.org
 * @since     february, 28th 2009
 */

/** @ignore */
require_once 'adherent.class.php';
require_once 'status.class.php';

/**
 * Members class for galette
 *
 * @name Members
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Members
{
    const TABLE = Adherent::TABLE;
    const PK = Adherent::PK;

    const FILTER_NAME = 0;
    const FILTER_ADRESS = 1;
    const FILTER_MAIL = 2;
    const FILTER_JOB = 3;
    const FILTER_INFOS = 4;

    private $_filter = null;

    /**
    * Default constructor
    */
    public function __construct()
    {
    }

    /**
    * Get members list
    *
    * @param bool   $as_members return the results as an array of Member object. When true, fields are not relevant
    * @param array  $fields     field(s) name(s) to get. Should be a string or an array. If null, all fields will be returned
    * @param string $filter     should add filter... TODO
    *
    * @return Adherent[]|ResultSet
    */
    public static function getList($as_members=false, $fields=null, $filter=null)
    {
        global $mdb, $log;

        /** TODO: Check if filter is valid ? */
        if ( $filter != null && trim($filter) != '' ) $this->_filter = $filter;

        $fieldsList = ( $fields != null && !$as_members ) ? (( !is_array($fields) || count($fields) < 1 ) ? '*' : implode(', ', $fields)) : '*';

        $requete = 'SELECT ' . $fieldsList . ' FROM ' . PREFIX_DB . self::TABLE;

        $result = $mdb->query($requete);
        if (MDB2::isError($result)) {
            $log->log('Cannot list members | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
            return false;
        }

        $members = array();
        if ( $as_members ) {
            foreach ( $result->fetchAll() as $row ) {
                $members[] = new Adherent($row);
            }
        } else {
            $members = $result->fetchAll();
        }
        return $members;
    }

    /**
    * Get members list with public informations available
    *
    * @param boolean $with_photos get only members which have uploaded a photo (for trombinoscope)
    *
    * @return Adherent[]
    */
    public static function getPublicList($with_photos)
    {
        global $mdb, $log;

        $where = ' WHERE bool_display_info=1 AND (date_echeance > \''. date("Y-m-d") . '\' OR bool_exempt_adh=1)';

        if ( $with_photos ) {
            $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' a JOIN ' . PREFIX_DB . Picture::TABLE . ' p ON a.' . self::PK . '=p.' . self::PK . $where;
        } else {
            $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . $where;
        }

        $result = $mdb->query($requete);

        if (MDB2::isError($result)) {
            $log->log('Cannot list members with public informations (photos: ' . $with_photos . ') | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
            return false;
        }

        foreach ( $result->fetchAll() as $row ) {
            $members[] = new Adherent($row);
        }
        return $members;
    }

    /**
    * Get list of members that has been selected
    *
    * @param array  $ids     an array of members id that has been selected
    * @param string $orderby SQL order clause (optionnal)
    *
    * @return Adherent[]
    */
    public static function getArrayList($ids, $orderby = null)
    {
        global $mdb, $log;

        if ( !is_array($ids) || count($ids) < 1 ) {
            $log->log('No member selected for labels.', PEAR_LOG_INFO);
            return false;
        }

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=';
        $requete .= implode(' OR ' . self::PK . '=', $ids);

        if ( $orderby != null && trim($orderby) != '' ) $requete .= ' ORDER BY ' . $orderby;

        $result = $mdb->query($requete);

        if (MDB2::isError($result)) {
            $log->log('Cannot load members form ids array | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
            return false;
        }

        $members = array();
        foreach ( $result->fetchAll() as $row ) {
            $members[] = new Adherent($row);
        }
        return $members;
    }
}
?>