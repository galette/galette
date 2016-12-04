<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History management
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-09
 */

namespace Galette\Core;

use Analog\Analog;
use Galette\Filters\HistoryList;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Adapter;

/**
 * History management
 *
 * @category  Core
 * @name      History
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-09
 */

class History
{
    const TABLE = 'logs';
    const PK = 'id_log';

    protected $count;
    protected $zdb;
    protected $login;
    protected $filters;

    protected $users;
    protected $actions;

    /**
     * Default constructor
     *
     * @param Db          $zdb     Database
     * @param Login       $login   Login
     * @param HistoryList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new HistoryList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Add a new entry
     *
     * @param string $action   the action to log
     * @param string $argument the argument
     * @param string $query    the query (if relevant)
     *
     * @return bool true if entry was successfully added, false otherwise
     */
    public function add($action, $argument = '', $query = '')
    {
        $ip = null;
        if (PHP_SAPI === 'cli') {
            $ip = '127.0.0.1';
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        try {
            $values = array(
                'date_log'   => date('Y-m-d H:i:s'),
                'ip_log'     => $ip,
                'adh_log'    => $this->login->login,
                'action_log' => $action,
                'text_log'   => $argument,
                'sql_log'    => $query
            );

            $insert = $this->zdb->insert($this->getTableName());
            $insert->values($values);
            $this->zdb->execute($insert);
        } catch (\Exception $e) {
            Analog::log(
                "An error occured trying to add log entry. " . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }

        return true;
    }

    /**
     * Delete all entries
     *
     * @return boolean
     */
    public function clean()
    {
        try {
            $result = $this->zdb->db->query(
                'TRUNCATE TABLE ' . $this->getTableName(true),
                Adapter::QUERY_MODE_EXECUTE
            );

            if (!$result) {
                Analog::log(
                    'An error occured cleaning history. ',
                    Analog::WARNING
                );
                $this->add('Error flushing logs');
                return false;
            }
            $this->add('Logs flushed');
            $this->filters = new HistoryList();
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to flush logs. | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Get the entire history list
     *
     * @return array
     */
    public function getHistory()
    {
        try {
            $select = $this->zdb->select($this->getTableName());
            $this->buildWhereClause($select);
            $select->order(self::buildOrderClause());
            $this->buildLists($select);
            $this->proceedCount($select);
            //add limits to retrieve only relavant rows
            $this->filters->setLimit($select);
            $results = $this->zdb->execute($select);
            return $results;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to get history. | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Builds users and actions lists
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function buildLists($select)
    {
        try {
            $usersSelect = clone $select;
            $usersSelect->reset($usersSelect::COLUMNS);
            $usersSelect->reset($usersSelect::ORDER);
            $usersSelect->quantifier('DISTINCT')->columns(['adh_log']);
            $usersSelect->order(['adh_log ASC']);

            $results = $this->zdb->execute($usersSelect);

            $this->users = [];
            foreach ($results as $result) {
                $this->users[] = $result->adh_log;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list members from history! | ' . $e->getMessage(),
                Analog::WARNING
            );
        }

        try {
            $actionsSelect = clone $select;
            $actionsSelect->reset($actionsSelect::COLUMNS);
            $actionsSelect->reset($actionsSelect::ORDER);
            $actionsSelect->quantifier('DISTINCT')->columns(['action_log']);
            $actionsSelect->order(['action_log ASC']);

            $results = $this->zdb->execute($actionsSelect);

            $this->actions = [];
            foreach ($results as $result) {
                $this->actions[] = $result->action_log;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list actions from history! | ' . $e->getMessage(),
                Analog::WARNING
            );
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
            case HistoryList::ORDERBY_DATE:
                $order[] = 'date_log ' . $this->filters->ordered;
                break;
            case HistoryList::ORDERBY_IP:
                $order[] = 'ip_log ' . $this->filters->ordered;
                break;
            case HistoryList::ORDERBY_USER:
                $order[] = 'adh_log ' . $this->filters->ordered;
                break;
            case HistoryList::ORDERBY_ACTION:
                $order[] = 'action_log ' . $this->filters->ordered;
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
    private function buildWhereClause($select)
    {
        try {
            if ($this->filters->start_date_filter != null) {
                $d = new \DateTime($this->filters->raw_start_date_filter);
                $d->setTime(0, 0, 0);
                $select->where->greaterThanOrEqualTo(
                    'date_log',
                    $d->format('Y-m-d H:i:s')
                );
            }

            if ($this->filters->end_date_filter != null) {
                $d = new \DateTime($this->filters->raw_end_date_filter);
                $d->setTime(23, 59, 59);
                $select->where->lessThanOrEqualTo(
                    'date_log',
                    $d->format('Y-m-d H:i:s')
                );
            }

            if ($this->filters->user_filter != null && $this->filters->user_filter != '0') {
                $select->where->equalTo(
                    'adh_log',
                    $this->filters->user_filter
                );
            }

            if ($this->filters->action_filter != null && $this->filters->action_filter != '0') {
                $select->where->equalTo(
                    'action_log',
                    $this->filters->action_filter
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
     * Count history entries from the query
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
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count history | ' . $e->getMessage(),
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
     * @return false|object the called property
     */
    public function __get($name)
    {
        Analog::log(
            '[History] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        $forbidden = array();
        if (!in_array($name, $forbidden)) {
            switch ($name) {
                case 'fdate':
                    //return formatted datemime
                    try {
                        $d = new \DateTime($this->$name);
                        return $d->format(__("Y-m-d H:i:s"));
                    } catch (\Exception $e) {
                        //oops, we've got a bad date :/
                        Analog::log(
                            'Bad date (' . $this->$name . ') | ' .
                            $e->getMessage(),
                            Analog::INFO
                        );
                        return $this->$name;
                    }
                    break;
                default:
                    return $this->$name;
                    break;
            }
        } else {
            Analog::log(
                '[History] Unable to get proprety `' .$name . '`',
                Analog::WARNING
            );
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
        Analog::log(
            '[History] Setting property `' . $name . '`',
            Analog::DEBUG
        );

        $forbidden = array();
        if (!in_array($name, $forbidden)) {
            switch ($name) {
                default:
                    $this->$name = $value;
                    break;
            }
        } else {
            Analog::log(
                '[History] Unable to set proprety `' .$name . '`',
                Analog::WARNING
            );
        }
    }

    /**
     * Get table's name
     *
     * @param boolean $prefixed Whether table name should be prefixed
     *
     * @return string
     */
    protected function getTableName($prefixed = false)
    {
        if ($prefixed === true) {
            return PREFIX_DB . self::TABLE;
        } else {
            return self::TABLE;
        }
    }

    /**
     * Get table's PK
     *
     * @return string
     */
    protected function getPk()
    {
        return self::PK;
    }

    /**
     * Set filters
     *
     * @param HistoryList $filters Filters
     *
     * @return History
     */
    public function setFilters(HistoryList $filters)
    {
        $this->filters = $filters;
        return $this;
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
     * Get users list
     *
     * @return array
     */
    public function getUsersList()
    {
        return $this->users;
    }

    /**
     * Get actions list
     *
     * @return array
     */
    public function getActionsList()
    {
        return $this->actions;
    }
}
