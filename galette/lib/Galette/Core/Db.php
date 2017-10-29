<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Zend Db wrapper
 *
 * PHP version 5
 *
 * Copyright © 2011-2014 The Galette Team
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
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-27
 */

namespace Galette\Core;

use Analog\Analog;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;

/**
 * Zend Db wrapper
 *
 * @category  Core
 * @name      Db
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://framework.zend.com/apidoc/2.2/namespaces/Zend.Db.html
 * @since     Available since 0.7dev - 2011-07-27
 */
class Db
{
    private $db;
    private $type_db;
    private $sql;
    private $options;

    const MYSQL = 'mysql';
    const PGSQL = 'pgsql';

    const MYSQL_DEFAULT_PORT = 3306;
    const PGSQL_DEFAULT_PORT = 5432;

    /**
     * Main constructor
     *
     * @param array $dsn Connection informations
     *                   If not set, database constants will be used.
     */
    public function __construct($dsn = null)
    {
        $_type = null;

        if ($dsn !== null && is_array($dsn)) {
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
                throw new \Exception;
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
            if ($_type_db === self::MYSQL && !defined('NON_UTF_DBCONNECT')) {
                $this->options['charset'] = 'utf8';
            }

            $this->doConnection();
        } catch (\Exception $e) {
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
    private function doConnection()
    {
        $this->db = new Adapter($this->options);
        $this->db->getDriver()->getConnection()->connect();
        $this->sql = new Sql($this->db);

        Analog::log(
            '[Db] Database connection was successfull!',
            Analog::DEBUG
        );
    }

    /**
     * To store Db in session
     *
     * @return array
     */
    public function __sleep()
    {
        return ['type_db', 'options'];
    }

    /**
     * Connect again to the database on wakeup
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->doConnection();
    }

    /**
     * Retrieve current database version
     *
     * @return float
     *
     * @throw LogicException
     */
    public function getDbVersion()
    {
        try {
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
        } catch (\Exception $e) {
            Analog::log(
                'Cannot check database version: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw new \LogicException('Cannot check database version');
        }
    }

    /**
     * Check if database version suits our needs
     *
     * @return boolean
     */
    public function checkDbVersion()
    {
        if (GALETTE_MODE === 'DEV') {
            Analog::log(
                'Database version not checked in DEV mode.',
                Analog::INFO
            );
            return true;
        }

        try {
            return $this->getDbVersion() === GALETTE_DB_VERSION;
        } catch (\LogicException $e) {
            return false;
        }
    }

    /**
     * Peform a select query on the whole table
     *
     * @param string $table Table name
     *
     * @return array
     */
    public function selectAll($table)
    {
        return $this->db->query(
            'SELECT * FROM ' . PREFIX_DB . $table,
            Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test if database can be contacted. Mostly used for installation
     *
     * @param string $type db type
     * @param string $user database's user
     * @param string $pass password for the user
     * @param string $host which host we want to connect to
     * @param string $port which tcp port we want to connect to
     * @param string $db   database name
     *
     * @return true|array true if connection was successfull,
     *                    an array with some infos otherwise
     */
    public static function testConnectivity(
        $type,
        $user = null,
        $pass = null,
        $host = null,
        $port = null,
        $db = null
    ) {
        $_type = null;
        try {
            if ($type === self::MYSQL) {
                $_type = 'Pdo_Mysql';
            } elseif ($type === self::PGSQL) {
                $_type = 'Pdo_Pgsql';
            } else {
                throw new \Exception;
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

            Analog::log(
                '[' . __METHOD__ . '] Database connection was successfull!',
                Analog::DEBUG
            );
            return true;
        } catch (\Exception $e) {
            // perhaps failed to load the specified Adapter class
            Analog::log(
                '[' . __METHOD__ . '] Connection error (' . $e->getCode() . '|' .
                $e->getMessage() . ')',
                Analog::ALERT
            );
            return $e;
        }
    }

    /**
     * Drop test table if it exists, so we can make all checks.
     *
     * @return void
     */
    public function dropTestTable()
    {
        try {
            $this->db->query('DROP TABLE IF EXISTS galette_test');
            Analog::log('Test table successfully dropped.', Analog::DEBUG);
        } catch (\Exception $e) {
            Analog::log(
                'Cannot drop test table! ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Checks GRANT access for install time
     *
     * @param char $mode are we at install time (i) or update time (u) ?
     *
     * @return array containing each test. Each array entry could
     *           be either true or contains an exception of false if test did not
     *           ran.
     */
    public function grantCheck($mode = 'i')
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
        } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
                    throw new \Exception('No row inserted!');
                }
            } catch (\Exception $e) {
                Analog::log(
                    'Cannot INSERT records | ' .$e->getMessage(),
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
                        throw new \Exception('No row updated!');
                    }
                } catch (\Exception $e) {
                    Analog::log(
                        'Cannot UPDATE records | ' .$e->getMessage(),
                        Analog::WARNING
                    );
                    $results['update'] = $e;
                }

                //can Galette SELECT records ?
                try {
                    $pass = false;
                    $select = $this->sql->select('galette_test');
                    $select->where('test_id = 1');
                    $res = $this->execute($select);
                    $pass = $res->count() === 1;

                    if ($pass) {
                        $results['select'] = true;
                    } else {
                        throw new \Exception('Select is empty!');
                    }
                } catch (\Exception $e) {
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
                } catch (\Exception $e) {
                    Analog::log(
                        'Cannot DELETE records | ' .$e->getMessage(),
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
            } catch (\Exception $e) {
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
     * @param string $prefix Specified table prefix, PREFIX_DB if null
     *
     * @return array
     */
    public function getTables($prefix = null)
    {
        $metadata = new \Zend\Db\Metadata\Metadata($this->db);
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
     * @return array
     */
    public function getColumns($table)
    {
        $metadata = new \Zend\Db\Metadata\Metadata($this->db);
        $table = $metadata->getTable(PREFIX_DB . $table);
        return $table->getColumns();
    }

    /**
     * Converts recursively database to UTF-8
     *
     * @param string  $prefix       Specified table prefix
     * @param boolean $content_only Proceed only content (no table conversion)
     *
     * @return void
     */
    public function convertToUTF($prefix = null, $content_only = false)
    {
        if ($prefix === null) {
            $prefix = PREFIX_DB;
        }

        try {
            $this->connection->beginTransaction();

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
                        'Charset successfully changed for table `' . $table .'`',
                        Analog::DEBUG
                    );
                }

                //Data conversion
                if ($table != $prefix . 'pictures') {
                    $this->convertContentToUTF($prefix, $table);
                }
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            Analog::log(
                'An error occured while converting to utf table ' .
                $table . ' (' . $e->getMessage() . ')',
                Analog::ERROR
            );
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
    private function convertContentToUTF($prefix, $table)
    {

        try {
            $query = 'SET NAMES latin1';
            $this->db->query(
                $query,
                Adapter::QUERY_MODE_EXECUTE
            );
        } catch (\Exception $e) {
            Analog::log(
                'Cannot SET NAMES on table `' . $table . '`. ' .
                $e->getMessage(),
                Analog::ERROR
            );
        }

        try {
            $metadata = new \Zend\Db\Metadata\Metadata($this->db);
            $tbl = $metadata->getTable($table);
            $columns = $tbl->getColumns();
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
                    throw new \Exception(
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
                    $where[] = $k . ' = "' . $row->$k .'"';
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
        } catch (\Exception $e) {
            Analog::log(
                'An error occured while converting contents to UTF-8 for table ' .
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
    public function isPostgres()
    {
        return $this->type_db === self::PGSQL;
    }

    /**
     * Instanciate a select query
     *
     * @param string $table Table name, without prefix
     * @param string $alias Tables alias, optionnal
     *
     * @return Select
     */
    public function select($table, $alias = null)
    {
        if ($alias === null) {
            return $this->sql->select(
                PREFIX_DB . $table
            );
        } else {
            return $this->sql->select(
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
    public function insert($table)
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
     * @return Insert
     */
    public function update($table)
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
    public function delete($table)
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
     * @return Stmt
     */
    public function execute($sql)
    {
        try {
            $query_string = $this->sql->getSqlStringForSqlObject($sql);
            $this->_last_query = $query_string;
            Analog::log(
                'Executing query: ' . $query_string,
                Analog::DEBUG
            );
            return $this->db->query(
                $query_string,
                Adapter::QUERY_MODE_EXECUTE
            );
        } catch (\Exception $e) {
            $msg = 'Query error: ';
            if (isset($query_string)) {
                $msg .= $query_string;
            }
            Analog::log(
                $msg . ' ' . $e->__toString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the variable we want to retrieve
     *
     * @return mixed
     */
    public function __get($name)
    {
        switch ($name) {
            case 'db':
                return $this->db;
                break;
            case 'sql':
                return $this->sql;
                break;
            case 'driver':
                return $this->db->getDriver();
                break;
            case 'connection':
                return $this->db->getDriver()->getConnection();
                break;
            case 'platform':
                return $this->db->getPlatform();
                break;
            case 'query_string':
                return $this->_last_query;
                break;
            case 'type_db':
                return $this->type_db;
                break;
        }
    }

    /**
     * Get database informations
     *
     * @return array
     */
    public function getInfos()
    {
        $infos = [
            'engine'    => null,
            'version'   => null,
            'size'      => null,
            'log_size'  => null,
            'sql_mode'  => null
        ];

        if ($this->isPostgres()) {
            $infos['engine'] = 'PostgreSQL';
            $sql = 'SHOW server_version';
            $result = $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();
            $infos['version'] = $result['server_version'];

            $total_size = 0;
            $db_size    = 0;

            $sql = 'SELECT pg_database_size(\'' . NAME_DB . '\')';
            $result = $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();
            $infos['size']          = round($result['pg_database_size'] / 1024 / 1024, 1);
        } else {
            $sql = 'SELECT @@sql_mode as mode, @@version AS version, @@version_comment AS version_comment';
            $result = $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();

            $infos['engine']    = $result['version_comment'];
            $infos['version']   = $result['version'];
            $infos['sql_mode']  = $result['mode'];

            $size_sql = 'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS dbsize' .
                ' FROM information_schema.tables WHERE table_schema="' . NAME_DB .'"';
            $result = $this->db->query($size_sql, Adapter::QUERY_MODE_EXECUTE)
                ->current();

            $infos['size']      = $result['dbsize'];
        }

        return $infos;
    }
}
