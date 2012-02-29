<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Transactions class
 *
 * PHP version 5
 *
 * Copyright © 2011-2012 The Galette Team
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
 * @copyright 2011-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-31
 */

/** @ignore */
require_once 'transaction.class.php';

/**
 * Transactions class for galette
 *
 * @name Transactions
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Transactions extends Galette\Core\Pagination
{
    const TABLE = Transaction::TABLE;
    const PK = Transaction::PK;

    const ORDERBY_DATE = 0;
    const ORDERBY_MEMBER = 1;
    const ORDERBY_AMOUNT = 2;

    private $_count = null;
    private $_filtre_cotis_adh = null;

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
        return self::ORDERBY_DATE;
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
    * Get transactions list
    *
    * @param bool    $as_trans return the results as an array of
    *                          Transaction object.
    * @param array   $fields   field(s) name(s) to get. Should be a string or
    *                          an array. If null, all fields will be returned
    * @param boolean $count    true if we want to count members
    *
    * @return Transaction[]|ResultSet
    */
    public function getTransactionsList(
        $as_trans=false, $fields=null, $count=true
    ) {
        global $zdb, $log;

        try {
            $select = $this->_buildSelect(
                $fields, $count
            );

            $this->setLimits($select);

            $transactions = array();
            if ( $as_trans ) {
                foreach ( $select->query()->fetchAll() as $row ) {
                    $transactions[] = new Transaction($row);
                }
            } else {
                $transactions = $select->query()->fetchAll();
            }
            return $transactions;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot list transactions | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
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
        global $zdb;

        try {
            $fieldsList = ( $fields != null )
                            ? (( !is_array($fields) || count($fields) < 1 ) ? (array)'*'
                            : implode(', ', $fields)) : (array)'*';

            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                array('t' => PREFIX_DB . 'transactions'),
                array(
                    't.trans_date',
                    't.trans_id',
                    't.trans_desc',
                    't.id_adh',
                    't.trans_amount',
                    'a.nom_adh',
                    'a.prenom_adh'
                )
            )->join(
                array('a' => PREFIX_DB . Galette\Entity\Adherent::TABLE, Galette\Entity\Adherent::PK),
                't.' . Galette\Entity\Adherent::PK . '=' . 'a.' . Galette\Entity\Adherent::PK
            );

            $this->_buildWhereClause($select);
            $select->order(self::_buildOrderClause());

            if ( $count ) {
                $this->_proceedCount($select);
            }

            return $select;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot build SELECT clause for transactions | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
    * Count transactions from the query
    *
    * @param Zend_Db_Select $select Original select
    *
    * @return void
    */
    private function _proceedCount($select)
    {
        global $zdb, $log;

        try {
            $countSelect = clone $select;
            $countSelect->reset(Zend_Db_Select::COLUMNS);
            $countSelect->reset(Zend_Db_Select::ORDER);
            $countSelect->columns('count(' . self::PK . ') AS ' . self::PK);
            $str = $select->__toString();
            $result = $countSelect->query()->fetch();

            $k = self::PK;
            $this->_count = $result->$k;
            if ( $this->_count > 0 ) {
                $this->counter = (int)$this->_count;
                $this->countPages();
            }
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot count transactions | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $countSelect->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
    * Builds the order clause
    *
    * @return string SQL ORDER clause
    */
    private function _buildOrderClause()
    {
        $order = array();

        switch ( $this->orderby ) {
        case self::ORDERBY_DATE:
            $order[] = 'trans_date' . ' ' . $this->ordered;
            break;
        case self::ORDERBY_MEMBER:
            $order[] = 'nom_adh' . ' ' . $this->ordered;
            $order[] = 'prenom_adh' . ' ' . $this->ordered;
            break;
        case self::ORDERBY_AMOUNT:
            $order[] = 'trans_amount' . ' ' . $this->ordered;
            break;
        default:
            $order[] = $this->orderby . ' ' . $this->ordered;
            break;
        }

        return $order;
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Zend_Db_Select $select Original select
     *
     * @return string SQL WHERE clause
     */
    private function _buildWhereClause($select)
    {
        global $zdb, $log, $login;

        try {
            /*if ( $this->_start_date_filter != null ) {*/
                /** TODO: initial date format should be i18n
                $d = DateTime::createFromFormat(
                    _T("d/m/Y"),
                    $this->_start_date_filter
                );*/
                /*$d = DateTime::createFromFormat(
                    'd/m/Y',
                    $this->_start_date_filter
                );
                $select->where('date_debut_cotis >= ?', $d->format('Y-m-d'));
            }

            if ( $this->_end_date_filter != null ) {*/
                /** TODO: initial date format should be i18n
                $d = DateTime::createFromFormat(
                    _T("d/m/Y"),
                    $this->_end_date_filter
                );*/
                /*$d = DateTime::createFromFormat(
                    'd/m/Y',
                    $this->_end_date_filter
                );
                $select->where('date_fin_cotis <= ?', $d->format('Y-m-d'));
            }*/

            if ( !$login->isAdmin() && !$login->isStaff() ) {
                //non staff members can only view their own transactions
                $select->where('t.' . Galette\Entity\Adherent::PK . ' = ?', $login->id);
            } else if ( $this->_filtre_cotis_adh != null ) {
                $select->where('t.' . Galette\Entity\Adherent::PK . ' = ?', $this->_filtre_cotis_adh);
            }
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                __METHOD__ . ' | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
        }
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
    * Reinit default parameters
    *
    * @return void
    */
    public function reinit()
    {
        parent::reinit();
        /*$this->_start_date_filter = null;
        $this->_end_date_filter = null;*/
    }

    /**
     * Remove specified transactions
     *
     * @param interger|array $ids Transactions identifiers to delete
     *
     * @return boolean
     */
    public function removeTransactions($ids)
    {
        global $zdb, $log, $hist;

        $list = array();
        if ( is_numeric($ids) ) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if ( is_array($list) ) {
            $res = true;
            try {
                $zdb->db->beginTransaction();
                $select = new Zend_Db_Select($zdb->db);
                $select->from(PREFIX_DB . self::TABLE)
                    ->where(self::PK . ' IN (?)', $list);
                $transactions = $select->query()->fetchAll();
                foreach ( $transactions as $transaction ) {
                    $c = new Transaction($transaction);
                    $res = $c->remove(false);
                    if ( $res === false ) {
                        throw new Exception;
                    }
                }
                $zdb->db->commit();
                $hist->add(
                    "Transactions deleted (" . print_r($list, true) . ')'
                );
            } catch (Exception $e) {
                /** FIXME */
                $zdb->db->rollBack();
                $log->log(
                    'An error occured trying to remove transactions | ' .
                    $e->getMessage(),
                    PEAR_LOG_ERR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            $log->log(
                'Asking to remove transaction, but without providing an array or a single numeric value.',
                PEAR_LOG_WARNING
            );
            return false;
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
            '[Transactions] Getting property `' . $name . '`',
            PEAR_LOG_DEBUG
        );

        if ( in_array($name, $this->pagination_fields) ) {
            return parent::__get($name);
        }/* else {
            $return_ok = array(
                'filtre_cotis_adh',
                'start_date_filter',
                'end_date_filter'
            );
            if (in_array($name, $return_ok)) {
                $name = '_' . $name;
                return $this->$name;
            } else {
                $log->log(
                    '[Transactions] Unable to get proprety `' .$name . '`',
                    PEAR_LOG_WARNING
                );
            }
        }*/
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
                '[Transactions] Setting property `' . $name . '`',
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
                    '[Transactions] Unable to set proprety `' .$name . '`',
                    PEAR_LOG_WARNING
                );
            }
        }
    }

}
?>
