<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions class
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
require_once 'contribution.class.php';
/*require_once 'status.class.php';*/

/**
 * Contributions class for galette
 *
 * @name Contributions
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Contributions extends GalettePagination
{
    const TABLE = Contribution::TABLE;
    const PK = Contribution::PK;

    const FILTER_DATE_BEGIN = 0;
    const FILTER_DATE_END = 1;

    const ORDERBY_DATE = 0;
    const ORDERBY_BEGIN_DATE = 1;
    const ORDERBY_END_DATE = 2;
    const ORDERBY_MEMBER = 3;
    const ORDERBY_TYPE = 4;
    const ORDERBY_AMOUNT = 5;
    const ORDERBY_DURATION = 6;

    private $_count = null;

    /**
    * Default constructor
    */
    public function __construct()
    {
        parent::__construct();
    }

    /**
    * Returns the field we want to default set order to
    *
    * @return string field name
    */
    protected function getDefaultOrder()
    {
        return 'date_debut_cotis';
    }

    /**
    * Returns the field we want to default set order to (public method)
    *
    * @return string field name
    */
    public static function defaultOrder()
    {
        return self::getDefaultOrder();
    }

    /**
    * Get members list
    *
    * @param bool    $as_contrib return the results as an array of
    *                               Contribution object.
    * @param array   $fields     field(s) name(s) to get. Should be a string or
    *                               an array. If null, all fields will be
    *                               returned
    * @param boolean $count      true if we want to count members
    *
    * @return Contribution[]|ResultSet
    */
    public function getContributionsList(
        $as_contrib=false, $fields=null, $count=true
    ) {
        global $mdb, $log;

        $query = $this->_buildSelect($fields, $count);
        $result = $mdb->query($query);
        if (MDB2::isError($result)) {
            $log->log(
                'Cannot list contributions | ' . $result->getMessage() . '(' .
                $result->getDebugInfo() . ')', PEAR_LOG_ERROR
            );
            return false;
        }

        $contributions = array();
        if ( $as_contrib ) {
            foreach ( $result->fetchAll() as $row ) {
                $contributions[] = new Contribution($row);
            }
        } else {
            $contributions = $result->fetchAll();
        }
        return $contributions;
    }

    /**
    * Builds the SELECT statement
    *
    * @param array $fields fields list to retrieve
    * @param bool  $count  true if we want to count members
                            (not applicable from static calls), defaults to false
    *
    * @return string SELECT statement
    */
    private function _buildSelect($fields, $count = false)
    {
        $fieldsList = ( $fields != null && !$as_members )
                        ? (( !is_array($fields) || count($fields) < 1 ) ? '*'
                        : implode(', ', $fields)) : '*';

        $query = 'SELECT ' . $fieldsList . ' FROM ' . PREFIX_DB . self::TABLE;
        $querycount = 'SELECT count(' . self::PK . ') FROM ' .
            PREFIX_DB . self::TABLE;

        $join = ' a JOIN ' . PREFIX_DB . Adherent::TABLE .
            ' p ON a.' . Adherent::PK . '=p.' . Adherent::PK;
        $query .= $join;

        $where = $this->_buildWhereClause();
        $query .= $where;

        $query .= $this->_buildOrderClause();

        if ( $count ) {
            $this->_proceedCount($where);
        }

        return $query;
    }

    /**
    * Count contribtions from the query
    *
    * @param string $where where clause
    *
    * @return void
    */
    private function _proceedCount($where)
    {
        global $mdb, $log;

        $query = 'SELECT count(' . self::PK . ') FROM ' .
            PREFIX_DB . self::TABLE;
        $query .= $where;

        $result = $mdb->query($query);

        if (MDB2::isError($result)) {
            $log->log(
                'Cannot count contribution | ' . $result->getMessage() .
                '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $this->_count = $result->fetchOne();
    }

    /**
    * Builds the order clause
    *
    * @return string SQL ORDER clause
    */
    private function _buildOrderClause()
    {
        $order = ' ORDER BY ';

        switch ( $this->orderby ) {
            case self::ORDERBY_DATE:
                $order .= 'date_enreg';
                break;
            case self::ORDERBY_BEGIN_DATE:
                $order .= 'date_debut_cotis';
                break;
            case self::ORDERBY_END_DATE:
                $order .= 'date_fin_cotis';
                break;
            case self::ORDERBY_MEMBER:
                $order .= 'nom_adh, prenom_adh';
                break;
            case self::ORDERBY_TYPE:
                $order .= ContributionsTypes::PK;
                break;
            case self::ORDERBY_AMOUNT:
                $order .= 'montant_cotis';
                break;
            /*
            Hum... I really do not know how to sort a query with a value that
            is calculated code side :/
            case self::ORDERBY_DURATION:
                break;*/
            default:
                $order .= $this->orderby;
                break;
        }

        //set the direction, whatever the field was
        $order .= ' ' . $this->ordered;

        return $order;
    }

    /**
    * Builds where clause, for filtering on simple list mode
    *
    * @return string SQL WHERE clause
    */
    private function _buildWhereClause()
    {
        global $login, $mdb;
        $where = '';
        /*if ( $varslist->filter_str != '' ) {
            $where = ' WHERE ';
            $token = ' like \'%' . $varslist->filter_str . '%\'';
            $mdb->getDb()->loadModule('Function');
            switch( $varslist->field_filter ) {
            case 0: //Name
                $where .= $mdb->getDb()->concat(
                    'nom_adh', 'prenom_adh', 'pseudo_adh'
                ) . $token;
                $where .= ' OR ';
                $where .= $mdb->getDb()->concat(
                    'prenom_adh', 'nom_adh', 'pseudo_adh'
                ) . $token;
                break;
            case 1: //Address
                $where .= 'adresse_adh' .$token;
                $where .= ' OR adresse2_adh' .$token;
                $where .= ' OR cp_adh' .$token;
                $where .= ' OR ville_adh' .$token;
                $where .= ' OR pays_adh' .$token;
                break;
            case 2: //Email,URL,IM
                $where .= 'email_adh' . $token;
                $where .= ' OR url_adh' . $token;
                $where .= ' OR msn_adh' . $token;
                $where .= ' OR icq_adh' . $token;
                $where .= ' OR jabber_adh' . $token;
                break;
            case 3: //Job
                $where .= 'prof_adh' .$token;
                break;
            case 4: //Infos
                $where .= 'info_public_adh' . $token;
                $where .= ' OR info_adh' .$token;
                break;
            }
        }

        if ( $varslist->membership_filter ) {
            $where .= ($where == '') ? ' WHERE ' : ' AND ';
            switch($varslist->membership_filter) {
            case 1:
                $where .= 'date_echeance > \'' . date('Y-m-d', time()) .
                    '\' AND date_echeance < \'' .
                    date('Y-m-d', time() + (30 *24 * 60 * 60)) . '\'';
                    //(30 *24 * 60 * 60) => 30 days
                break;
            case 2:
                $where .= 'date_echeance < \'' . date('Y-m-d', time()) . '\'';
                break;
            case 3:
                $where .= '(date_echeance > \'' . date('Y-m-d', time()) .
                    '\' OR bool_exempt_adh=1)';
                break;
            case 4:
                $where .= 'isnull(date_echeance)';
                break;
            }
        }

        if ( $varslist->account_status_filter ) {
            $where .= ($where == '') ? ' WHERE ' : ' AND ';
            switch($varslist->account_status_filter) {
            case 1:
                $where .= 'activite_adh=1';
                break;
            case 2:
                $where .= 'activite_adh=0';
                break;
            }
        }*/

        if ( !$login->isAdmin() ) {
            //members can only view their own contributions
            $where .= ($where == '') ? ' WHERE ' : ' AND ';
            $where .=  'p.' . Adherent::PK . '=' . $login->id;
        } else if ( $_SESSION['filtre_cotis_adh'] != '' ) {
            $where .= ($where == '') ? ' WHERE ' : ' AND ';
            $where .=  'p.' . Adherent::PK . '=' . $_SESSION['filtre_cotis_adh'];
        }

        return $where;
    }

    /**
    * Builds limit clause, for pagination
    *
    * @return string SQL LIMIT clause
    */
    private function _setLimits()
    {
        /*$limits = '';
        return $limits;*/
    }

    /**
    * Get count for current query
    *
    * @return int
    */
    public function getCount()
    {
        return $this->_count;
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
                '[Contributions] Setting property `' . $name . '`',
                PEAR_LOG_DEBUG
            );

            $forbidden = array();
            if ( !in_array($name, $forbidden) ) {
                $rname = '_' . $name;
                switch($name) {
                case 'tri':
                    $allowed_orders = array(
                        self::ORDERBY_DATE,
                        self::ORDERBY_BEGIN_DATE,
                        self::ORDERBY_END_DATE,
                        self::ORDERBY_MEMBER,
                        self::ORDERBY_TYPE,
                        self::ORDERBY_AMOUNT,
                        self::ORDERBY_DURATION
                    );
                    if ( in_array($value, $allowed_orders) ) {
                        $this->orderby = $value;
                    }
                    break;
                default:
                    $this->$rname = $value;
                    break;
                }
            } else {
                $log->log(
                    '[Contributions] Unable to set proprety `' .$name . '`',
                    PEAR_LOG_WARNING
                );
            }
        }
    }

}
?>