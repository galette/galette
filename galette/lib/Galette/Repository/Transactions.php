<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Transactions class
 *
 * PHP version 5
 *
 * Copyright © 2011-2014 The Galette Team
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
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-31
 */

namespace Galette\Repository;

use Analog\Analog;
use Zend\Db\Sql\Expression;
use Galette\Core\Pagination;
use Galette\Entity\Transaction;
use Galette\Entity\Adherent;
use Galette\Core\Db;
use Galette\Core\Login;

/**
 * Transactions class for galette
 *
 * @name Transactions
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Transactions extends Pagination
{
    const TABLE = Transaction::TABLE;
    const PK = Transaction::PK;

    const ORDERBY_DATE = 0;
    const ORDERBY_MEMBER = 1;
    const ORDERBY_AMOUNT = 2;

    private $_count = null;
    private $_start_date_filter = null;
    private $_end_date_filter = null;
    private $_filtre_cotis_adh = null;
    private $zdb;
    private $login;

    /**
     * Default constructor
     *
     * @param Db    $zdb   Database
     * @param Login $login Login
     */
    public function __construct(Db $zdb, Login $login)
    {
        $this->zdb = $zdb;
        $this->login = $login;
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
     * Return the default direction for ordering
     *
     * @return string ASC or DESC
     */
    protected function getDefaultDirection()
    {
        return self::ORDER_DESC;
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
    public function getTransactionsList($as_trans = false, $fields = null, $count = true)
    {
        try {
            $select = $this->_buildSelect($fields, $count);
            $this->setLimits($select);

            $transactions = array();
            $results = $this->zdb->execute($select);
            if ($as_trans) {
                foreach ($results as $row) {
                    $transactions[] = new Transaction($this->zdb, $row);
                }
            } else {
                $transactions = $results;
            }
            return $transactions;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list transactions | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Builds the SELECT statement
     *
     * @param array $fields fields list to retrieve
     * @param bool  $count  true if we want to count members
     *                      (not applicable from static calls), defaults to false
     *
     * @return string SELECT statement
     */
    private function _buildSelect($fields, $count = false)
    {
        try {
            $fieldsList = ( $fields != null )
                            ? (( !is_array($fields) || count($fields) < 1 ) ? (array)'*'
                            : implode(', ', $fields)) : (array)'*';

            $select = $this->zdb->select(self::TABLE, 't');
            $select->columns(
                array(
                    'trans_date',
                    'trans_id',
                    'trans_desc',
                    'id_adh',
                    'trans_amount'
                )
            )->join(
                array('a' => PREFIX_DB . Adherent::TABLE),
                't.' . Adherent::PK . '=' . 'a.' . Adherent::PK,
                array('nom_adh', 'prenom_adh')
            );

            $this->_buildWhereClause($select);
            $select->order(self::_buildOrderClause());

            if ( $count ) {
                $this->_proceedCount($select);
            }

            return $select;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot build SELECT clause for transactions | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Count transactions from the query
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function _proceedCount($select)
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->reset($countSelect::JOINS);
            $countSelect->columns(
                array(
                    self::PK => new Expression('COUNT(' . self::PK . ')')
                )
            );

            $results = $this->zdb->execute($countSelect);
            $result = $results->current();

            $k = self::PK;
            $this->_count = $result->$k;
            if ( $this->_count > 0 ) {
                $this->counter = (int)$this->_count;
                $this->countPages();
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count transactions | ' . $e->getMessage(),
                Analog::WARNING
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
     * @param Select $select Original select
     *
     * @return string SQL WHERE clause
     */
    private function _buildWhereClause($select)
    {
        try {
            if ($this->_start_date_filter != null) {
                $d = new \DateTime($this->_start_date_filter);
                $select->where->greaterThanOrEqualTo(
                    'trans_date',
                    $d->format('Y-m-d')
                );
            }

            if ($this->_end_date_filter != null) {
                $d = new \DateTime($this->_end_date_filter);
                $select->where->lessThanOrEqualTo(
                    'trans_date',
                    $d->format('Y-m-d')
                );
            }

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                //non staff members can only view their own transactions
                $select->where('t.' . Adherent::PK . ' = ' . $this->login->id);
            } elseif ($this->_filtre_cotis_adh != null) {
                $select->where(
                    't.' . Adherent::PK . ' = ' . $this->_filtre_cotis_adh
                );
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
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
        $this->_start_date_filter = null;
        $this->_end_date_filter = null;
    }

    /**
     * Remove specified transactions
     *
     * @param interger|array $ids  Transactions identifiers to delete
     * @param History        $hist History
     *
     * @return boolean
     */
    public function removeTransactions($ids, History $hist)
    {
        $list = array();
        if (is_numeric($ids)) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if (is_array($list)) {
            $res = true;
            try {
                $this->zdb->connection->beginTransaction();

                $select = $this->zdb->select(self::TABLE);
                $select->where->in(self::PK, $list);

                $results = $this->zdb->execute($select);
                foreach ($results as $transaction) {
                    $c = new Transaction($this->zdb, $transaction);
                    $res = $c->remove(false);
                    if ($res === false) {
                        throw new \Exception;
                    }
                }
                $this->zdb->connection->commit();
                $hist->add(
                    "Transactions deleted (" . print_r($list, true) . ')'
                );
            } catch (\Exception $e) {
                $this->zdb->connection->rollBack();
                Analog::log(
                    'An error occured trying to remove transactions | ' .
                    $e->getMessage(),
                    Analog::ERROR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            Analog::log(
                'Asking to remove transaction, but without providing ' .
                'an array or a single numeric value.',
                Analog::WARNING
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

        Analog::log(
            '[Transactions] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if ( in_array($name, $this->pagination_fields) ) {
            return parent::__get($name);
        } else {
            $return_ok = array(
                'filtre_cotis_adh',
                'start_date_filter',
                'end_date_filter'
            );
            if (in_array($name, $return_ok)) {
                $name = '_' . $name;
                return $this->$name;
            } else {
                Analog::log(
                    '[Transactions] Unable to get proprety `' .$name . '`',
                    Analog::WARNING
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
        if ( in_array($name, $this->pagination_fields) ) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[Transactions] Setting property `' . $name . '`',
                Analog::DEBUG
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
                case 'start_date_filter':
                case 'end_date_filter':
                    try {
                        if ( $value !== '' ) {
                            $y = \DateTime::createFromFormat(_T("Y"), $value);
                            if ( $y !== false ) {
                                $month = 1;
                                $day = 1;
                                if ( $name === 'end_date_filter' ) {
                                    $month = 12;
                                    $day = 31;
                                }
                                $y->setDate(
                                    $y->format('Y'),
                                    $month,
                                    $day
                                );
                                $this->$rname = $y->format('Y-m-d');
                            }

                            $ym = \DateTime::createFromFormat(_T("Y-m"), $value);
                            if ( $y === false && $ym  !== false ) {
                                $day = 1;
                                if ( $name === 'end_date_filter' ) {
                                    $day = $ym->format('t');
                                }
                                $ym->setDate(
                                    $ym->format('Y'),
                                    $ym->format('m'),
                                    $day
                                );
                                $this->$rname = $ym->format('Y-m-d');
                            }

                            $d = \DateTime::createFromFormat(_T("Y-m-d"), $value);
                            if ( $y === false && $ym  === false && $d !== false ) {
                                $this->$rname = $d->format('Y-m-d');
                            }

                            if ( $y === false && $ym === false && $d === false ) {
                                $formats = array(
                                    _T("Y"),
                                    _T("Y-m"),
                                    _T("Y-m-d"),
                                );

                                $field = null;
                                if ($name === 'start_date_filter' ) {
                                    $field = _T("start date filter");
                                }
                                if ($name === 'end_date_filter' ) {
                                    $field = _T("end date filter");
                                }

                                throw new \Exception(
                                    str_replace(
                                        array('%field', '%format'),
                                        array(
                                            $field,
                                            implode(', ', $formats)
                                        ),
                                        _T("Unknown date format for %field.<br/>Know formats are: %formats")
                                    )
                                );
                            }
                        } else {
                            $this->$rname = null;
                        }
                    } catch (\Exception $e) {
                        Analog::log(
                            'Wrong date format. field: ' . $key .
                            ', value: ' . $value . ', expected fmt: ' .
                            _T("Y-m-d") . ' | ' . $e->getMessage(),
                            Analog::INFO
                        );
                        throw $e;
                    }
                    break;
                default:
                    $this->$rname = $value;
                    break;
                }
            } else {
                Analog::log(
                    '[Transactions] Unable to set proprety `' .$name . '`',
                    Analog::WARNING
                );
            }
        }
    }

}
