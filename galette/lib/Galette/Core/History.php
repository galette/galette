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
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property HistoryList $filters
 */

class History
{
    public const TABLE = 'logs';
    public const PK = 'id_log';

    protected int $count;
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
    public function __construct(protected Db $zdb, protected Login $login, protected Preferences $preferences, ?HistoryList $filters = null)
    {
        $this->filters = $filters ?? new HistoryList();
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
            $split_xff = preg_split('/,\s*/', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = $split_xff[count($split_xff) - GALETTE_X_FORWARDED_FOR_INDEX];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
        ) {
            return $ip;
        }

        return '';
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
        $ip = PHP_SAPI === 'cli' ? '127.0.0.1' : self::findUserIpAddress();

        try {
            $values = [
                'date_log'   => date('Y-m-d H:i:s'),
                'ip_log'     => $ip,
                'adh_log'    => $this->login->login ?? '',
                'action_log' => $action,
                'text_log'   => $argument,
                'sql_log'    => $query
            ];

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
        $this->users = [];
        try {
            $usersSelect = clone $select;
            $usersSelect->reset($usersSelect::COLUMNS);
            $usersSelect->reset($usersSelect::ORDER);
            $usersSelect->quantifier('DISTINCT')->columns(['adh_log']);
            $usersSelect->order(['adh_log ASC']);

            $results = $this->zdb->execute($usersSelect);

            foreach ($results as $result) {
                $ulabel = $result->adh_log;
                if ($ulabel === '') {
                    $ulabel = _T('None');
                }
                $this->users[] = $ulabel;
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
        $order = [];

        switch ($this->filters->orderby) {
            case HistoryList::ORDERBY_DATE:
                $order[] = 'date_log ' . $this->filters->getDirection();
                break;
            case HistoryList::ORDERBY_IP:
                $order[] = 'ip_log ' . $this->filters->getDirection();
                break;
            case HistoryList::ORDERBY_USER:
                $order[] = 'adh_log ' . $this->filters->getDirection();
                break;
            case HistoryList::ORDERBY_ACTION:
                $order[] = 'action_log ' . $this->filters->getDirection();
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

            if ($this->filters->user_filter !== null && $this->filters->user_filter != '0') {
                if ($this->filters->user_filter === _T('None')) {
                    $this->filters->user_filter = '';
                }
                $select->where->equalTo(
                    'adh_log',
                    $this->filters->user_filter
                );
            }

            if ($this->filters->action_filter !== null && $this->filters->action_filter != '0') {
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
                [
                    $this->getPk() => new Expression('COUNT(' . $this->getPk() . ')')
                ]
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
    public function __get(string $name): mixed
    {
        return $this->$name;
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
        return isset($this->$name);
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->$name = $value;
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
