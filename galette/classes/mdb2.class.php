<?php

// Copyright © 2007-2008 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * galette_mdb2.class.php, 05 septembre 2007
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 */

/**
* We define here include path
* for Galette to include embedded MDB2 and PEAR
*/
require_once('MDB2.php');

/**
 * PEAR::MDB2 wrapper class for galette
 *
 * @name	galette_mdb2
 * @package	Galette
 * @link	http://pear.php.net/package/MDB2
 *
 */
class GaletteMdb2{
	private $last_res_id = 0;
	private $persistent;
	private $dsn_array;
	private $dsn;
	private $options;
	private $db;
	private $error;

	function __construct($persistent = false){
		global $log;
		$this->persistent = $persistent;
		$this->dsn = TYPE_DB . '://' . USER_DB . ':' . PWD_DB . '@' . HOST_DB . '/' . NAME_DB;
		$this->options = array(
			'persistent'	=>	$persistent,
			'debug'		=>	2,
			'portability'	=>	MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
		);
		$this->dsn_array = MDB2::parseDSN($this->dsn);

		$this->db = MDB2::connect($this->dsn, $this->options);

		if (MDB2::isError($this->db)) {
			$log->log('MDB2 : no connexion (' . $this->db->getMessage() . ') - ' . $this->db->getDebugInfo(), PEAR_LOG_ALERT);
		}else
			$log->log('MDB2 : connected successfully', PEAR_LOG_INFO);

		$this->db->setFetchMode(MDB2_FETCHMODE_OBJECT);
		$this->db->loadModule('Manager');
		$this->db->loadModule('Reverse');
	}

	/**
	* Disconnects properly from the database when this object is unset
	*/
	function __destruct(){
		$this->db->disconnect();
	}

	/**
	* Queries the database
	* @param query the query to execute
	*/
	public function query( $query ){
		global $log;
		$result = $this->db->query($query);
				// Vérification des erreurs
		if (MDB2::isError($result)) {
			$this->error = $result;
			$log->log('There were an error executing query ' . $query . '(' . $result->getMessage() . ') - ' . $result->getDebugInfo(), PEAR_LOG_WARNING);
			return -1;
		}else{
			$log->log('Query successfull : ' . $query, PEAR_LOG_DEBUG);
			return $result;
		}
	}

	/**
	* Exectue a query on the database (ie. insert, update, delete)
	* FIXME: ! This function seems to be absent from mysqli driver !
	* @param query the query to execute
	*/
	public function execute( $query ){
		global $log;
		//$this->db->setCharset('UTF-8');
		$result = $this->db->exec($query);
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			$log->log('There were an error executing query ' . $query . '(' . $result->getMessage() . ') - ' . $result->getDebugInfo(), PEAR_LOG_ERR);
			return $result;
		}else{
			$log->log('Query successfull : ' . $query, PEAR_LOG_DEBUG);
			return $result;
		}
	}

	/**
	* Insert a new record in the database
	* @param table name of the table
	* @param fields array of fields names
	* @param values array of values to insert for each field
	* @param types data type for each field (optionnal)
	*/
	/** TODO: handle data types */
	public function insertInto($table, $fields, $values, $types = null){
		/** FIXME : log an error if array have different sizes */
		$requete = 'INSERT INTO ' . $this->db->quoteIdentifier($table);
		//traitement des champs
		$requete .= ' (';
		foreach($fields as &$value)
			$value = $this->db->quoteIdentifier($value);
		$requete .= implode(', ', $fields);
		$requete .= ')';
		//traitement des valeurs
		$requete .= ' VALUES(';
		foreach($values as &$value)
			$value = $this->db->quote($value);
		$requete .= implode(', ', $values);
		$requete .= ')';

		$result = $this->db->query($requete);
		return $result;
	}

	/**
	* Update an existing record
	* @param table name of the table
	* @param fields array of fields values
	* @param values array of values to update
	* @param where where clause on which update should be executed (optionnal)
	* @param types data type for each field (optionnal)
	*/
	/** TODO: handle data types */
	public function update($table, $fields, $values, $where=null, $types = null){
		/** FIXME : log an error if array have different sizes */
		$requete = 'UPDATE ' . $this->db->quoteIdentifier($table) . ' SET ';

		for( $i = 0 ; $i < count($fields) ; $i++){
			$requete .= $this->db->quoteIdentifier($fields[$i]) . '=' . $this->db->quote($values[$i]);
			if( $i < count($fields)-1 ) $requete .= ', ';
		}

		if($where != null)
			$requete .= 'WHERE ' . $where;

		$result = $this->db->query($requete);
		return $result;
	}

	/**
	* Wrapper to MDB2 quote
	* @param value which value to quote
	*/
	public function quote($value){
		return $this->db->quote($value);
	}

	/**
	* Wrapper to MDB2 quoteIdentifier
	* @param value which identifier to quote
	*/
	public function quoteIdentifier($value){
		return $this->db->quoteIdentifier($value);
	}

	/**
	* Wrapper to MDB2 escape
	* @param value to escape
	*/
	public function escape($value){
		return $this->db->escape($value);
	}

	/**
	* Test if database can be contacted
	* Mostly used for installation
	* @param type db type
	* @param user database's user
	* @param pass password for the user
	* @param host which host we want to connect to
	* @param db database name
	*/
	public static function testConnectivity($type, $user, $pass, $host, $db){
		$dsn = $type . '://' . $user . ':' . $pass . '@' . $host . '/' . $db;
		$options = array(
			'persistent'	=>	false,
			'debug'		=>	2,
			'portability'	=>	MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
		);

		$db = MDB2::connect($dsn, $options);

		if (MDB2::isError($db)){
			$ret = array(
				'main'	=>	$db->getMessage(),
				'debug'	=>	$db->getDebugInfo()
			);
			return $ret;
		}else{
			$db->disconnect();
			return false;
		}
	}

	public function getOption($arg){
		return $this->db->getOption($arg);
	}

	public function prepare($query, $types = null, $result_types = null, $lobs = array()){
		return $this->db->prepare($query, $types, $result_types, $lobs);
	}

	public function listTables(){
		return $this->db->listTables();
	}

	public function testDropTable(){
		global $log;
		$result = $this->db->dropTable('galette_test');

		if (MDB2::isError($result)){
			$log->log('Unable to drop test table.', PEAR_LOG_WARNING);
			$ret = array(
				'main'	=>	$result->getMessage(),
				'debug'	=>	$result->getDebugInfo()
			);
			return $ret;
		}else{
			$log->log('Test table successfully dropped.', PEAR_LOG_DEBUG);
			return $result;
		}
	}

	/**
	* Checks GRANT access for install time
	* @param mode are we at install time (i) or update time (u) ?
	*/
	public function grantCheck($mode = 'i'){
		//This method should not catch more than warning log messages
		//since errors displaying is handled at install/index.php
		global $log;
		$log->log('Check for database rights', PEAR_LOG_DEBUG);
		$stop = false;
		$results = array(
			'create'	=>	false,
			'insert'	=>	false,
			'select'	=>	false,
			'update'	=>	false,
			'alter'		=>	false,
			'delete'	=>	false,
			'drop'		=>	false
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

		$result = $this->db->manager->createTable('galette_test', $fields);

		if (MDB2::isError($result)){
			$create = $result;
			$stop = true;
			$log->log('Cannot CREATE TABLE', PEAR_LOG_WARNING);
		} else $create = MDB2_OK;
		$results['create'] = $create;

		if(!$stop){
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
				1.3,
				'2007-05-29',
				'12:12:00',
				'1980-05-29 12:12:00'
			);
			$result = $this->insertInto('galette_test', $fields, $values);
			if (MDB2::isError($result)){
				$insert = $result;
				$stop = true;
				$log->log('Cannot INSERT records', PEAR_LOG_WARNING);
			} else $insert = MDB2_OK;
			$results['insert'] = $insert;
		}

		if(!$stop){
			//can Galette UPDATE records ?
			$fields = array(
				'test_text',
				'test_float',
				'test_timestamp'
			);
			$values = array(
				'another simple text',
				3.1,
				'1979-11-27 11:30:05'
			);
			$result = $this->update('galette_test', $fields, $values);
			if (MDB2::isError($result)){
				$update = $result;
				$stop = true;
				$log->log('Cannot UPDATE records', PEAR_LOG_WARNING);
			} else $update = MDB2_OK;
			$results['update'] = $update;
		}

		if(!$stop){
			//can Galette SELECT records ?
			$requete = 'SELECT '
				 . $this->db->quoteIdentifier('test_id') . ', '
				 . $this->db->quoteIdentifier('test_boolean') . ', '
				 . $this->db->quoteIdentifier('test_date') . ' FROM '
				 . $this->db->quoteIdentifier('galette_test');

			$result = $this->db->query($requete);
			if (MDB2::isError($result)){
				$select = $result;
				$stop = true;
				$log->log('Cannot SELECT records', PEAR_LOG_WARNING);
			} else $select = MDB2_OK;
			$results['select'] = $select;
		}

		if(!$stop && $mode == 'u'){
			//can Galette ALTER tables ?
			$alter = array(
				'add' => array(
					'test_add'	=>	array(
									'type'	=>	'text'
								)
					)
			);
			$result = $this->db->manager->alterTable('galette_test', $alter, false);
			if (MDB2::isError($result)){
				$alter = $result;
				$stop = true;
				$log->log('Cannot ALTER TABLE', PEAR_LOG_WARNING);
			} else $alter = MDB2_OK;
			$results['alter'] = $alter;
		}

		if(!$stop){
			//can Galette DELETE records ?
			$requete = 'DELETE FROM ' . $this->db->quoteIdentifier('galette_test');

			$result = $this->db->query($requete);
			if (MDB2::isError($result)){
				$delete = $result;
				$stop = true;
				$log->log('Cannot DELETE records', PEAR_LOG_WARNING);
			} else $delete = MDB2_OK;
			$results['delete'] = $delete;
		}

		if(!$stop){
			//can Galette DROP tables ?
			$result = $this->db->dropTable('galette_test');

			if (MDB2::isError($result)){
				$drop = $result;
				$stop = true;
				$log->log('Cannot DROP TABLE', PEAR_LOG_WARNING);
			} else $drop = MDB2_OK;
			$results['drop'] = $drop;
		}

		return $results;
	}

	/**
	* Converts recursively database to UTF-8
	*/
	public function convertToUTF(){
		global $log;
		$this->db->loadModule('Reverse');

		$all_tables = $this->db->listTables();
		$tables = array();
		$queries = array();

		// check for prefix in table name, so we keep only galette's tables
		for($i = 0 ; $i < count($all_tables) ; $i++){
			if (strstr($all_tables[$i], PREFIX_DB)){
				$tables[] = $all_tables[$i];
			}
		}

		foreach($tables as $table){
			// in MDB2 2.5.0, a method alterDatabase should have been added
			//Change whole table charset
			$query = 'ALTER TABLE ' . $this->quoteIdentifier($table) . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci';
			if( !$result = $this->db->query( $query ) ) {
				$log->log('Cannot change charset for table `' . $table . '`, data and fields will not be updated. Try to fix the problem, and run this script again.', PEAR_LOG_ERR);
			} else {
				$log->log('Charset successfully changed for table `' . $table .'`', PEAR_LOG_DEBUG);
				$fields = $this->db->listTableFields( $table );
		
				$fields_types = array();

				//change charset for each relevant field from the table
				foreach($fields as $field){
					$fdef = $this->db->reverse->getTableFieldDefinition($table, $field);
					$proceed_data_convert = false;

					/** FIXME: which datatypes should have a bad encoding value ?  */
					$fields_types[$field] = $fdef[0]['mdb2type'];
					if($fdef[0]['mdb2type'] == 'text'){
						$definitions = array();
						$definitions['type'] = $fdef[0]['mdb2type'];
						if(isset($fdef[0]['length'])) $definitions['length'] = $fdef[0]['length'];
						if($fdef[0]['notnull'] == 1) $definitions['notnull'] = true;
						//To handle DEFAULT ''
						if($fdef[0]['notnull'] == 1 && $fdef[0]['default'] == '' && $fdef[0]['nativetype'] != 'text'){
							$definitions['default'] = '';
						}elseif($fdef[0]['default']){
							$definitions['default'] = $fdef[0]['default'];
						}
						if($fdef[0]['fixed']) $definitions['fixed'] = $fdef[0]['fixed'];
						$definitions['charset'] = 'utf8';
						$definitions['collate'] = 'utf8_unicode_ci';

						$alter = array(
							'change' => array(
								$field	=>	array(
									'definition'	=>	$definitions
								)
							)
						);
						$result = $this->db->manager->alterTable($table, $alter, false);
						if (MDB2::isError($result)){
							$log->log('Cannot ALTER TABLE `' . $table . '` (working on field `' . $field . '`)' , PEAR_LOG_ERR);
						} else {
							$proceed_data_convert = true;
							$log->log('Charset for field `' . $field . '` from table `' . $table . '` successfully updated.', PEAR_LOG_DEBUG);
						}
					}
				}

				//Data conversion
				if($table != PREFIX_DB . 'pictures' && $proceed_data_convert) $this->convertContentToUTF($table, $fields_types);
			}
		}
	}

	private function convertContentToUTF($table, $fields_types) {
		global $log;
		$content="";
		$query = 'SET NAMES latin1';

		if( !$result = $this->db->query( $query ) ) {
			$log->log('Cannot SET NAMES on table table `' . $table . '`.', PEAR_LOG_ERR);
		} else {
			$query = 'SELECT * FROM ' . $this->quoteIdentifier($table);
			if( !$result = $this->db->query( $query ) ) {
				$log->log('Cannot retrieve data from table `' . $table . '`.', PEAR_LOG_ERR);
			} else {
				$table_info = $this->db->reverse->tableInfo($table);
				$constraints = $this->db->reverse->getTableConstraintDefinition($table, 'primary');
				$r = $result->fetchAll();
				foreach($r as $row){
					$requete = 'UPDATE ' . $this->quoteIdentifier($table) . ' SET ';
					foreach($row as $key => $value){
						$requete .= $key . '=';
						$requete .= $this->db->quote((( !seems_utf8($value) ) ? utf8_encode($value) : $value), $fields_types[$key]);
						$requete .= ', ';
					}
					$requete = rtrim($requete,', ');
					$requete .= ' WHERE ';
					//foreach( $constraints as $constraint){
					foreach( $constraints as $constraint_key => $constraint_value){
						if($constraint_key == 'fields'){
							
							$c = array_keys($constraint_value);
							foreach($c as $cf){
								$requete .= $cf . '=' . $row->$cf;
							}
						}
					}

					$result = $this->execute($requete);
					if (MDB2::isError($result)) {
						$log->log('Error while converting data ' . $result->getMessage() . '(' . $result->getDebugInfo() . ') - query: ' . $requete, PEAR_LOG_ERR);
						//return false;
					}
				}
			}
		}
		return $content;
	}

	public function getDb(){ return $this->db; }

	/**
	* Has an error occured ?
	*/
	public function inError(){
		if( MDB2::isError($this->error) ) return true; 
		else return false;
	}

	/**
	* Get main MDB2 error message
	*/
	public function getErrorMessage(){
		return $this->error->getMessage();
	}

	/**
	* Get additionnal informations about the error
	*/
	public function getErrorDetails(){
		return $this->error->getDebugInfo();
	}
}
?>
