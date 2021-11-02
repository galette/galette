<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Saved searches
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
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
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-09-21
 */

namespace Galette\Repository;

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
 * @category  Repository
 * @name      SavedSearches
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2019-09-21
 */
class SavedSearches
{
    public const TABLE = SavedSearch::TABLE;
    public const PK = SavedSearch::PK;

    private $count = null;

    /**
     * Default constructor
     *
     * @param Db                $zdb     Database
     * @param Login             $login   Login
     * @param SavedSearchesList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, $filters = null)
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
     * @param bool    $as_search return the results as an array of
     *                           SavedSearch object.
     * @param array   $fields    field(s) name(s) to get. Should be a string or
     *                           an array. If null, all fields will be
     *                           returned
     * @param boolean $count     true if we want to count
     *
     * @return SavedSearch[]|ResultSet
     */
    public function getList($as_search = false, $fields = null, $count = true)
    {
        try {
            $select = $this->buildSelect($fields, $count);
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

            $select = $this->zdb->select(self::TABLE, 's');
            $select->columns($fieldsList);
            if (0 === $this->login->id) {
                $select->where->isNull(Adherent::PK);
            } else {
                $select->where([Adherent::PK => $this->login->id]);
            }

            $select->order(self::buildOrderClause());

            if ($count) {
                $this->proceedCount($select);
            }

            return $select;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot build SELECT clause for saved searches | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Count searches from the query
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function proceedCount($select)
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
                'Cannot count saved searches | ' . $e->getMessage(),
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
        $order[] = $this->filters->orderby . ' ' . $this->filters->ordered;

        return $order;
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
     * Remove specified searches
     *
     * @param integer|array $ids         Searches identifiers to delete
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
                $searches = $this->zdb->execute($select);
                foreach ($searches as $search) {
                    $s = new SavedSearch($this->zdb, $this->login, $search);
                    $res = $s->remove(false);
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
                return false;
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
