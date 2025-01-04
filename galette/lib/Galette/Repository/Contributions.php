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

use ArrayObject;
use Galette\Entity\Group;
use Laminas\Db\ResultSet\ResultSet;
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
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Contributions
{
    public const TABLE = Contribution::TABLE;
    public const PK = Contribution::PK;

    private ContributionsList $filters;
    private int $count = 0;

    private Db $zdb;
    private Login $login;
    private float $sum = 0;
    /** @var array<int> */
    private array $current_selection;

    /**
     * Default constructor
     *
     * @param Db                 $zdb     Database
     * @param Login              $login   Login
     * @param ?ContributionsList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, ?ContributionsList $filters = null)
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
    public function getListFromTransaction(int $trans_id): array
    {
        $this->filters->from_transaction = $trans_id;
        return $this->getList(true);
    }

    /**
     * Get contributions list for a specific transaction
     *
     * @param array<int>     $ids        an array of members id that has been selected
     * @param bool           $as_contrib return the results as an array of
     * @param ?array<string> $fields     field(s) name(s) to get. Should be a string or
     *                                   an array. If null, all fields will be returned
     *
     * @return array<int, Contribution>|false
     */
    public function getArrayList(array $ids, bool $as_contrib = false, ?array $fields = null): array|false
    {
        if (count($ids) < 1) {
            Analog::log('No contribution selected.', Analog::INFO);
            return false;
        }

        $this->current_selection = $ids;
        $list = $this->getList($as_contrib, $fields);
        $array_list = [];
        foreach ($list as $entry) {
            $array_list[] = $entry;
        }
        return $array_list;
    }

    /**
     * Get contributions list
     *
     * @param bool           $as_contrib return the results as an array of
     *                                   Contribution object.
     * @param ?array<string> $fields     field(s) name(s) to get. Should be a string or
     *                                   an array. If null, all fields will be returned
     *
     * @return array<int, Contribution>|ResultSet
     */
    public function getList(bool $as_contrib = false, ?array $fields = null): array|ResultSet
    {
        try {
            $select = $this->buildSelect($fields);

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

            $select = $this->zdb->select(self::TABLE, 'c');
            $select->columns($fieldsList);

            $select->join(
                array('a' => PREFIX_DB . Adherent::TABLE),
                'c.' . Adherent::PK . '= a.' . Adherent::PK,
                array()
            );

            $this->buildWhereClause($select);
            $select->order(self::buildOrderClause());

            $this->calculateSum($select);

            $this->proceedCount($select);

            return $select;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot build SELECT clause for contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count contributions from the query
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
                array(
                    self::PK => new Expression('COUNT(' . self::PK . ')')
                )
            );

            $results = $this->zdb->execute($countSelect);
            $result = $results->current();

            if (!$result) {
                return;
            }

            $k = self::PK;
            $this->count = (int)$result->$k;
            $this->filters->setCounter($this->count);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count contributions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Calculate sum of all selected contributions
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
                array(
                    'contribsum' => new Expression('SUM(montant_cotis)')
                )
            );

            $results = $this->zdb->execute($sumSelect);
            $result = $results->current();
            if ($result && $result->contribsum) {
                $this->sum = round((float)$result->contribsum, 2);
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot calculate contributions sum | ' . $e->getMessage(),
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
            case ContributionsList::ORDERBY_PAYMENT_TYPE:
                $order[] = 'type_paiement_cotis ' . $this->filters->ordered;
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
        global $preferences;
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

        if (isset($this->current_selection)) {
            $select->where->in('c.' . self::PK, $this->current_selection);
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

            if ($this->filters->contrib_type_filter !== null) {
                $select->where->equalTo(
                    ContributionsTypes::PK,
                    $this->filters->contrib_type_filter
                );
            }

            if ($this->filters->from_transaction !== false) {
                $select->where->equalTo(
                    Transaction::PK,
                    $this->filters->from_transaction
                );
            }

            if ($this->filters->max_amount !== null) {
                $select->where(
                    '(montant_cotis <= ' . $this->filters->max_amount .
                    ' OR montant_cotis IS NULL)'
                );
            }

            $member_clause = null;
            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                //default case, only display transactions for current member
                $member_clause = [$this->login->id];
            }
            if ($this->filters->filtre_cotis_adh != null) {
                //handle case when list is filtered on a single member id
                if (!$this->login->isAdmin() && !$this->login->isStaff() && $this->filters->filtre_cotis_adh != $this->login->id) {
                    $member = new Adherent(
                        $this->zdb,
                        (int)$this->filters->filtre_cotis_adh,
                        [
                            'picture' => false,
                            'groups' => true,
                            'dues' => false,
                            'parent' => true
                        ]
                    );
                    if (
                        !$member->hasParent() ||
                        $member->parent->id != $this->login->id
                    ) {
                        //check if member is part of logged-in user managed groups
                        $mgroup = $this->login->getManagedGroups();
                        $groups = $member->getGroups();
                        if (count(array_intersect(array_keys($mgroup), array_keys($groups))) == 0) {
                            Analog::log(
                                'Trying to display contributions for member #' . $member->id .
                                ' without appropriate ACLs',
                                Analog::WARNING
                            );
                            $this->filters->filtre_cotis_adh = $this->login->id;
                        }
                    }
                }
                $member_clause = [$this->filters->filtre_cotis_adh];
            } elseif ($this->filters->filtre_cotis_children !== false) {
                //handle children case
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
            }

            if (
                $this->filters->filtre_cotis_adh == null
                && !$this->login->isAdmin()
                && !$this->login->isStaff()
                && $this->login->isGroupManager()
                && $preferences->pref_bool_groupsmanagers_see_contributions
            ) {
                //limit to managed members from managed groups
                $mgroups = $this->login->getManagedGroups();

                $select->join(
                    array('users_groups' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
                    'c.' . Adherent::PK . '=users_groups.' . Adherent::PK,
                    array(),
                    $select::JOIN_LEFT
                );
                $select->where->nest()
                    ->in('users_groups.' . Group::PK, array_values($mgroups))
                    ->or
                    ->in('c.' . Adherent::PK, $member_clause);
                $select->group('c.' . Contribution::PK);

                //member clause is already handled, reset it
                $member_clause = null;
            }

            if ($member_clause !== null) {
                $select->where(
                    array(
                        'c.' . Adherent::PK => $member_clause
                    )
                );
            }

            if ($this->filters->filtre_transactions === true) {
                $select->where('c.trans_id IS NULL');
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
     * Remove specified contributions
     *
     * @param integer|array<int> $ids         Contributions identifiers to delete
     * @param History            $hist        History
     * @param boolean            $transaction True to begin a database transaction
     *
     * @return boolean
     */
    public function remove(int|array $ids, History $hist, bool $transaction = true): bool
    {
        $list = array();
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
            throw $e;
        }
    }
}
