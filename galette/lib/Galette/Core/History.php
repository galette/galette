<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History management
 *
 * PHP version 5
 *
 * Copyright © 2009-2023 The Galette Team
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
 * @copyright 2009-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-09
 */

namespace Galette\Core;

use Throwable;
use Analog\Analog;
use Galette\Filters\HistoryList;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Adapter\Adapter;
use Galette\Core\Preferences;
use Laminas\Db\Sql\Select;

/**
 * History management
 *
 * @category  Core
 * @name      History
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-09
 */

class History
{
    public const TABLE = 'logs';
    public const PK = 'id_log';

    protected $count;
    protected $zdb;
    protected $login;
    protected $preferences;
    protected $filters;

    protected $users;
    protected $actions;

    protected $with_lists = true;

    /**
     * Default constructor
     *
     * @param Db          $zdb         Database
     * @param Login       $login       Login
     * @param Preferences $preferences Preferences
     * @param HistoryList $filters     Filtering
     */
    public function __construct(Db $zdb, Login $login, Preferences $preferences, $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        $this->preferences = $preferences;

        if ($filters === null) {
            $this->filters = new HistoryList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Helper function to find the user IP address
     *
     * This function uses the client address or the appropriate part of
     * X-Forwarded-For, if present and the configuration specifies it.
     * (blindly trusting X-Forwarded-For would make the IP address logging
     * very easy to deveive.
     *
     * @return string
     */
    public static function findUserIPAddress()
    {
        if (
            defined('GALETTE_X_FORWARDED_FOR_INDEX')
            && isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ) {
            $split_xff = preg_split('/,\s*/', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return $split_xff[count($split_xff) - GALETTE_X_FORWARDED_FOR_INDEX];
        }
        return $_SERVER['REMOTE_ADDR'];
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
        if ($this->preferences->pref_log == Preferences::LOG_DISABLED) {
            //logs are disabled
            return true;
        }

        $ip = null;
        if (PHP_SAPI === 'cli') {
            $ip = '127.0.0.1';
        } else {
            $ip = self::findUserIpAddress();
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
        } catch (Throwable $e) {
            Analog::log(
                "An error occurred trying to add log entry. " . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
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
                    'An error occurred cleaning history. ',
                    Analog::WARNING
                );
                $this->add('Error flushing logs');
                return false;
            }
            $this->add('Logs flushed');
            $this->filters = new HistoryList();
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to flush logs. | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
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
            $select->order($this->buildOrderClause());
            if ($this->with_lists === true) {
                $this->buildLists($select);
            }
            $this->proceedCount($select);
            //add limits to retrieve only relavant rows
            $this->filters->setLimits($select);
            $results = $this->zdb->execute($select);

            $entries = [];
            foreach ($results as $result) {
                $entries[] = $result;
            }

            return $entries;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to get history. | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds users and actions lists
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function buildLists(Select $select)
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list actions from history! | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @return string SQL ORDER clause
     */
    protected function buildOrderClause()
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
     * @return void
     */
    private function buildWhereClause(Select $select)
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
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count history entries from the query
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
                    $this->getPk() => new Expression('COUNT(' . $this->getPk() . ')')
                )
            );

            $results = $this->zdb->execute($countSelect);
            $result = $results->current();

            $k = $this->getPk();
            $this->count = $result->$k;
            if ($this->count > 0) {
                $this->filters->setCounter($this->count);
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count history | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrive
     *
     * @return mixed the called property
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
                    //return formatted datetime
                    try {
                        $d = new \DateTime($this->$name);
                        return $d->format(__("Y-m-d H:i:s"));
                    } catch (Throwable $e) {
                        //oops, we've got a bad date :/
                        Analog::log(
                            'Bad date (' . $this->$name . ') | ' .
                            $e->getMessage(),
                            Analog::INFO
                        );
                        return $this->$name;
                    }
                default:
                    return $this->$name;
            }
        } else {
            Analog::log(
                '[History] Unable to get proprety `' . $name . '`',
                Analog::WARNING
            );
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrive
     *
     * @return false|object the called property
     */
    public function __isset($name)
    {
        if ($name == 'fdate' || isset($this->$name)) {
            return true;
        }
        return false;
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
                '[History] Unable to set proprety `' . $name . '`',
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
