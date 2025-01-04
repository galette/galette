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
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Select;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\History;
use Galette\Entity\SavedSearch;
use Galette\Filters\SavedSearchesList;
use Galette\Entity\Adherent;

/**
 * Saved searches
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SavedSearches
{
    public const TABLE = SavedSearch::TABLE;
    public const PK = SavedSearch::PK;

    private SavedSearchesList $filters;
    private Db $zdb;
    private Login $login;
    private ?int $count = null;

    /**
     * Default constructor
     *
     * @param Db                 $zdb     Database
     * @param Login              $login   Login
     * @param ?SavedSearchesList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, ?SavedSearchesList $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new SavedSearchesList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get saved searches list
     *
     * @param bool           $as_search return the results as an array of
     *                                  SavedSearch object.
     * @param ?array<string> $fields    field(s) name(s) to get. Should be a string or
     *                                  an array. If null, all fields will be returned
     *
     * @return array<int, SavedSearch>|ResultSet
     */
    public function getList(bool $as_search = false, ?array $fields = null): array|ResultSet
    {
        try {
            $select = $this->buildSelect($fields);
            $this->filters->setLimits($select);

            $searches = array();
            $results = $this->zdb->execute($select);
            if ($as_search) {
                foreach ($results as $row) {
                    $searches[] = new SavedSearch($this->zdb, $this->login, $row);
                }
            } else {
                $searches = $results;
            }
            return $searches;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list saved searches | ' . $e->getMessage(),
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
            if ($fields !== null && count($fields)) {
                $fieldsList = $fields;
            }

            $select = $this->zdb->select(self::TABLE, 's');
            $select->columns($fieldsList);
            if (0 === $this->login->id) {
                $select->where->isNull(Adherent::PK);
            } else {
                $select->where([Adherent::PK => $this->login->id]);
            }

            $select->order(self::buildOrderClause());

            $this->proceedCount($select);

            return $select;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot build SELECT clause for saved searches | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count searches from the query
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
            $this->count = (int)$result->$k;
            $this->filters->setCounter($this->count);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count saved searches | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @return array<string>
     */
    private function buildOrderClause(): array
    {
        $order = array();
        $order[] = $this->filters->orderby . ' ' . $this->filters->ordered;

        return $order;
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
     * Remove specified searches
     *
     * @param integer|array<int> $ids         Searches identifiers to delete
     * @param History            $hist        History
     * @param boolean            $transaction True to begin a database transaction
     *
     * @return boolean
     */
    public function remove(int|array $ids, History $hist, bool $transaction = true): bool
    {
        $list = array();
        if (is_numeric($ids)) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if (count($list)) {
            try {
                if ($transaction) {
                    $this->zdb->connection->beginTransaction();
                }
                $select = $this->zdb->select(self::TABLE);
                $select->where->in(self::PK, $list);
                $searches = $this->zdb->execute($select);
                foreach ($searches as $search) {
                    $s = new SavedSearch($this->zdb, $this->login, $search);
                    $res = $s->remove();
                    if ($res === false) {
                        throw new \Exception('Cannot remove saved search');
                    }
                }
                if ($transaction) {
                    $this->zdb->connection->commit();
                }
                $hist->add(
                    str_replace(
                        '%list',
                        print_r($list, true),
                        _T("Searches deleted (%list)")
                    )
                );
                return true;
            } catch (Throwable $e) {
                if ($transaction) {
                    $this->zdb->connection->rollBack();
                }
                Analog::log(
                    'An error occurred trying to remove searches | ' .
                    $e->getMessage(),
                    Analog::ERROR
                );
                throw $e;
            }
        } else {
            //not numeric and not an array: incorrect.
            Analog::log(
                'Asking to remove searches, but without providing an array or a single numeric value.',
                Analog::WARNING
            );
            return false;
        }
    }
}
