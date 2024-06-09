<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Repository;

use ArrayObject;
use Laminas\Db\Adapter\Driver\Pdo\Result;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Select;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Entity\Transaction;
use Galette\Entity\Adherent;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\History;
use Galette\Filters\TransactionsList;

/**
 * Transactions class for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Transactions
{
    public const TABLE = Transaction::TABLE;
    public const PK = Transaction::PK;

    private int $count = 0;
    private Db $zdb;
    private Login $login;
    private TransactionsList $filters;

    /**
     * Default constructor
     *
     * @param Db                $zdb     Database
     * @param Login             $login   Login
     * @param ?TransactionsList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, ?TransactionsList $filters = null)
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
     * @param bool           $as_trans return the results as an array of
     *                                 Transaction object.
     * @param ?array<string> $fields   field(s) name(s) to get. Should be a string or
     *                                 an array. If null, all fields will be returned
     *
     * @return array<int, Transaction>|ResultSet|Result
     */
    public function getList(bool $as_trans = false, ?array $fields = null): array|ResultSet|Result
    {
        try {
            $select = $this->buildSelect($fields);
            $this->filters->setLimits($select);

            $transactions = array();
            $results = $this->zdb->execute($select);
            if ($as_trans) {
                foreach ($results as $row) {
                    /** @var ArrayObject<string, int|string> $row */
                    $transactions[] = new Transaction($this->zdb, $this->login, $row);
                }
            } else {
                $transactions = $results;
            }
            return $transactions;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list transactions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the SELECT statement
     *
     * @param ?array<string> $fields fields list to retrieve
     *
     * @return Select SELECT statement
     */
    private function buildSelect(?array $fields): Select
    {
        try {
            $select = $this->zdb->select(self::TABLE, 't');
            if ($fields === null || !count($fields)) {
                $fields = array(
                    'trans_date',
                    'trans_id',
                    'trans_desc',
                    'id_adh',
                    'trans_amount',
                    'type_paiement_trans'
                );
            }
            $select->columns($fields)->join(
                array('a' => PREFIX_DB . Adherent::TABLE),
                't.' . Adherent::PK . '=' . 'a.' . Adherent::PK,
                array('nom_adh', 'prenom_adh')
            );

            $this->buildWhereClause($select);
            $select->order(self::buildOrderClause());

            $this->proceedCount($select);

            return $select;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot build SELECT clause for transactions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count transactions from the query
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function proceedCount(Select $select): void
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
            $this->count = (int)$result->$k;
            $this->filters->setCounter($this->count);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count transactions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(): array
    {
        $order = array();

        switch ($this->filters->orderby) {
            case TransactionsList::ORDERBY_ID:
                $order[] = Transaction::PK . ' ' . $this->filters->ordered;
                break;
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
            case TransactionsList::ORDERBY_PAYMENT_TYPE:
                $order[] = 'type_paiement_trans' . ' ' . $this->filters->ordered;
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
     * @return void
     */
    private function buildWhereClause(Select $select): void
    {
        try {
            if ($this->filters->start_date_filter != null) {
                $d = new \DateTime($this->filters->rstart_date_filter);
                $select->where->greaterThanOrEqualTo(
                    'trans_date',
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->end_date_filter != null) {
                $d = new \DateTime($this->filters->rend_date_filter);
                $select->where->lessThanOrEqualTo(
                    'trans_date',
                    $d->format('Y-m-d')
                );
            }

            $member_clause = null;
            if ($this->filters->filtre_cotis_adh != null) {
                $member_clause = [$this->filters->filtre_cotis_adh];
                if (!$this->login->isAdmin() && !$this->login->isStaff() && $this->filters->filtre_cotis_adh != $this->login->id) {
                    $member = new Adherent(
                        $this->zdb,
                        (int)$this->filters->filtre_cotis_adh,
                        [
                            'picture' => false,
                            'groups' => false,
                            'dues' => false,
                            'parent' => true
                        ]
                    );
                    if (
                        !$member->hasParent() ||
                        $member->parent->id != $this->login->id
                    ) {
                        Analog::log(
                            'Trying to display transactions for member #' . $member->id .
                            ' without appropriate ACLs',
                            Analog::WARNING
                        );
                        $this->filters->filtre_cotis_adh = $this->login->id;
                        $member_clause = [$this->login->id];
                    }
                }
            } elseif ($this->filters->filtre_cotis_children !== false) {
                $member_clause = [$this->login->id];
                $member = new Adherent(
                    $this->zdb,
                    (int)$this->filters->filtre_cotis_children,
                    [
                        'picture'   => false,
                        'groups'    => false,
                        'dues'      => false,
                        'children'  => true
                    ]
                );
                foreach ($member->children as $child) {
                    $member_clause[] = $child->id;
                }
            } elseif (!$this->login->isAdmin() && !$this->login->isStaff()) {
                $member_clause = $this->login->id;
            }

            if ($member_clause !== null) {
                $select->where(
                    array(
                        't.' . Adherent::PK => $member_clause
                    )
                );
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
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Remove specified transactions
     *
     * @param array<int>|integer $ids  Transactions identifiers to delete
     * @param History            $hist History
     *
     * @return boolean
     */
    public function remove(array|int $ids, History $hist): bool
    {
        $list = array();
        if (is_numeric($ids)) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        try {
            $this->zdb->connection->beginTransaction();

            $select = $this->zdb->select(self::TABLE);
            $select->where->in(self::PK, $list);

            $results = $this->zdb->execute($select);
            foreach ($results as $transaction) {
                /** @var ArrayObject<string, int|string> $transaction */
                $c = new Transaction($this->zdb, $this->login, $transaction);
                $res = $c->remove($hist, false);
                if ($res === false) {
                    throw new \Exception();
                }
            }
            $this->zdb->connection->commit();
            $hist->add(
                "Transactions deleted (" . print_r($list, true) . ')'
            );
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'An error occurred trying to remove transactions | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }
}
