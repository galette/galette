<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History management
 *
 * PHP version 5
 *
 * Copyright © 2009-2024 The Galette Team
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2009-02-09
 */

namespace Galette\Core;

use Throwable;
use Analog\Analog;
use Galette\Filters\HistoryList;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;

/**
 * History management
 *
 * @category  Core
 * @name      History
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2009-02-09
 *
 * @property HistoryList $filters
 */

class History
{
    public const TABLE = 'logs';
    public const PK = 'id_log';

    protected int $count;
    protected Db $zdb;
    protected Login $login;
    protected Preferences $preferences;
    protected HistoryList $filters;

    /** @var array<int, string> */
    protected array $users;
    /** @var array<int, string> */
    protected array $actions;

    protected bool $with_lists = true;

    /**
     * Default constructor
     *
     * @param Db           $zdb         Database
     * @param Login        $login       Login
     * @param Preferences  $preferences Preferences
     * @param ?HistoryList $filters     Filtering
     */
    public function __construct(Db $zdb, Login $login, Preferences $preferences, HistoryList $filters = null)
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
    public static function findUserIPAddress(): string
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
    public function add(string $action, string $argument = '', string $query = ''): bool
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
                'adh_log'    => $this->login->login ?? '',
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
    public function clean(): bool
    {
        try {
            $this->zdb->db->query(
                'TRUNCATE TABLE ' . $this->getTableName(true),
                Adapter::QUERY_MODE_EXECUTE
            );
            $this->add('Logs flushed');
            $this->filters = new HistoryList();
            return true;
        } catch (Throwable $e) {
            $this->add('Error flushing logs');
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
     * @return array<int, object>
     */
    public function getHistory(): array
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
    private function buildLists(Select $select): void
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
     * @return array<int, string> SQL ORDER clauses
     */
    protected function buildOrderClause(): array
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
    private function buildWhereClause(Select $select): void
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

            //@phpstan-ignore-next-line
            if ($this->filters->user_filter != null && $this->filters->user_filter != '0') {
                $select->where->equalTo(
                    'adh_log',
                    $this->filters->user_filter
                );
            }

            //@phpstan-ignore-next-line
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
    private function proceedCount(Select $select): void
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
            $this->count = (int)$result->$k;
            $this->filters->setCounter($this->count);
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
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name)
    {
        Analog::log(
            '[History] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        $forbidden = array();
        if (!in_array($name, $forbidden)) {
            return $this->$name;
        } else {
            Analog::log(
                '[History] Unable to get property `' . $name . '`',
                Analog::WARNING
            );
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if (isset($this->$name)) {
            return true;
        }
        return false;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, $value): void
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
                '[History] Unable to set property `' . $name . '`',
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
    protected function getTableName(bool $prefixed = false): string
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
    protected function getPk(): string
    {
        return self::PK;
    }

    /**
     * Set filters
     *
     * @param HistoryList $filters Filters
     *
     * @return self
     */
    public function setFilters(HistoryList $filters): self
    {
        $this->filters = $filters;
        return $this;
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
     * Get users list
     *
     * @return array<int, string>
     */
    public function getUsersList(): array
    {
        return $this->users;
    }

    /**
     * Get actions list
     *
     * @return array<int, string>
     */
    public function getActionsList(): array
    {
        return $this->actions;
    }
}
