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

/**
 * Fields config class for galette :
 * defines which fields are mandatory and which are not, 
 * also define order and visibility
 *
 * @name FieldsConfig
 * @package Galette
 *
 */

require_once('adherent.class.php');
require_once('fields_categories.class.php');

class FieldsConfig{
	private $all_required;
	private $all_visibles;
	private $all_labels;
	private $error = array();
	private $db;
	private $fields = array();
	private $table;
	private $default = null;
	private $all_categories;

	const TABLE = 'config_fields';

	/** TODO: reflect new table structure here*/
	private $types = array(
		'text',
		'text',
		'boolean',
		'boolean',
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
		if ($mdb->getOption('result_buffering')){
			$requete = 'SELECT * FROM ' . PREFIX_DB . $this->table;
			$mdb->getDb()->setLimit(1);

			if( !$result2 = $mdb->query( $requete ) )
				return -1;

			$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE table_name=\'' . $this->table . '\' ORDER BY id_field_category, position ASC';

			if( !$result = $mdb->query( $requete ) )
				return -1;

			$result->setResultTypes($this->types);

			if($result->numRows() == 0 && $try){
				$this->init();
			}else{
				$categories = FieldsCategories::getList();
				foreach($categories as $c){
					$this->fields[$c] = array();
				}
				$required = $result->fetchAll();
				$this->fields = null;
				foreach($required as $k){
					//$this->fields[] = $k->field_id;
					$this->fields[$k->id_field_category][] = $k->field_id;
					// categ1
					//	champ1
					//	champ2
					// categ2
					//	champ3
					//	champ5
					// categ3
					//	champ4
					$this->all_labels[$k->field_id] = $this->defaults[$k->field_id]['label'];
					$this->all_categories[$k->field_id] = $this->defaults[$k->field_id]['category'];
					$this->all_positions[$k->field_id] = $k->position;
					if($k->required == 1)
						$this->all_required[$k->field_id] = $k->required;
					if($k->visible == 1)
						$this->all_visibles[$k->field_id] = $k->visible;
				}
				if($result2->numCols() != $result->numRows()){
					$log->log('Count for `' . $this->table . '` columns does not match records. Is : ' . $result->numRows() . ' and should be ' . $result2->numCols() . '. Reinit.', PEAR_LOG_INFO);
					$this->init(true);
				}
			}
		}else{
			$log->log('An error occured while checking for required fields update for table `' . $this->table . '`.', PEAR_LOG_ERROR);
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
		$log->log('Initializing fields configuration for table `' . $this->table . '`', PEAR_LOG_DEBUG);
		if($reinit){
			$log->log('Reinit mode, we delete config content for table `' . $this->table . '`', PEAR_LOG_DEBUG);
			//Delete all entries for current table. Existing entries are alreday stored, new ones will be added :)
			$requetesup = 'DELETE FROM ' . PREFIX_DB . self::TABLE . ' WHERE table_name=\'' . $this->table . '\'';

			if( !$init_result = $mdb->execute( $requetesup ) )
				return -1;
		}
	
		$requete = 'SELECT * FROM ' . PREFIX_DB . $this->table;
		$mdb->getDb()->setLimit(1);

		if( !$result = $mdb->query( $requete ) )
			return -1;

		$fields = $result->getColumnNames();

		$f = array();
		foreach($fields as $key=>$value){
			$f[] = array(
 				'table_name' => $this->table,
				'id' => $key,
				'required' => (($reinit)?array_key_exists($key, $this->all_required):$this->defaults[$key]['required']?true:false),
				'visible' => (($reinit)?array_key_exists($key, $this->all_visible):$this->defaults[$key]['visible']?true:false),
				'position' => (($reinit)?$this->all_positions[$key]:$this->defaults[$key]['position']),
				'category' => (($reinit)?$this->all_categories[$key]:$this->defaults[$key]['category']),
			);
		}

		$stmt = $mdb->prepare(
				'INSERT INTO ' . PREFIX_DB . self::TABLE . ' (table_name, field_id, required, visible, position, id_field_category) VALUES(:table_name, :id, :required, :visible, :position, :category)',
				$this->types,
				MDB2_PREPARE_MANIP
			);

		foreach ($f as $row){
			$stmt->bindParamArray($row);
			$stmt->execute();
		}

		if (MDB2::isError($stmt)) {
			$log->log('An error occured trying to initialize required fields for table `' . $this->table . '`.' . $stmt->getMessage(), PEAR_LOG_ERR);
		}else{
			$log->log('Initialisation seems successfull, we reload the object', PEAR_LOG_DEBUG);
			$log->log(str_replace('%s', $this->table,  _T("Fields configuration for table %s initialized successfully.")), PEAR_LOG_INFO);
			$this->checkUpdate(false);
		}
	}


	/**
	* GETTERS
	* @return array of all required fields. Field names = keys
	*/
	public function getRequired(){ return $this->all_required; }
	public function getLabels(){ return $this->all_labels; }
	public function getCategories(){ return $this->all_categories; }
	public function getPositions(){ return $this->all_positions; }
	public function getPosition($field){ return $this->all_positions[$field]; }
	public function getVisibles(){ return $this->all_visibles; }
	public function getFields(){ return $this->fields; }

	/**
	* SETTERS
	* @param string: Field name to set to required state
	* @return boolean: true = field set
	*/
	public function setRequired($value){
		global $mdb, $log;

		//set required fields
		/** TODO: reflect new table structure here*/
		$requete = 'UPDATE ' . PREFIX_DB . self::TABLE . ' SET required=' . $mdb->quote(true) . ' WHERE field_id=\'';
		$requete .= implode('\' OR field_id=\'', $value);
		$requete .= '\'';
		$requete .= ' AND table_name=\'' . $this->table . '\'';

		/** TODO: what to do on error ? */
		if( !$result = $mdb->query( $requete ) )
			return -1;

		//set not required fields (ie. all others...)
		$not_required = array_diff($this->fields, $value);
		/** TODO: reflect new table structure here*/
		$requete2 = 'UPDATE ' . PREFIX_DB . self::TABLE . ' SET required=' . $mdb->quote(false) . ' WHERE field_id=\'';
		$requete2 .= implode('\' OR field_id=\'', $not_required);
		$requete2 .= '\'';
		$requete .= ' AND table_name=\'' . $this->table . '\'';

		if( !$result = $mdb->query( $requete2 ) )
			return -1;

		$this->checkUpdate();
	}
}
?>