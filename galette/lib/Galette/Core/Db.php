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

namespace Galette\Core;

use Exception;
use Laminas\Db\Adapter\Driver\Pdo\Result;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Db\Metadata\Source\Factory;
use LogicException;
use RuntimeException;
use Throwable;
use Analog\Analog;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\Adapter\Driver\AbstractConnection;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\Insert;
use Laminas\Db\Sql\Update;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Delete;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\SqlInterface;

/**
 * Zend Db wrapper
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property Adapter $db
 * @property Sql $sql
 * @property DriverInterface $driver
 * @property AbstractConnection $connection
 * @property PlatformInterface $platform
 * @property string $query_string
 * @property string $type_db
 */
class Db
{
    /** @var Adapter */
    private Adapter $db;
    /** @var string */
    private string $type_db;
    /** @var Sql */
    private Sql $sql;
    /** @var array<string,string> */
    private array $options;
    /** @var string */
    private string $last_query;

    public const MYSQL = 'mysql';
    public const PGSQL = 'pgsql';

    public const MYSQL_DEFAULT_PORT = 3306;
    public const PGSQL_DEFAULT_PORT = 5432;

    /**
     * Main constructor
     *
     * @param ?array<string,string> $dsn Connection information
     *                                   If not set, database constants will be used.
     * @throws Throwable
     */
    public function __construct(array $dsn = null)
    {
        $_type = null;

        if (is_array($dsn)) {
            $_type_db = $dsn['TYPE_DB'];
            $_host_db = $dsn['HOST_DB'];
            $_port_db = $dsn['PORT_DB'];
            $_user_db = $dsn['USER_DB'];
            $_pwd_db = $dsn['PWD_DB'];
            $_name_db = $dsn['NAME_DB'];
        } else {
            $_type_db = TYPE_DB;
            $_host_db = HOST_DB;
            $_port_db = PORT_DB;
            $_user_db = USER_DB;
            $_pwd_db = PWD_DB;
            $_name_db = NAME_DB;
        }

        try {
            if ($_type_db === self::MYSQL) {
                $_type = 'Pdo_Mysql';
            } elseif ($_type_db === self::PGSQL) {
                $_type = 'Pdo_Pgsql';
            } else {
                throw new Exception("Type $_type_db not known (dsn: $_user_db@$_host_db(:$_port_db)/$_name_db)");
            }

            $this->type_db = $_type_db;
            $this->options = array(
                'driver'   => $_type,
                'hostname' => $_host_db,
                'port'     => $_port_db,
                'username' => $_user_db,
                'password' => $_pwd_db,
                'database' => $_name_db
            );
            if ($_type_db === self::MYSQL) {
                $this->options['charset'] = 'utf8mb4';
            }

            $this->doConnection();
        } catch (Throwable $e) {
            // perhaps factory() failed to load the specified Adapter class
            Analog::log(
                '[Db] Error (' . $e->getCode() . '|' .
                $e->getMessage() . ')',
                Analog::ALERT
            );
            throw $e;
        }
    }

    /**
     * Do database connection
     *
     * @return void
     */
    private function doConnection(): void
    {
        $this->db = new Adapter($this->options);
        $this->db->getDriver()->getConnection()->connect();
        $this->sql = new Sql($this->db);

        if (!$this->isPostgres()) {
            $this->db->query("SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
        }
    }

    /**
     * To store Db in session
     *
     * @return array
     */
    public function __sleep(): array
    {
        return ['type_db', 'options'];
    }

    /**
     * Connect again to the database on wakeup
     *
     * @return void
     */
    public function __wakeup(): void
    {
        $this->doConnection();
    }

    /**
     * Retrieve current database version
     *
     * @param boolean $check_table Check if table exists, defaults to false
     *
     * @return string
     *
     * @throw LogicException
     */
    public function getDbVersion(bool $check_table = false): string
    {
        try {
            if ($check_table === true) {
                $exists = count($this->getTables(PREFIX_DB . 'database')) === 1;
            } else {
                $exists = true;
            }

            if ($exists === true) {
                $select = $this->select('database');
                $select->columns(
                    array('version')
                )->limit(1);

                $results = $this->execute($select);
                $result = $results->current();
                return number_format(
                    $result->version,
                    3,
                    '.',
                    ''
                );
            } else {
                return '0.63';
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot check database version: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw new LogicException('Cannot check database version');
        }
    }

    /**
     * Check if database version suits our needs
     *
     * @return boolean
     */
    public function checkDbVersion(): bool
    {
        if (Galette::isDebugEnabled()) {
            Analog::log(
                'Database version not checked in DEV mode.',
                Analog::INFO
            );
            return true;
        }

        try {
            return $this->getDbVersion() === GALETTE_DB_VERSION;
        } catch (LogicException $e) {
            return false;
        }
    }

    /**
     * Peform a select query on the whole table
     *
     * @param string $table Table name
     *
     * @return ResultSet
     */
    public function selectAll(string $table): ResultSet
    {
        return $this->db->query(
            'SELECT * FROM ' . PREFIX_DB . $table,
            Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test if database can be contacted. Mostly used for installation
     *
     * @param string  $type db type
     * @param ?string $user database's user
     * @param ?string $pass password for the user
     * @param ?string $host which host we want to connect to
     * @param ?string $port which tcp port we want to connect to
     * @param ?string $db   database name
     *
     * @return boolean
     *
     * @throws Exception|Throwable
     */
    public static function testConnectivity(
        string $type,
        string $user = null,
        string $pass = null,
        string $host = null,
        string $port = null,
        string $db = null
    ): bool {
        try {
            if ($type === self::MYSQL) {
                $_type = 'Pdo_Mysql';
            } elseif ($type === self::PGSQL) {
                $_type = 'Pdo_Pgsql';
            } else {
                throw new Exception('Unknown database type');
            }

            $_options = array(
                'driver'   => $_type,
                'hostname' => $host,
                'port'     => $port,
                'username' => $user,
                'password' => $pass,
                'database' => $db
            );

            $_db = new Adapter($_options);
            $_db->getDriver()->getConnection()->connect();

            return true;
        } catch (Throwable $e) {
            // perhaps failed to load the specified Adapter class
            Analog::log(
                '[' . __METHOD__ . '] Connection error (' . $e->getCode() . '|' .
                $e->getMessage() . ')',
                Analog::ALERT
            );
            throw $e;
        }
    }

    /**
     * Drop test table if it exists, so we can make all checks.
     *
     * @return void
     */
    public function dropTestTable(): void
    {
        try {
            $this->db->query('DROP TABLE IF EXISTS galette_test');
            Analog::log('Test table successfully dropped.', Analog::DEBUG);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot drop test table! ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Checks GRANT access for install time
     *
     * @param string $mode are we at install time (i) or update time (u) ?
     *
     * @return array<string, bool|Throwable> containing each test. Each array entry could
     *           be either true or contains an exception or false if test did not
     *           run.
     */
    public function grantCheck(string $mode = 'i'): array
    {
        Analog::log(
            'Check for database rights (mode ' . $mode . ')',
            Analog::DEBUG
        );
        $stop = false;
        $results = array(
            'create' => false,
            'insert' => false,
            'select' => false,
            'update' => false,
            'delete' => false,
            'drop'   => false
        );
        if ($mode === 'u') {
            $results['alter'] = false;
        }

        //can Galette CREATE tables?
        try {
            $sql = 'CREATE TABLE galette_test (
                test_id INTEGER NOT NULL,
                test_text VARCHAR(20)
            )';
            $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE);
            $results['create'] = true;
        } catch (Throwable $e) {
            Analog::log('Cannot CREATE TABLE', Analog::WARNING);
            //if we cannot create tables, we cannot check other permissions
            $stop = true;
            $results['create'] = $e;
        }

        //all those tests need the table to exists
        if (!$stop) {
            if ($mode == 'u') {
                //can Galette ALTER tables? (only for update mode)
                try {
                    $sql = 'ALTER TABLE galette_test ALTER test_text SET DEFAULT \'nothing\'';
                    $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE);
                    $results['alter'] = true;
                } catch (Throwable $e) {
                    Analog::log(
                        'Cannot ALTER TABLE | ' . $e->getMessage(),
                        Analog::WARNING
                    );
                    $results['alter'] = $e;
                }
            }

            //can Galette INSERT records ?
            $values = array(
                'test_id'      => 1,
                'test_text'    => 'a simple text'
            );
            try {
                $insert = $this->sql->insert('galette_test');
                $insert->values($values);

                $res = $this->execute($insert);

                if ($res->count() === 1) {
                    $results['insert'] = true;
                } else {
                    throw new Exception('No row inserted!');
                }
            } catch (Throwable $e) {
                Analog::log(
                    'Cannot INSERT records | ' . $e->getMessage(),
                    Analog::WARNING
                );
                //if we cannot insert records, some others tests cannot be done
                $stop = true;
                $results['insert'] = $e;
            }

            //all those tests need that the first record exists
            if (!$stop) {
                //can Galette UPDATE records ?
                $values = array(
                    'test_text' => 'another simple text'
                );
                try {
                    $update = $this->sql->update('galette_test');
                    $update->set($values)->where(
                        array('test_id' => 1)
                    );
                    $res = $this->execute($update);
                    if ($res->count() === 1) {
                        $results['update'] = true;
                    } else {
                        throw new Exception('No row updated!');
                    }
                } catch (Throwable $e) {
                    Analog::log(
                        'Cannot UPDATE records | ' . $e->getMessage(),
                        Analog::WARNING
                    );
                    $results['update'] = $e;
                }

                //can Galette SELECT records ?
                try {
                    $select = $this->sql->select('galette_test');
                    $select->where(['test_id' => 1]);
                    $res = $this->execute($select);
                    $pass = $res->count() === 1;

                    if ($pass) {
                        $results['select'] = true;
                    } else {
                        throw new Exception('Select is empty!');
                    }
                } catch (Throwable $e) {
                    Analog::log(
                        'Cannot SELECT records | ' . $e->getMessage(),
                        Analog::WARNING
                    );
                    $results['select'] = $e;
                }

                //can Galette DELETE records ?
                try {
                    $delete = $this->sql->delete('galette_test');
                    $delete->where(array('test_id' => 1));
                    $this->execute($delete);
                    $results['delete'] = true;
                } catch (Throwable $e) {
                    Analog::log(
                        'Cannot DELETE records | ' . $e->getMessage(),
                        Analog::WARNING
                    );
                    $results['delete'] = $e;
                }
            }

            //can Galette DROP tables ?
            try {
                $sql = 'DROP TABLE galette_test';
                $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE);
                $results['drop'] = true;
            } catch (Throwable $e) {
                Analog::log(
                    'Cannot DROP TABLE | ' . $e->getMessage(),
                    Analog::WARNING
                );
                $results['drop'] = $e;
            }
        }

        return $results;
    }

    /**
     * Get a list of Galette's tables
     *
     * @param ?string $prefix Specified table prefix, PREFIX_DB if null
     *
     * @return array<int, string>
     */
    public function getTables(string $prefix = null): array
    {
        $metadata = Factory::createSourceFromAdapter($this->db);
        $tmp_tables_list = $metadata->getTableNames();

        if ($prefix === null) {
            $prefix = PREFIX_DB;
        }

        $tables_list = array();
        //filter table_list: we only want PREFIX_DB tables
        foreach ($tmp_tables_list as $t) {
            if (preg_match('/^' . $prefix . '/', $t)) {
                $tables_list[] = $t;
            }
        }
        return $tables_list;
    }

    /**
     * Get columns for a specified table
     *
     * @param string $table Table name
     *
     * @return array<int, ColumnObject>
     */
    public function getColumns(string $table): array
    {
        $metadata = Factory::createSourceFromAdapter($this->db);
        $table = $metadata->getTable(PREFIX_DB . $table);
        return $table->getColumns();
    }

    /**
     * Converts recursively database to UTF-8
     *
     * @param ?string $prefix       Specified table prefix
     * @param boolean $content_only Proceed only content (no table conversion)
     *
     * @return void
     */
    public function convertToUTF(string $prefix = null, bool $content_only = false): void
    {
        if ($this->isPostgres()) {
            Analog::log(
                'Cannot change encoding on PostgreSQL database',
                Analog::INFO
            );
            return;
        }
        if ($prefix === null) {
            $prefix = PREFIX_DB;
        }

        $table = '';
        try {
            $tables = $this->getTables($prefix);

            foreach ($tables as $table) {
                if ($content_only === false) {
                    //Change whole table charset
                    //CONVERT TO instruction will take care of each fields,
                    //but converting data stay our problem.
                    $query = 'ALTER TABLE ' . $table .
                        ' CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci';

                    $this->db->query(
                        $query,
                        Adapter::QUERY_MODE_EXECUTE
                    );

                    Analog::log(
                        'Charset successfully changed for table `' . $table . '`',
                        Analog::DEBUG
                    );
                }

                //Data conversion
                if ($table != $prefix . 'pictures') {
                    $this->convertContentToUTF($prefix, $table);
                }
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred while converting to utf table ' .
                $table . ' (' . $e->getMessage() . ')',
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Converts dtabase content to UTF-8
     *
     * @param string $prefix Specified table prefix
     * @param string $table  the table we want to convert datas from
     *
     * @return void
     */
    private function convertContentToUTF(string $prefix, string $table): void
    {

        try {
            $query = 'SET NAMES latin1';
            $this->db->query(
                $query,
                Adapter::QUERY_MODE_EXECUTE
            );
        } catch (Throwable $e) {
            Analog::log(
                'Cannot SET NAMES on table `' . $table . '`. ' .
                $e->getMessage(),
                Analog::ERROR
            );
        }

        try {
            $metadata = Factory::createSourceFromAdapter($this->db);
            $tbl = $metadata->getTable($table);
            $constraints = $tbl->getConstraints();
            $pkeys = array();

            foreach ($constraints as $constraint) {
                if ($constraint->getType() === 'PRIMARY KEY') {
                    $pkeys = $constraint->getColumns();
                }
            }

            if (count($pkeys) == 0) {
                //no primary key! How to do an update without that?
                //Prior to 0.7, l10n and dynamic_fields tables does not
                //contains any primary key. Since encoding conversion is done
                //_before_ the SQL upgrade, we'll have to manually
                //check these ones
                if (preg_match('/' . $prefix . 'dynamic_fields/', $table) !== 0) {
                    $pkeys = array(
                        'item_id',
                        'field_id',
                        'field_form',
                        'val_index'
                    );
                } elseif (preg_match('/' . $prefix . 'l10n/', $table) !== 0) {
                    $pkeys = array(
                        'text_orig',
                        'text_locale'
                    );
                } else {
                    //not a know case, we do not perform any update.
                    throw new Exception(
                        'Cannot define primary key for table `' . $table .
                        '`, aborting'
                    );
                }
            }

            $select = $this->sql->select($table);
            $results = $this->execute($select);

            foreach ($results as $row) {
                $data = array();
                $where = array();

                //build where
                foreach ($pkeys as $k) {
                    $where[$k] = $row->$k;
                }

                //build data
                foreach ($row as $key => $value) {
                    $data[$key] = $value;
                }

                //finally, update data!
                $update = $this->sql->update($table);
                $update->set($data)->where($where);
                $this->execute($update);
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred while converting contents to UTF-8 for table ' .
                $table . ' (' . $e->getMessage() . ')',
                Analog::ERROR
            );
        }
    }

    /**
     * Is current database using Postgresql?
     *
     * @return boolean
     */
    public function isPostgres(): bool
    {
        return $this->type_db === self::PGSQL;
    }

    /**
     * Instanciate a select query
     *
     * @param string  $table Table name, without prefix
     * @param ?string $alias Tables alias, optional
     *
     * @return Select
     */
    public function select(string $table, string $alias = null): Select
    {
        if ($alias === null) {
            return $this->sql->select(
                PREFIX_DB . $table
            );
        } else {
            return $this->sql->select(
                //@phpstan-ignore-next-line
                array(
                    $alias => PREFIX_DB . $table
                )
            );
        }
    }

    /**
     * Instanciate an insert query
     *
     * @param string $table Table name, without prefix
     *
     * @return Insert
     */
    public function insert(string $table): Insert
    {
        return $this->sql->insert(
            PREFIX_DB . $table
        );
    }

    /**
     * Instanciate an update query
     *
     * @param string $table Table name, without prefix
     *
     * @return Update
     */
    public function update(string $table): Update
    {
        return $this->sql->update(
            PREFIX_DB . $table
        );
    }

    /**
     * Instanciate a delete query
     *
     * @param string $table Table name, without prefix
     *
     * @return Delete
     */
    public function delete(string $table): Delete
    {
        return $this->sql->delete(
            PREFIX_DB . $table
        );
    }

    /**
     * Execute query string
     *
     * @param SqlInterface $sql SQL object
     *
     * @return ResultSet|Result
     * @throws Throwable
     */
    public function execute(SqlInterface $sql): ResultSet|Result
    {
        try {
            $query_string = $this->sql->buildSqlString($sql);
            $this->last_query = $query_string;
            $this->log($query_string);
            return $this->db->query(
                $query_string,
                Adapter::QUERY_MODE_EXECUTE
            );
        } catch (Throwable $e) {
            $msg = 'Query error: ';
            if (isset($query_string)) {
                $msg .= $query_string;
            }
            Analog::log(
                $msg . ' ' . $e->__toString(),
                Analog::ERROR
            );
            if ($this->isDuplicateException($sql, $e)) {
                throw new \OverflowException('Duplicate entry', 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the variable we want to retrieve
     *
     * @return mixed
     * @throws RuntimeException
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'db':
                return $this->db;
            case 'sql':
                return $this->sql;
            case 'driver':
                return $this->db->getDriver();
            case 'connection':
                return $this->db->getDriver()->getConnection();
            case 'platform':
                return $this->db->getPlatform();
            case 'query_string':
                return $this->last_query;
            case 'type_db':
                return $this->type_db;
            default:
                throw new RuntimeException('Unknown property ' . $name);
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the variable we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        switch ($name) {
            case 'db':
            case 'sql':
            case 'driver':
            case 'connection':
            case 'platform':
            case 'query_string':
            case 'type_db':
                return true;
        }
        return property_exists($this, $name);
    }

    /**
     * Get database information
     *
     * @return array<string, string>
     */
    public function getInfos(): array
    {
        $infos = [
            'engine'    => null,
            'version'   => null,
            'size'      => null,
            'log_size'  => null,
            'sql_mode'  => ''
        ];

        if ($this->isPostgres()) {
            $infos['engine'] = 'PostgreSQL';
            $sql = 'SHOW server_version';
            $result = $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();
            $infos['version'] = $result['server_version'];

            $sql = 'SELECT pg_database_size(\'' . NAME_DB . '\')';
            $result = $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();
            $infos['size'] = (string)round($result['pg_database_size'] / 1024 / 1024);
        } else {
            $sql = 'SELECT @@sql_mode as mode, @@version AS version, @@version_comment AS version_comment';
            $result = $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();

            $infos['engine']    = $result['version_comment'];
            $infos['version']   = $result['version'];
            $infos['sql_mode']  = $result['mode'];

            $size_sql = 'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS dbsize' .
                ' FROM information_schema.tables WHERE table_schema="' . NAME_DB . '"';
            $result = $this->db->query($size_sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();

            $infos['size'] = $result['dbsize'];
        }

        return $infos;
    }

    /**
     * Handle sequence on PostgreSQL
     *
     * When inserting a value on a field with a sequence,
     * this one is not incremented.
     * This happens when installing system values (for status, titles, ...)
     *
     * @see https://bugs.galette.eu/issues/1158
     * @see https://bugs.galette.eu/issues/1374
     *
     * @param string  $table    Table name
     * @param integer $expected Expected value
     *
     * @return void
     */
    public function handleSequence(string $table, int $expected): void
    {
        if ($this->isPostgres()) {
            //check for Postgres sequence
            //see https://bugs.galette.eu/issues/1158
            //see https://bugs.galette.eu/issues/1374
            $seq = $table . '_id_seq';

            $select = $this->select($seq);
            $select->columns(['last_value']);
            $results = $this->execute($select);
            $result = $results->current();
            if ($result->last_value < $expected) {
                $this->db->query(
                    'SELECT setval(\'' . PREFIX_DB . $seq . '\', ' . $expected . ')',
                    Adapter::QUERY_MODE_EXECUTE
                );
            }
        }
    }

    /**
     * Check if current exception is on a duplicate key
     *
     * @param SqlInterface $sql       SQL object
     * @param Throwable    $exception Exception to check
     *
     * @return boolean
     */
    public function isDuplicateException(SqlInterface $sql, Throwable $exception): bool
    {
        return $sql instanceof Insert && $exception instanceof \PDOException
            && (
                (!$this->isPostgres() && $exception->errorInfo[1] === 1062)
                || ($this->isPostgres() && $exception->getCode() == 23505)
            )
        ;
    }

    /**
     * Check if current exception is related to a remaining foreign key
     *
     * @param Throwable $exception Exception to check
     *
     * @return boolean
     */
    public function isForeignKeyException(Throwable $exception): bool
    {
        return $exception instanceof \PDOException
            && (
                (!$this->isPostgres() && in_array($exception->errorInfo[1], [1217, 1451]))
                || ($this->isPostgres() && $exception->getCode() == 23503)
            )
        ;
    }

    /**
     * Drops a table
     *
     * @param string  $table   Table name, without prefix
     * @param boolean $maymiss Whether the table can be missing, defaults to false
     *
     * @return void
     */
    public function drop(string $table, bool $maymiss = false): void
    {
        $sql = 'DROP TABLE ';
        if ($maymiss === true) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= PREFIX_DB . $table;
        $this->db->query(
            $sql,
            Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Log queries in specific file
     *
     * @param string $query Query to add in logs
     *
     * @return void
     */
    protected function log(string $query): void
    {
        if (Galette::isSqlDebugEnabled()) {
            $logfile = GALETTE_LOGS_PATH . 'galette_sql.log';
            file_put_contents($logfile, $query . "\n", FILE_APPEND);
        }
    }

    /**
     * Get last generated value
     *
     * @param object $entity Entity instance
     *
     * @return integer
     */
    public function getLastGeneratedValue($entity): int
    {
        /** @phpstan-ignore-next-line */
        return (int)$this->driver->getLastGeneratedValue(
            $this->isPostgres() ?
                PREFIX_DB . $entity::TABLE . '_id_seq'
                : null
        );
    }

    /**
     * Get MySQL warnings
     *
     * @return array<array<string, string>>
     */
    public function getWarnings(): array
    {
        $results = $this->db->query('SHOW WARNINGS', Adapter::QUERY_MODE_EXECUTE);

        $warnings = [];
        foreach ($results as $result) {
            $warnings[] = $result;
        }

        return $warnings;
    }

    /**
     * Is current database engine supported?
     *
     * @return bool
     */
    public function isEngineSUpported(): bool
    {
        $infos = $this->getInfos();
        $version = $infos['version'];
        if ($this->isPostgres()) {
            $min_version = GALETTE_PGSQL_MIN;
        } else {
            $min_version = str_contains($version, '-MariaDB') ? GALETTE_MARIADB_MIN : GALETTE_MYSQL_MIN;
        }

        $version = preg_replace('/^((\d+\.?)+).*$/', '$1', $version);
        return version_compare($version, $min_version, '>=');
    }

    /**
     * Get not supported database version message
     *
     * @return string
     */
    public function getUnsupportedMessage(): string
    {
        $infos = $this->getInfos();
        $version = $infos['version'];
        if ($this->isPostgres()) {
            $engine = 'PostgreSQL';
            $min_version = GALETTE_PGSQL_MIN;
        } else {
            $engine = str_contains($version, '-MariaDB') ? 'MariaDB' : 'MySQL';
            $min_version = str_contains($version, '-MariaDB') ? GALETTE_MARIADB_MIN : GALETTE_MYSQL_MIN;
        }

        $version = preg_replace('/^((\d+\.?)+).*$/', '$1', $version);

        return sprintf(
            _T('Minimum version for %1$s engine is %2$s, %1$s %3$s found!'),
            $engine,
            $min_version,
            $version
        );
    }
}
