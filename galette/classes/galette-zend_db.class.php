<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Zend_Db wrapper
 *
 * PHP version 5
 *
 * Copyright © 2011 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-27
 */

require_once 'Zend/Db.php';
require_once 'i18n.class.php';

/**
 * Zend_Db wrapper
 *
 * @category  Classes
 * @name      GaletteZendDb
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://framework.zend.com/apidoc/core/_Db.html#\Zend_Db
 * @since     Available since 0.7dev - 2011-07-27
 */
class GaletteZendDb extends Zend_Db
{
    private $_persistent;
    private $_dsn_array;
    private $_dsn;
    private $_options;
    private $_db;
    private $_error;

    const MYSQL_DEFAULT_PORT = 3306;
    const PGSQL_DEFAULT_PORT = 5432;

    /**
    * Main constructor
    */
    function __construct()
    {
        global $log;

        $_type = null;
        try {
            if ( TYPE_DB === 'mysql' ) {
                $_type = 'Pdo_Mysql';
            } else if ( TYPE_DB === 'pgsql' ) {
                $_type = 'Pdo_Pgsql';
            } else {
                throw new Exception;
            }

            $this->_db = Zend_Db::factory(
                $_type,
                array(
                    'host'     => HOST_DB,
                    'port'     => PORT_DB,
                    'username' => USER_DB,
                    'password' => PWD_DB,
                    'dbname'   => NAME_DB
                )
            );
            $this->_db->getConnection();
            $this->_db->setFetchMode(Zend_Db::FETCH_OBJ);
            $log->log(
                '[ZendDb] Database connection was successfull!',
                PEAR_LOG_DEBUG
            );
        } catch (Zend_Db_Adapter_Exception $e) {
            // perhaps a failed login credential, or perhaps the RDBMS is not running
            $ce = $e->getChainedException();
            $log->log(
                '[ZendDb] No connexion (' . $ce->getCode() . '|' .
                $ce->getMessage() . ')',
                PEAR_LOG_ALERT
            );
            throw $e;
        } catch (Exception $e) {
            // perhaps factory() failed to load the specified Adapter class
            $log->log(
                '[ZendDb] Error (' . $e->getCode() . '|' .
                $e->getMessage() . ')',
                PEAR_LOG_ALERT
            );
            throw $e;
        }
    }

    /**
     * Default class destructor
     */
    function __destruct()
    {
        $this->db->closeConnection();
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
        return $this->_db->fetchAll('SELECT * FROM ' . $table);
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
    * @return true|array true if connection was successfull, an array with some infos otherwise
    */
    public static function testConnectivity($type, $user, $pass, $host, $port, $db)
    {
        global $log;

        $_type = null;
        try {
            if ( $type === 'mysql' ) {
                $_type = 'Pdo_Mysql';
            } else if ( $type === 'pgsql' ) {
                $_type = 'Pdo_Pgsql';
            } else {
                throw new Exception;
            }

            $_db = Zend_Db::factory(
                $_type,
                array(
                    'host'     => $host,
                    'port'     => $port,
                    'username' => $user,
                    'password' => $pass,
                    'dbname'   => $db
                )
            );
            $_db->getConnection();
            $_db->setFetchMode(Zend_Db::FETCH_OBJ);
            $_db->closeConnection();
            $log->log(
                '[' . __METHOD__ . '] Database connection was successfull!',
                PEAR_LOG_DEBUG
            );
            return true;
        } catch (Zend_Db_Adapter_Exception $e) {
            // perhaps a failed login credential, or perhaps the RDBMS is not running
            $ce = $e->getChainedException();
            $log->log(
                '[' . __METHOD__ . '] No connexion (' . $ce->getCode() . '|' .
                $ce->getMessage() . ')',
                PEAR_LOG_ALERT
            );
            return $e;
        } catch (Exception $e) {
            // perhaps factory() failed to load the specified Adapter class
            $log->log(
                '[' . __METHOD__ . '] Error (' . $e->getCode() . '|' .
                $e->getMessage() . ')',
                PEAR_LOG_ALERT
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
        global $log;

        try {
            $this->_db->query('DROP TABLE IF EXISTS galette_test');
            $log->log('Test table successfully dropped.', PEAR_LOG_DEBUG);
        } catch (Exception $e) {
            $log->log(
                'Cannot drop test table! ' . $e->getMessage(),
                PEAR_LOG_WARNING
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
        global $log;

        $log->log(
            'Check for database rights (mode ' . $mode . ')',
            PEAR_LOG_DEBUG
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
        if ( $mode === 'u' ) {
            $results['alter'] = false;
        }

        //can Galette CREATE tables?
        try {
            $sql = 'CREATE TABLE galette_test (
                test_id INTEGER NOT NULL,
                test_text VARCHAR(20)
            )';
            $this->_db->getConnection()->exec($sql);
            $results['create'] = true;
        } catch (Exception $e) {
            $log->log('Cannot CREATE TABLE', PEAR_LOG_WARNING);
            //if we cannot create tables, we cannot check other permissions
            $stop = true;
            $results['create'] = $e;
        }

        //all those tests need the table to exists
        if ( !$stop ) {
            if ( $mode == 'u' ) {
                //can Galette ALTER tables? (only for update mode)
                try {
                    $sql = 'ALTER TABLE galette_test ALTER test_text SET DEFAULT \'nothing\'';
                    $this->_db->getConnection()->exec($sql);
                    $results['alter'] = true;
                } catch (Exception $e) {
                    $log->log(
                        'Cannot ALTER TABLE | ' . $e->getMessage(),
                        PEAR_LOG_WARNING
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
                $res = $this->_db->insert(
                    'galette_test',
                    $values
                );
                if ( $res === 1 ) {
                    $results['insert'] = true;
                } else {
                    throw new Exception('No row inserted!');
                }
            } catch (Exception $e) {
                $log->log(
                    'Cannot INSERT records | ' .$e->getMessage(),
                    PEAR_LOG_WARNING
                );
                //if we cannot insert records, some others tests cannot be done
                $stop = true;
                $results['insert'] = $e;
            }

            //all those tests need that the first record exists
            if ( !$stop ) {
                //can Galette UPDATE records ?
                $values = array(
                    'test_text' => 'another simple text'
                );
                try {
                    $res = $this->_db->update(
                        'galette_test',
                        $values,
                        array('test_id = ?' => 1)
                    );
                    if ( $res === 1 ) {
                        $results['update'] = true;
                    } else {
                        throw new Exception('No row updated!');
                    }
                } catch (Exception $e) {
                    $log->log(
                        'Cannot UPDATE records | ' .$e->getMessage(),
                        PEAR_LOG_WARNING
                    );
                    $results['update'] = $e;
                }

                //can Galette SELECT records ?
                try {
                    $select = new Zend_Db_Select($this->_db);
                    $select->from('galette_test')
                        ->where('test_id = ?', 1);
                    $res = $select->query()->fetchAll();
                    if ( count($res) === 1 ) {
                        $results['select'] = true;
                    } else {
                        throw new Exception('Select is empty!');
                    }
                } catch (Exception $e) {
                    $log->log(
                        'Cannot SELECT records | ' . $e->getMessage(),
                        PEAR_LOG_WARNING
                    );
                    $results['select'] = $e;
                }

                //can Galette DELETE records ?
                try {
                    $this->_db->delete(
                        'galette_test',
                        array('test_id = ?' => 1)
                    );
                    $results['delete'] = true;
                } catch (Exception $e) {
                    $log->log(
                        'Cannot DELETE records | ' .$e->getMessage(),
                        PEAR_LOG_WARNING
                    );
                    $results['delete'] = $e;
                }
            }

            //can Galette DROP tables ?
            try {
                $sql = 'DROP TABLE galette_test';
                $this->_db->getConnection()->exec($sql);
                $results['drop'] = true;
            } catch (Exception $e) {
                $log->log(
                    'Cannot DROP TABLE | ' . $e->getMessage(),
                    PEAR_LOG_WARNING
                );
                $results['drop'] = $e;
            }
        }

        return $results;
    }

    /**
     * Get a list of Galette's tables
     *
     * @return array
     */
    public function getTables()
    {
        $tmp_tables_list = $this->db->listTables();

        $tables_list = array();
        //filter table_list: we only want PREFIX_DB tables
        foreach ( $tmp_tables_list as $t ) {
            if ( preg_match('/^' . PREFIX_DB . '/', $t) ) {
                $tables_list[] = $t;
            }
        }
        return $tables_list;
    }

    * Global getter method
    *
    * @param string $name name of the variable we want to retrieve
    *
    * @return mixed
    */
    public function __get($name)
    {
        switch ( $name ) {
        case 'db':
            return $this->_db;
        }
    }

}
?>
