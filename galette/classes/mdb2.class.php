<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PEAR::MDB2 wrapper
 *
 * PHP version 5
 *
 * Copyright © 2007-2011 The Galette Team
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
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-09-05
 */

/**
* We define here include path
* for Galette to include embedded MDB2 and PEAR
*/
require_once 'MDB2.php';
require_once 'i18n.class.php';

/**
 * PEAR::MDB2 wrapper
 *
 * @category  Classes
 * @name      GaletteMdb2
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://pear.php.net/package/MDB2
 * @since     Available since 0.7dev - 2007-09-05
 */
class GaletteMdb2
{
    private $_persistent;
    private $_dsn_array;
    private $_dsn;
    private $_options;
    private $_db;
    private $_error;

    /**
    * Main constructor
    *
    * @param bool $persistent defines a persistent database connection.
    *                           Defaults to false
    */
    function __construct($persistent = false)
    {
        global $log;
        $this->_persistent = $persistent;
        $this->_dsn = TYPE_DB . '://' . USER_DB . ':' . PWD_DB . '@' .
            HOST_DB . '/' . NAME_DB;
        $this->_options = array(
            'persistent'  => $persistent,
            'debug'       => 2,
            'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
        );
        $this->_dsn_array = MDB2::parseDSN($this->_dsn);

        $this->_db = MDB2::connect($this->_dsn, $this->_options);

        if ( MDB2::isError($this->_db) ) {
            $log->log(
                'MDB2 : no connexion (' . $this->_db->getMessage() . ') - ' .
                $this->_db->getDebugInfo(),
                PEAR_LOG_ALERT
            );
        } else {
            $log->log('MDB2 : connected successfully', PEAR_LOG_INFO);
        }

        $this->_db->setFetchMode(MDB2_FETCHMODE_OBJECT);
        $this->_db->loadModule('Manager');
        $this->_db->loadModule('Reverse');
    }

    /**
    * Disconnects properly from the database when this object is unset
    *
    * @return void
    */
    function __destruct()
    {
        $this->_db->disconnect();
    }

    /**
    * Queries the database
    *
    * @param string $query the query to execute
    *
    * @return either MDB2 resultset if query was successfull,
    *           MDB2 error object if something goes wrong
    */
    public function query( $query )
    {
        global $log;
        $result = $this->_db->query($query);
        // we log a warn if query fails, and an debug if it succeed
        if ( MDB2::isError($result) ) {
            $log->log(
                'There were an error executing query ' . $query . '(' .
                $result->getMessage() . ') - ' . $result->getDebugInfo(),
                PEAR_LOG_WARNING
            );
        } else {
            $log->log('Query successfull : ' . $query, PEAR_LOG_DEBUG);
        }
        //anyways, we return $result (either mdb2's error or mdb2's resultset)
        return $result;
    }

    /**
    * Exectue a query on the database (ie. insert, update, delete)
    *
    * @param string $query the query to execute
    *
    * @return either MDB2 resultset if query was successfull,
    *           MDB2 error object if something goes wrong
    */
    public function execute( $query )
    {
        global $log;
        $result = $this->_db->exec($query);
        // we log a warn if query fails, and an debug if it succeed
        if ( MDB2::isError($result) ) {
            $log->log(
                'There were an error executing query ' . $query . '(' .
                $result->getMessage() . ') - ' . $result->getDebugInfo(),
                PEAR_LOG_ERR
            );
        } else {
            $log->log('Query successfull : ' . $query, PEAR_LOG_DEBUG);
        }
        //anyways, we return $result (either mdb2's error or mdb2's resultset)
        return $result;
    }

    /** TODO: handle data types */
    /**
    * Insert a new record in the database
    *
    * @param string $table  name of the table
    * @param array  $fields array of fields names
    * @param array  $values array of values to insert for each field
    * @param array  $types  data type for each field (optionnal)
    *
    * @return int|MDB2Error
    */
    public function insertInto($table, $fields, $values, $types = null)
    {
        /** FIXME : log an error if array have different sizes */
        $requete = 'INSERT INTO ' . $this->_db->quoteIdentifier($table);
        //traitement des champs
        $requete .= ' (';
        foreach ( $fields as &$value ) {
            $value = $this->_db->quoteIdentifier($value);
        }
        $requete .= implode(', ', $fields);
        $requete .= ')';
        //traitement des valeurs
        $requete .= ' VALUES(';
        foreach ( $values as &$value ) {
            $value = $this->_db->quote($value);
        }
        $requete .= implode(', ', $values);
        $requete .= ')';

        $result = $this->_db->query($requete);
        return $result;
    }

    /** TODO: handle data types */
    /**
    * Update an existing record
    *
    * @param string $table  name of the table
    * @param array  $fields array of fields values
    * @param array  $values array of values to update
    * @param string $where  where clause on which update should be run (optionnal)
    * @param array  $types  data type for each field (optionnal)
    *
    * @return int|MDB2Error
    */
    public function update($table, $fields, $values, $where=null, $types = null)
    {
        /** FIXME : log an error if array have different sizes */
        $requete = 'UPDATE ' . $this->_db->quoteIdentifier($table) . ' SET ';

        for ( $i = 0 ; $i < count($fields); $i++ ) {
            $requete .= $this->_db->quoteIdentifier($fields[$i]) . '=' .
                $this->_db->quote($values[$i]);
            if ( $i < count($fields)-1 ) {
                $requete .= ', ';
            }
        }

        if ( $where != null ) {
            $requete .= 'WHERE ' . $where;
        }

        $result = $this->_db->query($requete);
        return $result;
    }

    /**
    * Wrapper to MDB2 quote
    *
    * @param string $value which value to quote
    *
    * @return string quoted value
    */
    public function quote($value)
    {
        return $this->_db->quote($value);
    }

    /**
    * Wrapper to MDB2 quoteIdentifier
    *
    * @param string $value which identifier to quote
    *
    * @return string quoted identifier
    */
    public function quoteIdentifier($value)
    {
        return $this->_db->quoteIdentifier($value);
    }

    /**
    * Wrapper to MDB2 escape
    *
    * @param string $value to escape
    *
    * @return string escaped value
    */
    public function escape($value)
    {
        return $this->_db->escape($value);
    }

    /**
    * Test if database can be contacted. Mostly used for installation
    *
    * @param string $type db type
    * @param string $user database's user
    * @param stirng $pass password for the user
    * @param stirng $host which host we want to connect to
    * @param string $db   database name
    *
    * @return true|array true if connection was successfull, an array with some infos otherwise
    */
    public static function testConnectivity($type, $user, $pass, $host, $db)
    {
        $dsn = $type . '://' . $user . ':' . $pass . '@' . $host . '/' . $db;
        $options = array(
            'persistent'    =>    false,
            'debug'        =>    2,
            'portability'    =>
                MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
        );

        $db = MDB2::connect($dsn, $options);

        if ( MDB2::isError($db) ) {
            $ret = array(
                'main'    =>    $db->getMessage(),
                'debug'    =>    $db->getDebugInfo()
            );
            return $ret;
        } else {
            $db->disconnect();
            return true;
        }
    }

    /**
    * Get a MDB otpions
    *
    * @param string $arg the option
    *
    * @return the required optpion
    */
    public function getOption($arg)
    {
        return $this->_db->getOption($arg);
    }

    /**
    * Prepares a query for multiple execution with execute().
    * With some database backends, this is emulated.
    * prepare() requires a generic query as string like
    * 'INSERT INTO numbers VALUES(?,?)' or
    * 'INSERT INTO numbers VALUES(:foo,:bar)'.
    * The ? and :[a-zA-Z] and  are placeholders which can be set using
    * bindParam() and the query can be send off using the execute() method.
    *
    * @param string $query        the query to prepare
    * @param mixed  $types        array that contains the types of the placeholders
    * @param mixed  $result_types array that contains the types of the columns in
    *                               the result set or MDB2_PREPARE_RESULT,
    *                               if set to MDB2_PREPARE_MANIP the query is
    *                               handled as a manipulation query
    * @param mixed  $lobs         key (field) value (parameter) pair for all
    *                               lob placeholders
    *
    * @return mixed resource handle for the prepared query on success, a MDB2
    *        error on failure
    * @access public
    * @see bindParam, execute
    */
    public function prepare(
        $query, $types = null, $result_types = null, $lobs = array()
    ) {
        return $this->_db->prepare($query, $types, $result_types, $lobs);
    }

    /**
    * List all tables in the cuirrent database
    *
    * @param bool $local when true, list will only contains
    *               table are PREFIX_DB prefixed
    *
    * @return mixed array of table names on success, a MDB2 error on failure
    */
    public function listTables($local = true)
    {
        $list = array();
        if ( $local ) {
            foreach ( $this->_db->listTables() as $v ) {
                if ( substr_compare($v, PREFIX_DB, 0, strlen(PREFIX_DB)) === 0 ) {
                    $list[] = $v;
                }
            }
        } else {
            $list = $this->_db->listTables();
        }

        return $list;
    }

    /**
    * Test DROP TABLE privileges
    *
    * @return array exaplaining error if test fails, ResultSet otherwise
    */
    public function testDropTable()
    {
        global $log;
        $result = $this->_db->dropTable('galette_test');

        if (MDB2::isError($result)) {
            $log->log('Unable to drop test table.', PEAR_LOG_WARNING);
            $ret = array(
                'main'    =>    $result->getMessage(),
                'debug'    =>    $result->getDebugInfo()
            );
            return $ret;
        } else {
            $log->log('Test table successfully dropped.', PEAR_LOG_DEBUG);
            return $result;
        }
    }

    /**
    * Checks GRANT access for install time
    *
    * @param char $mode are we at install time (i) or update time (u) ?
    *
    * @return array containing each test. Each array entry could
    *           be either MDB2_OK or MDB2_ERROR
    */
    public function grantCheck($mode = 'i')
    {
        //This method should not catch more than warning log messages
        //since errors displaying is handled at install/index.php
        global $log;
        $log->log('Check for database rights', PEAR_LOG_DEBUG);
        $stop = false;
        $results = array(
            'create'    =>    false,
            'insert'    =>    false,
            'select'    =>    false,
            'update'    =>    false,
            'alter'        =>    false,
            'delete'    =>    false,
            'drop'        =>    false
        );

        //can Galette CREATE tales ?
        $fields = array(
            'test_id' => array(
                'type'       => 'integer',
                'unsigned'   => true,
                'notnull'    => true,
                'default'    => 0,
            ),
            'test_text'      => array(
                'type'       => 'text',
                'length'     => 20,
            ),
            'test_boolean'   => array(
                'type'       => 'boolean',
            ),
            'test_decimal'   => array(
                'type'       => 'decimal',
            ),
            'test_float'     => array(
                'type'       => 'float',
            ),
            'test_date'      => array(
                'type'       => 'date',
            ),
            'test_time'      => array(
                'type'       => 'time',
            ),
            'test_timestamp' => array(
                'type'       => 'timestamp',
            ),
        );

        $result = $this->_db->manager->createTable('galette_test', $fields);

        if ( MDB2::isError($result) ) {
            $create = $result;
            $stop = true;
            $log->log('Cannot CREATE TABLE', PEAR_LOG_WARNING);
        } else {
            $create = MDB2_OK;
        }
        $results['create'] = $create;

        if ( !$stop ) {
            //can Galette INSERT records ?
            $fields = array(
                'test_id',
                'test_text',
                'test_boolean',
                'test_decimal',
                'test_float',
                'test_date',
                'test_time',
                'test_timestamp'
            );
            $values = array(
                1,
                'a simple text',
                true,
                12,
                '1.3',
                '2007-05-29',
                '12:12:00',
                '1980-05-29 12:12:00'
            );
            $result = $this->insertInto('galette_test', $fields, $values);
            if ( MDB2::isError($result) ) {
                $insert = $result;
                $stop = true;
                $log->log('Cannot INSERT records', PEAR_LOG_WARNING);
            } else {
                $insert = MDB2_OK;
            }
            $results['insert'] = $insert;
        }

        if ( !$stop ) {
            //can Galette UPDATE records ?
            $fields = array(
                'test_text',
                'test_float',
                'test_timestamp'
            );
            $values = array(
                'another simple text',
                '3.1',
                '1979-11-27 11:30:05'
            );
            $result = $this->update('galette_test', $fields, $values);
            if ( MDB2::isError($result) ) {
                $update = $result;
                $stop = true;
                $log->log('Cannot UPDATE records', PEAR_LOG_WARNING);
            } else {
                $update = MDB2_OK;
            }
            $results['update'] = $update;
        }

        if ( !$stop ) {
            //can Galette SELECT records ?
            $requete = 'SELECT '
                 . $this->_db->quoteIdentifier('test_id') . ', '
                 . $this->_db->quoteIdentifier('test_boolean') . ', '
                 . $this->_db->quoteIdentifier('test_date') . ' FROM '
                 . $this->_db->quoteIdentifier('galette_test');

            $result = $this->_db->query($requete);
            if ( MDB2::isError($result) ) {
                $select = $result;
                $stop = true;
                $log->log('Cannot SELECT records', PEAR_LOG_WARNING);
            } else {
                $select = MDB2_OK;
            }
            $results['select'] = $select;
        }

        if ( !$stop && $mode == 'u' ) {
            //can Galette ALTER tables ?
            $alter = array(
                'add' => array(
                    'test_add'    =>    array(
                                    'type'    =>    'text'
                                )
                    )
            );
            $result = $this->_db->manager->alterTable('galette_test', $alter, false);
            if ( MDB2::isError($result) ) {
                $alter = $result;
                $stop = true;
                $log->log('Cannot ALTER TABLE', PEAR_LOG_WARNING);
            } else {
                $alter = MDB2_OK;
            }
            $results['alter'] = $alter;
        }

        if ( !$stop ) {
            //can Galette DELETE records ?
            $requete = 'DELETE FROM ' . $this->_db->quoteIdentifier('galette_test');

            $result = $this->_db->query($requete);
            if ( MDB2::isError($result) ) {
                $delete = $result;
                $stop = true;
                $log->log('Cannot DELETE records', PEAR_LOG_WARNING);
            } else {
                $delete = MDB2_OK;
            }
            $results['delete'] = $delete;
        }

        if ( !$stop ) {
            //can Galette DROP tables ?
            $result = $this->_db->dropTable('galette_test');

            if ( MDB2::isError($result) ) {
                $drop = $result;
                $stop = true;
                $log->log('Cannot DROP TABLE', PEAR_LOG_WARNING);
            } else {
                $drop = MDB2_OK;
            }
            $results['drop'] = $drop;
        }

        return $results;
    }

    /**
    * Converts recursively database to UTF-8
    *
    * @return void
    */
    public function convertToUTF()
    {
        global $log;
        $this->_db->loadModule('Reverse');

        $all_tables = $this->_db->listTables();
        $tables = array();
        $queries = array();

        // check for prefix in table name, so we keep only galette's tables
        for ($i = 0 ; $i < count($all_tables); $i++) {
            if ( strstr($all_tables[$i], PREFIX_DB) ) {
                $tables[] = $all_tables[$i];
            }
        }

        foreach ($tables as $table) {
            // in MDB2 2.5.0, a method alterDatabase should have been added
            //Change whole table charset
            $query = 'ALTER TABLE ' . $this->quoteIdentifier($table) .
                ' DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci';
            if ( !$result = $this->_db->query($query) ) {
                $log->log(
                    'Cannot change charset for table `' . $table .
                    '`, data and fields will not be updated. Try to fix the ' .
                    'problem, and run this script again.',
                    PEAR_LOG_ERR
                );
            } else {
                $log->log(
                    'Charset successfully changed for table `' . $table .'`',
                    PEAR_LOG_DEBUG
                );
                $fields = $this->_db->listTableFields($table);

                $fields_types = array();

                //change charset for each relevant field from the table
                foreach ($fields as $field) {
                    $fdef = $this->_db->reverse->getTableFieldDefinition(
                        $table, $field
                    );
                    $proceed_data_convert = false;

                    /** FIXME: which datatypes should have a bad encoding value ?  */
                    $fields_types[$field] = $fdef[0]['mdb2type'];
                    if ( $fdef[0]['mdb2type'] == 'text' ) {
                        $definitions = array();
                        $definitions['type'] = $fdef[0]['mdb2type'];
                        if ( isset($fdef[0]['length']) ) {
                            $definitions['length'] = $fdef[0]['length'];
                        }
                        if ( $fdef[0]['notnull'] == 1 ) {
                            $definitions['notnull'] = true;
                        }
                        //To handle DEFAULT ''
                        if (   $fdef[0]['notnull'] == 1
                            && $fdef[0]['default'] == ''
                            && $fdef[0]['nativetype'] != 'text'
                        ) {
                            $definitions['default'] = '';
                        } elseif ( $fdef[0]['default'] ) {
                            $definitions['default'] = $fdef[0]['default'];
                        }
                        if ( $fdef[0]['fixed'] ) {
                            $definitions['fixed'] = $fdef[0]['fixed'];
                        }
                        $definitions['charset'] = 'utf8';
                        $definitions['collate'] = 'utf8_unicode_ci';

                        $alter = array(
                            'change' => array(
                                $field    =>    array(
                                    'definition'    =>    $definitions
                                )
                            )
                        );
                        $result = $this->_db->manager->alterTable(
                            $table, $alter, false
                        );
                        if ( MDB2::isError($result) ) {
                            $log->log(
                                'Cannot ALTER TABLE `' . $table .
                                '` (working on field `' . $field . '`)',
                                PEAR_LOG_ERR
                            );
                        } else {
                            $proceed_data_convert = true;
                            $log->log(
                                'Charset for field `' . $field . '` from table `' .
                                $table . '` successfully updated.',
                                PEAR_LOG_DEBUG
                            );
                        }
                    }
                }

                //Data conversion
                if ( $table != PREFIX_DB . 'pictures' && $proceed_data_convert ) {
                    $this->_convertContentToUTF($table, $fields_types);
                }
            }
        }
    }

    /**
    * Converts dtabase content to UTF-8
    *
    * @param string $table        the table we want to convert datas from
    * @param array  $fields_types fields type of that table
    *
    * @return string encoded content
    */
    private function _convertContentToUTF($table, $fields_types)
    {
        global $log;
        $content="";
        $query = 'SET NAMES latin1';

        if ( !$result = $this->_db->query($query) ) {
            $log->log(
                'Cannot SET NAMES on table table `' . $table . '`.',
                PEAR_LOG_ERR
            );
        } else {
            $query = 'SELECT * FROM ' . $this->quoteIdentifier($table);
            if ( !$result = $this->_db->query($query) ) {
                $log->log(
                    'Cannot retrieve data from table `' . $table . '`.',
                    PEAR_LOG_ERR
                );
            } else {
                $table_info = $this->_db->reverse->tableInfo($table);
                $constraints = $this->_db->reverse->getTableConstraintDefinition(
                    $table, 'primary'
                );
                $r = $result->fetchAll();
                foreach ( $r as $row ) {
                    $requete = 'UPDATE ' . $this->quoteIdentifier($table) . ' SET ';
                    foreach ( $row as $key => $value ) {
                        $requete .= $key . '=';
                        $requete .= $this->_db->quote(
                            (( !I18n::seemsUtf8($value) ) ? utf8_encode($value) : $value),
                            $fields_types[$key]
                        );
                        $requete .= ', ';
                    }
                    $requete = rtrim($requete, ', ');
                    $requete .= ' WHERE ';

                    foreach ( $constraints as $constr_key => $constr_value ) {
                        if ( $constr_key == 'fields' ) {

                            $c = array_keys($constr_value);
                            foreach ( $c as $cf ) {
                                $requete .= $cf . '=' . $row->$cf;
                            }
                        }
                    }

                    $result = $this->execute($requete);
                    if (MDB2::isError($result)) {
                        $log->log(
                            'Error while converting data ' . $result->getMessage() .
                            '(' . $result->getDebugInfo() . ') - query: ' . $requete,
                            PEAR_LOG_ERR
                        );
                        //return false;
                    }
                }
            }
        }
        return $content;
    }

    /**
    * Get MDB2 object
    *
    * @return the real MDB2 object
    */
    public function getDb()
    {
        return $this->_db;
    }

}
?>
