<?php

// Copyright © 2009 Johan Cwiklinski
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
 * fields_config.class.php, 26 mars 2009
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 */


/** @ignore */
require_once('adherent.class.php');
require_once('fields_categories.class.php');

/**
 * Fields config class for galette :
 * defines fields mandatory, order and visibility
 *
 * @name FieldsConfig
 * @package Galette
 *
 */
class FieldsConfig{
	const HIDDEN = 0;
	const VISIBLE = 1;
	const ADMIN = 2;

	private $all_required;
	private $all_visibles;
	private $all_labels;
	private $error = array();
	private $categorized_fields = array();
	private $table;
	private $default = null;
	private $all_categories;

	const TABLE = 'fields_config';

	private $types = array(
		'text',
		'text',
		'boolean',
		'integer',
		'integer',
		'integer'
	);

	/**
	* Default constructor
	* @param table (string): the table for which to get fields configuration
	*/
	function __construct($table, $defaults){
		$this->table = $table;
		$this->defaults = $defaults;
		$this->checkUpdate();
	}

	/**
	* Checks if the required table should be updated
	* since it has not yet happened or the table
	* has been modified.
	*/
	private function checkUpdate($try = true){
		global $mdb, $log;
		$class = get_class($this);
		if ($mdb->getOption('result_buffering')){
			$requete = 'SELECT * FROM ' . PREFIX_DB . $this->table;
			$mdb->getDb()->setLimit(1);

			$result2 = $mdb->query( $requete );
			if( MDB2::isError($result2) )
				return -1;

			$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE table_name=\'' . $this->table . '\' ORDER BY ' . FieldsCategories::PK . ', position ASC';

			$result = $mdb->query( $requete );
			if( MDB2::isError($result) )
				return -1;

			$result->setResultTypes($this->types);

			if($result->numRows() == 0 && $try){
				$this->init();
			}else{
				$required = $result->fetchAll();
				$this->categorized_fields = null;
				foreach($required as $k){
					$f = array(
						'field_id'	=>	$k->field_id,
						'label'		=>	$this->defaults[$k->field_id]['label'],
						'category'	=>	$this->defaults[$k->field_id]['category'],
						'visible'	=>	$k->visible,
						'required'	=>	$k->required
					);
					$this->categorized_fields[$k->id_field_category][] = $f;

					//array of all required fields
					if($k->required == 1)
						$this->all_required[$k->field_id] = $k->required;

					//array of all fields visibility
					$this->all_visibles[$k->field_id] = $k->visible;

					//maybe we can delete these ones in the future
					$this->all_labels[$k->field_id] = $this->defaults[$k->field_id]['label'];
					$this->all_categories[$k->field_id] = $this->defaults[$k->field_id]['category'];
					$this->all_positions[$k->field_id] = $k->position;
				}
				if($result2->numCols() != $result->numRows()){
					$log->log('[' . $class . '] Count for `' . $this->table . '` columns does not match records. Is : ' . $result->numRows() . ' and should be ' . $result2->numCols() . '. Reinit.', PEAR_LOG_INFO);
					$this->init(true);
				}
			}
		}else{
			$log->log('[' . $class . '] An error occured while checking update for fields configuration for table `' . $this->table . '`.', PEAR_LOG_ERROR);
		}
	}

	/**
	* Init data into config table.
	* @param reinit (boolean): true if we must first delete all config data for current table.
	* This should occurs when table has been updated. For the first
	* initialisation, value should be false.
	*/
	function init($reinit=false){
		global $mdb, $log;
		$class = get_class($this);
		$log->log('[' . $class . '] Initializing fields configuration for table `' . $this->table . '`', PEAR_LOG_DEBUG);
		if($reinit){
			$log->log('[' . $class . '] Reinit mode, we delete config content for table `' . $this->table . '`', PEAR_LOG_DEBUG);
			//Delete all entries for current table. Existing entries are alreday stored, new ones will be added :)
			$requetesup = 'DELETE FROM ' . PREFIX_DB . self::TABLE . ' WHERE table_name=\'' . $this->table . '\'';

			if( !$init_result = $mdb->execute( $requetesup ) )
				return -1;
		}
	
		$requete = 'SELECT * FROM ' . PREFIX_DB . $this->table;
		$mdb->getDb()->setLimit(1);

		$result = $mdb->query( $requete );
		if( MDB2::isError($result) )
			return -1;

		$fields = $result->getColumnNames();

		$stmt = $mdb->prepare(
			'INSERT INTO ' . PREFIX_DB . self::TABLE . ' (table_name, field_id, required, visible, position, ' . FieldsCategories::PK . ') VALUES(:table_name, :field_id, :required, :visible, :position, :category)',
			$this->types,
			MDB2_PREPARE_MANIP
		);

		$params = array();
		foreach($fields as $key=>$value){
			$params[] = array(
				'field_id'	=>	$key,
 				'table_name'	=>	$this->table,
				'required'	=>	(($reinit)?array_key_exists($key, $this->all_required):$this->defaults[$key]['required']?true:false),
				'visible'	=>	(($reinit)?array_key_exists($key, $this->all_visible):$this->defaults[$key]['visible']?true:false),
				'position'	=>	(($reinit)?$this->all_positions[$key]:$this->defaults[$key]['position']),
				'category'	=>	(($reinit)?$this->all_categories[$key]:$this->defaults[$key]['category']),
			);
		}

		$mdb->getDb()->loadModule('Extended', null, false);
		$mdb->getDb()->extended->executeMultiple($stmt, $params);

		if (MDB2::isError($stmt)) {
			$log->log('[' . $class . '] An error occured trying to initialize fields configuration for table `' . $this->table . '`.' . $stmt->getMessage(), PEAR_LOG_ERR);
		}else{
			$log->log('[' . $class . '] Initialisation seems successfull, we reload the object', PEAR_LOG_DEBUG);
			$log->log(str_replace('%s', $this->table,  '[' . $class . '] Fields configuration for table %s initialized successfully.'), PEAR_LOG_INFO);
			$stmt->free();
			$this->checkUpdate(false);
		}
	}


	/**
	* GETTERS
	* @return array of all required fields. Field names = keys
	*/
	public function getRequired(){ return $this->all_required; }
	/*public function getLabels(){ return $this->all_labels; }*/
	/*public function getCategories(){ return $this->all_categories; }*/
	/*public function getPositions(){ return $this->all_positions; }*/
	/*public function getPosition($field){ return $this->all_positions[$field]; }*/
	public function getVisibilities(){ return $this->all_visibles; }
	public function getVisibility($field){ return $this->all_visibles[$field]; }
	public function getCategorizedFields(){ return $this->categorized_fields; }
	public function getFields(){ return $this->fields; }

	/**
	* SETTERS
	* @param array fields categorized fields array
	*/
	public function setFields($fields){
		$this->categorized_fields = $fields;
		$this->store();
	}

	private function store(){
		global $mdb, $log;

		$stmt = $mdb->prepare(
			'UPDATE ' . PREFIX_DB . self::TABLE . ' SET required=:required, visible=:visible, position=:position, ' . FieldsCategories::PK . '=:category WHERE table_name=\'' .$this->table .'\' AND field_id=:field_id',
			$this->types,
			MDB2_PREPARE_MANIP
		);

		$params = array();
		foreach($this->categorized_fields as $cat){
			foreach($cat as $pos=>$field){
				$params[] = array(
					'field_id'	=>	$field['field_id'],
					'required'	=>	$field['required'],
					'visible'	=>	$field['visible'],
					'position'	=>	$pos,
					'category'	=>	$field['category']
				);
			}
		}

		$mdb->getDb()->loadModule('Extended', null, false);
		$mdb->getDb()->extended->executeMultiple($stmt, $params);

		$class = get_class($this);
		if (MDB2::isError($stmt)) {
			$log->log('[' . $class . '] An error occured while storing fields configuration for table `' . $this->table . '`.' . $stmt->getMessage(), PEAR_LOG_ERR);
			return false;
		}else{
			$log->log('[' . $class . '] Fields configuration stored successfully! ', PEAR_LOG_DEBUG);
			$log->log(str_replace('%s', $this->table,  '[' . $class . '] Fields configuration for table %s stored successfully.'), PEAR_LOG_INFO);
			$stmt->free();
			return true;
		}
	}
}
?>