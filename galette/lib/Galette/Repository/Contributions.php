<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions class
 *
 * PHP version 5
 *
 * Copyright © 2010-2013 The Galette Team
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
 * @copyright 2010-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-11
 */

namespace Galette\Repository;

use Analog\Analog as Analog;
use Galette\Core\Pagination as Pagination;
use Galette\Entity\Contribution as Contribution;
use Galette\Entity\Adherent as Adherent;
use Galette\Entity\Transaction as Transaction;
use Galette\Entity\ContributionsTypes as ContributionsTypes;

/**
 * Contributions class for galette
 *
 * @name Contributions
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Contributions extends Pagination
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
    const ORDERBY_PAYMENT_TYPE = 7;

    private $_count = null;
    private $_start_date_filter = null;
    private $_end_date_filter = null;
    private $_payment_type_filter = null;
    private $_filtre_cotis_adh = null;
    private $_filtre_transactions = null;

    private $_from_transaction = false;
    private $_max_amount = null;
    private $_sum;

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
     * Return the default direction for ordering
     *
     * @return string ASC or DESC
     */
    protected function getDefaultDirection()
    {
        return self::ORDER_DESC;
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
     * Get contributions list for a specific transaction
     *
     * @param int $trans_id Transaction identifier
     *
     * @return Contribution[]
     */
    public function getListFromTransaction($trans_id)
    {
        $this->_from_transaction = $trans_id;
        return $this->getContributionsList(true);
    }

    /**
    * Get contributions list
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
        global $zdb;

        try {
            $select = $this->_buildSelect(
                $fields, $count
            );

            $this->setLimits($select);

            $contributions = array();
            if ( $as_contrib ) {
                $res = $select->query()->fetchAll();
                foreach ( $res as $row ) {
                    $contributions[] = new Contribution($row);
                }
            } else {
                $contributions = $select->query()->fetchAll();
            }
            return $contributions;
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Cannot list contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
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

            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                array('a' => PREFIX_DB . self::TABLE),
                $fieldsList
            );

            $select->join(
                array('p' => PREFIX_DB . Adherent::TABLE, Adherent::PK),
                'a.' . Adherent::PK . '=' . 'p.' . Adherent::PK
            );

            $this->_buildWhereClause($select);
            $select->order(self::_buildOrderClause());

            $this->_calculateSum($select);

            if ( $count ) {
                $this->_proceedCount($select);
            }

            return $select;
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Cannot build SELECT clause for contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Count contributions from the query
    *
    * @param Zend_Db_Select $select Original select
    *
    * @return void
    */
    private function _proceedCount($select)
    {
        global $zdb;

        try {
            $countSelect = clone $select;
            $countSelect->reset(\Zend_Db_Select::COLUMNS);
            $countSelect->reset(\Zend_Db_Select::ORDER);
            $countSelect->columns('count(' . self::PK . ') AS ' . self::PK);

            $result = $countSelect->query()->fetch();

            $k = self::PK;
            $this->_count = $result->$k;
            if ( $this->_count > 0 ) {
                $this->counter = (int)$this->_count;
                $this->countPages();
            }
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Cannot count contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $countSelect->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Calculate sum of all selected contributions
    *
    * @param Zend_Db_Select $select Original select
    *
    * @return void
    */
    private function _calculateSum($select)
    {
        global $zdb;

        try {
            $sumSelect = clone $select;
            $sumSelect->reset(\Zend_Db_Select::COLUMNS);
            $sumSelect->reset(\Zend_Db_Select::ORDER);
            $sumSelect->columns('SUM(montant_cotis) AS contribsum');

            $result = $sumSelect->query()->fetch();

            $this->_sum = round($result->contribsum, 2);
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Cannot calculate contributions sum | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $sumSelect->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
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
            $order[] = 'date_enreg' . ' ' . $this->ordered;
            break;
        case self::ORDERBY_BEGIN_DATE:
            $order[] = 'date_debut_cotis' . ' ' . $this->ordered;
            break;
        case self::ORDERBY_END_DATE:
            $order[] = 'date_fin_cotis' . ' ' . $this->ordered;
            break;
        case self::ORDERBY_MEMBER:
            $order[] = 'nom_adh' . ' ' . $this->ordered;
            $order[] = 'prenom_adh' . ' ' . $this->ordered;
            break;
        case self::ORDERBY_TYPE:
            $order[] = ContributionsTypes::PK;
            break;
        case self::ORDERBY_AMOUNT:
            $order[] = 'montant_cotis' . ' ' . $this->ordered;
            break;
        /*
        Hum... I really do not know how to sort a query with a value that
        is calculated code side :/
        case self::ORDERBY_DURATION:
            break;*/
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
        global $zdb, $login;

        try {
            if ( $this->_start_date_filter != null ) {
                $d = new \DateTime($this->_start_date_filter);
                $select->where('date_debut_cotis >= ?', $d->format('Y-m-d'));
            }

            if ( $this->_end_date_filter != null ) {
                $d = new \DateTime($this->_end_date_filter);
                $select->where('date_debut_cotis <= ?', $d->format('Y-m-d'));
            }

            if ( $this->_payment_type_filter != null ) {
                $select->where('type_paiement_cotis = ?', $this->_payment_type_filter);
            }

            if ( $this->_from_transaction !== false ) {
                $select->where(
                    Transaction::PK . ' = ?',
                    $this->_from_transaction
                );
            }

            if ( $this->_max_amount !== null && is_int($this->_max_amount)) {
                $select->where(
                    '(montant_cotis <= ' . $this->_max_amount .
                    ' OR montant_cotis IS NULL)'
                );
            }
            $sql = $select->__toString();

            if ( !$login->isAdmin() && !$login->isStaff() ) {
                //non staff members can only view their own contributions
                $select->where('p.' . Adherent::PK . ' = ?', $login->id);
            } else if ( $this->_filtre_cotis_adh != null ) {
                $select->where('p.' . Adherent::PK . ' = ?', $this->_filtre_cotis_adh);
            }
            if ( $this->_filtre_transactions === true ) {
                $select->where('a.trans_id ?', new \Zend_Db_Expr('IS NULL'));
            }
            $qry = $select->__toString();
            Analog::log(
                "Query was:\n" . $qry,
                Analog::DEBUG
            );
        } catch (\Exception $e) {
            /** TODO */
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
        $this->_payment_type_filter = null;
        $this->_filtre_transactions = null;
        $this->_count = null;
        $this->_filtre_cotis_adh = null;
        $this->_from_transaction = false;
        $this->_max_amount = null;
        $this->_sum = null;
    }

    /**
     * Remove specified contributions
     *
     * @param integer|array $ids         Contributions identifiers to delete
     * @param boolean       $transaction True to begin a database transaction
     *
     * @return boolean
     */
    public function removeContributions($ids, $transaction = true)
    {
        global $zdb, $hist;

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
                if ( $transaction ) {
                    $zdb->db->beginTransaction();
                }
                $select = new \Zend_Db_Select($zdb->db);
                $select->from(PREFIX_DB . self::TABLE)
                    ->where(self::PK . ' IN (?)', $list);
                $contributions = $select->query()->fetchAll();
                foreach ( $contributions as $contribution ) {
                    $c = new Contribution($contribution);
                    $res = $c->remove(false);
                    if ( $res === false ) {
                        throw new \Exception;
                    }
                }
                if ( $transaction ) {
                    $zdb->db->commit();
                }
                $hist->add(
                    str_replace(
                        '%list',
                        print_r($list, true),
                        _T("Contributions deleted (%list)")
                    )
                );
            } catch (\Exception $e) {
                /** FIXME */
                if ( $transaction ) {
                    $zdb->db->rollBack();
                }
                Analog::log(
                    'An error occured trying to remove contributions | ' .
                    $e->getMessage(),
                    Analog::ERROR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            Analog::log(
                'Asking to remove contribution, but without providing an array or a single numeric value.',
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
            '[Contributions] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if ( in_array($name, $this->pagination_fields) ) {
            return parent::__get($name);
        } else {
            $return_ok = array(
                'filtre_cotis_adh',
                'start_date_filter',
                'end_date_filter',
                'payment_type_filter',
                'sum',
                'max_amount'
            );
            if (in_array($name, $return_ok)) {
                $rname = '_' . $name;

                switch ( $name ) {
                case 'start_date_filter':
                case 'end_date_filter':
                    if ( $this->$rname !== null ) {
                        $d = new \DateTime($this->$rname);
                        return $d->format(_T("Y-m-d"));
                    } else {
                        return null;
                    }
                    break;
                default:
                    return $this->$rname;
                    break;
                }
            } else {
                Analog::log(
                    '[Contributions] Unable to get proprety `' .$name . '`',
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
                '[Contributions] Setting property `' . $name . '`',
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
                    '[Contributions] Unable to set proprety `' .$name . '`',
                    Analog::WARNING
                );
            }
        }
    }

}
