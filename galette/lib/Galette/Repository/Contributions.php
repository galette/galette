<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions class
 *
 * PHP version 5
 *
 * Copyright © 2010-2021 The Galette Team
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
 * @copyright 2010-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-11
 */

namespace Galette\Repository;

use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\History;
use Galette\Entity\Contribution;
use Galette\Entity\Adherent;
use Galette\Entity\Transaction;
use Galette\Entity\ContributionsTypes;
use Galette\Filters\ContributionsList;
use Laminas\Db\Sql\Select;

/**
 * Contributions class for galette
 *
 * @name Contributions
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Contributions
{
    public const TABLE = Contribution::TABLE;
    public const PK = Contribution::PK;

    private $count = null;

    private $sum;

    /**
     * Default constructor
     *
     * @param Db                $zdb     Database
     * @param Login             $login   Login
     * @param ContributionsList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new ContributionsList();
        } else {
            $this->filters = $filters;
        }
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
        $this->filters->from_transaction = $trans_id;
        return $this->getList(true);
    }

    /**
     * Get contributions list
     *
     * @param bool    $as_contrib return the results as an array of
     *                            Contribution object.
     * @param array   $fields     field(s) name(s) to get. Should be a string or
     *                            an array. If null, all fields will be
     *                            returned
     * @param boolean $count      true if we want to count members
     *
     * @return Contribution[]|ResultSet
     */
    public function getList($as_contrib = false, $fields = null, $count = true)
    {
        try {
            $select = $this->buildSelect($fields, $count);

            $this->filters->setLimits($select);

            $contributions = array();
            $results = $this->zdb->execute($select);
            if ($as_contrib) {
                foreach ($results as $row) {
                    $contributions[] = new Contribution($this->zdb, $this->login, $row);
                }
            } else {
                $contributions = $results;
            }
            return $contributions;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
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
    private function buildSelect($fields, $count = false)
    {
        try {
            $fieldsList = ($fields != null)
                            ? ((!is_array($fields) || count($fields) < 1) ? (array)'*'
                            : implode(', ', $fields)) : (array)'*';

            $select = $this->zdb->select(self::TABLE, 'a');
            $select->columns($fieldsList);

            $select->join(
                array('p' => PREFIX_DB . Adherent::TABLE),
                'a.' . Adherent::PK . '= p.' . Adherent::PK
            );

            $this->buildWhereClause($select);
            $select->order(self::buildOrderClause());

            $this->calculateSum($select);

            if ($count) {
                $this->proceedCount($select);
            }

            return $select;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot build SELECT clause for contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Count contributions from the query
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function proceedCount(Select $select)
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::JOINS);
            $countSelect->reset($countSelect::ORDER);
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
                $this->filters->setCounter($this->count);
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Calculate sum of all selected contributions
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function calculateSum(Select $select)
    {
        try {
            $sumSelect = clone $select;
            $sumSelect->reset($sumSelect::COLUMNS);
            $sumSelect->reset($sumSelect::JOINS);
            $sumSelect->reset($sumSelect::ORDER);
            $sumSelect->columns(
                array(
                    'contribsum' => new Expression('SUM(montant_cotis)')
                )
            );

            $results = $this->zdb->execute($sumSelect);
            $result = $results->current();

            $this->sum = round($result->contribsum, 2);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot calculate contributions sum | ' . $e->getMessage(),
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
    private function buildOrderClause()
    {
        $order = array();

        switch ($this->filters->orderby) {
            case ContributionsList::ORDERBY_ID:
                $order[] = Contribution::PK . ' ' . $this->filters->ordered;
                break;
            case ContributionsList::ORDERBY_DATE:
                $order[] = 'date_enreg ' . $this->filters->ordered;
                break;
            case ContributionsList::ORDERBY_BEGIN_DATE:
                $order[] = 'date_debut_cotis ' . $this->filters->ordered;
                break;
            case ContributionsList::ORDERBY_END_DATE:
                $order[] = 'date_fin_cotis ' . $this->filters->ordered;
                break;
            case ContributionsList::ORDERBY_MEMBER:
                $order[] = 'nom_adh ' . $this->filters->ordered;
                $order[] = 'prenom_adh ' . $this->filters->ordered;
                break;
            case ContributionsList::ORDERBY_TYPE:
                $order[] = ContributionsTypes::PK;
                break;
            case ContributionsList::ORDERBY_AMOUNT:
                $order[] = 'montant_cotis ' . $this->filters->ordered;
                break;
            /*
            Hum... I really do not know how to sort a query with a value that
            is calculated code side :/
            case ContributionsList::ORDERBY_DURATION:
                break;*/
            case ContributionsList::ORDERBY_PAYMENT_TYPE:
                $order[] = 'type_paiement_cotis ' . $this->filters->ordered;
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
    private function buildWhereClause(Select $select)
    {
        $field = 'date_debut_cotis';

        switch ($this->filters->date_field) {
            case ContributionsList::DATE_RECORD:
                $field = 'date_enreg';
                break;
            case ContributionsList::DATE_END:
                $field = 'date_fin_cotis';
                break;
            case ContributionsList::DATE_BEGIN:
            default:
                $field = 'date_debut_cotis';
                break;
        }

        try {
            if ($this->filters->start_date_filter != null) {
                $d = new \DateTime($this->filters->rstart_date_filter);
                $select->where->greaterThanOrEqualTo(
                    $field,
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->end_date_filter != null) {
                $d = new \DateTime($this->filters->rend_date_filter);
                $select->where->lessThanOrEqualTo(
                    $field,
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->payment_type_filter !== null) {
                $select->where->equalTo(
                    'type_paiement_cotis',
                    $this->filters->payment_type_filter
                );
            }

            if ($this->filters->from_transaction !== false) {
                $select->where->equalTo(
                    Transaction::PK,
                    $this->filters->from_transaction
                );
            }

            if ($this->filters->max_amount !== null && is_int($this->filters->max_amount)) {
                $select->where(
                    '(montant_cotis <= ' . $this->filters->max_amount .
                    ' OR montant_cotis IS NULL)'
                );
            }

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                //non staff members can only view their own contributions
                $select->where(
                    array(
                        'a.' . Adherent::PK => $this->login->id
                    )
                );
            } elseif ($this->filters->filtre_cotis_adh != null) {
                $select->where(
                    'a.' . Adherent::PK . ' = ' . $this->filters->filtre_cotis_adh
                );
            }
            if ($this->filters->filtre_transactions === true) {
                $select->where('a.trans_id IS NULL');
            }
        } catch (Throwable $e) {
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
     * Get sum
     *
     * @return int
     */
    public function getSum()
    {
        return $this->sum;
    }

    /**
     * Remove specified contributions
     *
     * @param integer|array $ids         Contributions identifiers to delete
     * @param History       $hist        History
     * @param boolean       $transaction True to begin a database transaction
     *
     * @return boolean
     */
    public function remove($ids, History $hist, $transaction = true)
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
                if ($transaction) {
                    $this->zdb->connection->beginTransaction();
                }
                $select = $this->zdb->select(self::TABLE);
                $select->where->in(self::PK, $list);
                $contributions = $this->zdb->execute($select);
                foreach ($contributions as $contribution) {
                    $c = new Contribution($this->zdb, $this->login, $contribution);
                    $res = $c->remove(false);
                    if ($res === false) {
                        throw new \Exception();
                    }
                }
                if ($transaction) {
                    $this->zdb->connection->commit();
                }
                $hist->add(
                    str_replace(
                        '%list',
                        print_r($list, true),
                        _T("Contributions deleted (%list)")
                    )
                );
                return true;
            } catch (Throwable $e) {
                if ($transaction) {
                    $this->zdb->connection->rollBack();
                }
                Analog::log(
                    'An error occurred trying to remove contributions | ' .
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
}
