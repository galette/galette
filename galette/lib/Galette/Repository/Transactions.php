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
use Galette\Entity\Transaction;
use Galette\Entity\Adherent;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\History;
use Galette\Filters\TransactionsList;

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
class Transactions
{
    const TABLE = Transaction::TABLE;
    const PK = Transaction::PK;

    private $count = null;
    private $zdb;
    private $login;
    private $filters;

    /**
     * Default constructor
     *
     * @param Db               $zdb     Database
     * @param Login            $login   Login
     * @param TransactionsList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new TransactionsList();
        } else {
            $this->filters = $filters;
        }
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
    public function getList($as_trans = false, $fields = null, $count = true)
    {
        try {
            $select = $this->_buildSelect($fields, $count);
            $this->filters->setLimit($select);

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

            if ($count) {
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
            $this->count = $result->$k;
            if ($this->count > 0) {
                $this->counter = (int)$this->count;
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

        switch ($this->filters->orderby) {
            case TransactionsList::ORDERBY_DATE:
                $order[] = 'trans_date' . ' ' . $this->filters->ordered;
                break;
            case TransactionsList::ORDERBY_MEMBER:
                $order[] = 'nom_adh' . ' ' . $this->filters->ordered;
                $order[] = 'prenom_adh' . ' ' . $this->filters->ordered;
                break;
            case TransactionsList::ORDERBY_AMOUNT:
                $order[] = 'trans_amount' . ' ' . $this->filters->ordered;
                break;
            default:
                $order[] = $this->filters->orderby . ' ' . $this->filters->ordered;
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
            if ($this->filters->start_date_filter != null) {
                $d = new \DateTime($this->filters->start_date_filter);
                $select->where->greaterThanOrEqualTo(
                    'trans_date',
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->end_date_filter != null) {
                $d = new \DateTime($this->filters->end_date_filter);
                $select->where->lessThanOrEqualTo(
                    'trans_date',
                    $d->format('Y-m-d')
                );
            }

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                //non staff members can only view their own transactions
                $select->where('t.' . Adherent::PK . ' = ' . $this->login->id);
            } elseif ($this->filters->filtre_cotis_adh != null) {
                $select->where(
                    't.' . Adherent::PK . ' = ' . $this->filters->filtre_cotis_adh
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
        return $this->count;
    }

    /**
     * Remove specified transactions
     *
     * @param interger|array $ids  Transactions identifiers to delete
     * @param History        $hist History
     *
     * @return boolean
     */
    public function remove($ids, History $hist)
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
                return true;
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
}
