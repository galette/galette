<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

use Galette\Entity\ScheduledPayment;
use Galette\Filters\ScheduledPaymentsList;
use Laminas\Db\ResultSet\ResultSet;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\History;
use Galette\Entity\Contribution;
use Galette\Entity\Adherent;
use Laminas\Db\Sql\Select;

/**
 * Scheduled payments class for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ScheduledPayments
{
    public const TABLE = ScheduledPayment::TABLE;
    public const PK = ScheduledPayment::PK;

    private ScheduledPaymentsList $filters;
    private int $count = 0;

    private Db $zdb;
    private Login $login;
    private float $sum = 0;
    /** @var array<int> */
    private array $current_selection;

    /**
     * Default constructor
     *
     * @param Db                     $zdb     Database
     * @param Login                  $login   Login
     * @param ?ScheduledPaymentsList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, ?ScheduledPaymentsList $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new ScheduledPaymentsList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get scheduled payments list for a specific contribution
     *
     * @param int $contrib_id Contribution identifier
     *
     * @return ScheduledPayment[]
     */
    public function getListFromContribution(int $contrib_id): array
    {
        $this->filters->from_contribution = $contrib_id;
        /** @phpstan-ignore-next-line */
        return $this->getList(true);
    }

    /**
     * Get scheduled payments list for a specific contribution
     *
     * @param array<int>     $ids       an array of members id that has been selected
     * @param bool           $as_object return the results as an array of
     * @param ?array<string> $fields    field(s) name(s) to get. Should be a string or
     *                                  an array. If null, all fields will be returned
     *
     * @return array<int, Contribution>|false
     */
    public function getArrayList(array $ids, bool $as_object = false, ?array $fields = null): array|false
    {
        if (count($ids) < 1) {
            Analog::log('No scheduled payment selected.', Analog::INFO);
            return false;
        }

        $this->current_selection = $ids;
        $list = $this->getList($as_object, $fields);
        $array_list = [];
        foreach ($list as $entry) {
            $array_list[] = $entry;
        }
        return $array_list;
    }

    /**
     * Get scheduled payments list
     *
     * @param bool           $as_object return the results as an array of
     *                                  ScheduledPayment object.
     * @param ?array<string> $fields    field(s) name(s) to get. Should be a string or
     *                                  an array. If null, all fields will be returned
     *
     * @return array<int, Contribution>|ResultSet
     */
    public function getList(bool $as_object = true, ?array $fields = null): array|ResultSet
    {
        try {
            $select = $this->buildSelect($fields);

            $this->filters->setLimits($select);

            $scheduleds = [];
            $results = $this->zdb->execute($select);
            if ($as_object) {
                foreach ($results as $row) {
                    $scheduleds[] = new ScheduledPayment($this->zdb, $row);
                }
            } else {
                $scheduleds = $results;
            }
            return $scheduleds;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list scheduled payments | ' . $e->getMessage(),
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
            $fieldsList = ['*'];
            if (is_array($fields) && count($fields)) {
                $fieldsList = $fields;
            }

            $select = $this->zdb->select(self::TABLE, 's');
            $select->columns($fieldsList);

            $select->join(
                ['c' => PREFIX_DB . Contribution::TABLE],
                's.' . Contribution::PK . '= c.' . Contribution::PK,
                []
            );

            $select->join(
                ['a' => PREFIX_DB . Adherent::TABLE],
                'c.' . Adherent::PK . '= a.' . Adherent::PK,
                []
            );

            $this->buildWhereClause($select);
            $select->order(self::buildOrderClause());

            $this->calculateSum($select);

            $this->proceedCount($select);

            return $select;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot build SELECT clause for scheduled payments | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count scheduled payments from the query
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
            $countSelect->columns(
                [
                    self::PK => new Expression('COUNT(' . self::PK . ')')
                ]
            );

            $results = $this->zdb->execute($countSelect);
            $result = $results->current();

            $k = self::PK;
            $this->count = (int)$result->$k;
            $this->filters->setCounter($this->count);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count scheduled payments | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Calculate sum of all selected scheduled payments
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function calculateSum(Select $select): void
    {
        try {
            $sumSelect = clone $select;
            $sumSelect->reset($sumSelect::COLUMNS);
            $sumSelect->reset($sumSelect::ORDER);
            $sumSelect->columns(
                [
                    'scheduledsum' => new Expression('SUM(amount)')
                ]
            );

            $results = $this->zdb->execute($sumSelect);
            $result = $results->current();
            if ($result->scheduledsum) {
                $this->sum = round((float)$result->scheduledsum, 2);
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot calculate scheduled payments sum | ' . $e->getMessage(),
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
        $order = [];

        switch ($this->filters->orderby) {
            case ScheduledPaymentsList::ORDERBY_ID:
                $order[] = ScheduledPayment::PK . ' ' . $this->filters->ordered;
                break;
            case ScheduledPaymentsList::ORDERBY_MEMBER:
                $order[] = 'a.nom_adh ' . $this->filters->getDirection();
                $order[] = 'a.prenom_adh ' . $this->filters->getDirection();
                break;
            case ScheduledPaymentsList::ORDERBY_DATE:
                $order[] = 'creation_date ' . $this->filters->ordered;
                break;
            case ScheduledPaymentsList::ORDERBY_SCHEDULED_DATE:
                $order[] = 'scheduled_date ' . $this->filters->ordered;
                break;
            case ScheduledPaymentsList::ORDERBY_CONTRIBUTION:
                $order[] = Contribution::PK . ' ' . $this->filters->ordered;
                break;
            case ScheduledPaymentsList::ORDERBY_AMOUNT:
                $order[] = 'amount ' . $this->filters->ordered;
                break;
            case ScheduledPaymentsList::ORDERBY_PAYMENT_TYPE:
                $order[] = 'id_paymenttype ' . $this->filters->ordered;
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
        switch ($this->filters->date_field) {
            case ScheduledPaymentsList::DATE_RECORD:
                $field = 'creation_date';
                break;
            case ScheduledPaymentsList::DATE_SCHEDULED:
            default:
                $field = 'scheduled_date';
                break;
        }

        if (isset($this->current_selection)) {
            $select->where->in('s.' . self::PK, $this->current_selection);
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
                    'id_paymenttype',
                    $this->filters->payment_type_filter
                );
            }

            if ($this->filters->from_contribution !== false) {
                $select->where->equalTo(
                    'c.' . Contribution::PK,
                    $this->filters->from_contribution
                );
            }

            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                $select->where(
                    [
                        'a.' . Adherent::PK => $this->login->id
                    ]
                );
            }
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
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
     * Get sum
     *
     * @return float
     */
    public function getSum(): float
    {
        return $this->sum;
    }

    /**
     * Remove specified scheduled payments
     *
     * @param integer|array<int> $ids         Scheduled payments identifiers to delete
     * @param History            $hist        History
     * @param boolean            $transaction True to begin a database transaction
     *
     * @return boolean
     */
    public function remove(int|array $ids, History $hist, bool $transaction = true): bool
    {
        $list = [];
        if (is_array($ids)) {
            $list = $ids;
        } else {
            $list = [$ids];
        }

        try {
            if ($transaction) {
                $this->zdb->connection->beginTransaction();
            }
            $select = $this->zdb->select(self::TABLE);
            $select->where->in(self::PK, $list);
            $scheduleds = $this->zdb->execute($select);
            foreach ($scheduleds as $scheduled) {
                $c = new ScheduledPayment($this->zdb, $scheduled);
                $res = $c->remove();
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
                    _T("Scheduled payments deleted (%list)")
                )
            );
            return true;
        } catch (Throwable $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred trying to remove scheduled payments | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }
}
