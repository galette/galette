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
 * contributions_types.class.php, 27 octobre 2007
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
 * Contributions
 *
 * @name Preferences
 * @package Galette
 *
 */

class ContributionsTypes{
	private $types;
	private $error;

	const TABLE = 'types_cotisation';
	const PK = 'id_type_cotis';
	private static $fields = array(
		'id_type_cotis',
		'libelle_type_cotis',
		'cotis_extension'
	);

	private static $defaults = array(
		array('id' => 1, 'libelle' => 'annual fee', 'extension' => '1'),
		array('id' => 2, 'libelle' => 'reduced annual fee', 'extension' => '1'),
		array('id' => 3, 'libelle' => 'company fee', 'extension' => '1'),
		array('id' => 4, 'libelle' => 'donation in kind', 'extension' => null),
		array('id' => 5, 'libelle' => 'donation in money', 'extension' => null),
		array('id' => 6, 'libelle' => 'partnership', 'extension' => null),
		array('id' => 7, 'libelle' => 'annual fee (to be paid)', 'extension' => '1')
	);

	/**
	* Default constructor
	*/
	public function __construct($args = null){
		if ( is_object($args) ){
			$this->loadFromRS($args);
		}
	}

	/**
	* Set default contribution types at install time
	*/
	public function installInit(){
		global $mdb, $log;

		//first, we drop all values
		$query = 'DELETE FROM '  . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
		$result = $mdb->execute($query);

		if (MDB2::isError($result)) {
			print_r($result);
		}

		$stmt = $mdb->prepare(
				'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' (' . $mdb->quoteIdentifier('id_type_cotis') . ', ' . $mdb->quoteIdentifier('libelle_type_cotis') . ', ' . $mdb->quoteIdentifier('cotis_extension') . ') VALUES(:id, :libelle, :extension)',
				array('integer', 'text', 'text'),
				MDB2_PREPARE_MANIP
			);

		$mdb->getDb()->loadModule('Extended', null, false);
		$mdb->getDb()->extended->executeMultiple($stmt, self::$defaults);

		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to initialize default contributions types.' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$stmt->free();
		$log->log('Default contributions types were successfully stored into database.', PEAR_LOG_INFO);
		return true;
	}

	/**
	* Returns the list of statuses, in an array built as :
	* $array[id] = label status
	*/
	public function getList(){
		/** TODO */
	}

	/**
	* Returns the complete list of contributions types, as an array of ContributionsTypes objects
	* TODO: replace with a static function ?
	*/
	public function getCompleteList(){
		global $mdb, $log;
		$list = array();

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' ORDER BY id_type_cotis';

		$result = $mdb->query( $requete );
		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return false;
		  }

		if($result->numRows() == 0){
			$log->log('No contribution type defined in database.', PEAR_LOG_INFO);
			return(-10);
		}else{
			/** TODO: an array of Objects would be more relevant here (see members and adherent class) */
			/*foreach( $result->fetchAll() as $row ){
				$list[] = new ContributionTypes($row);
			}*/
			$r = $result->fetchAll();
			foreach($r as $contrib){
				$list[$contrib->id_type_cotis] = array(
					_T($contrib->libelle_type_cotis),
					$contrib->cotis_extension
				);
			}
			return $list;
		}
	}

	/* Get a status. Return values on error:
	 * null : no such $id.
	 * MDB2::Error object : DB error.
	 */
	public function get($id){
		global $mdb, $log;

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK .'=' . $id;

		$result = $mdb->query($requete);
		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return $result;
		  }

		if ($result->numRows() == 0) {
			$this->error = $result;
			$log->log('Contribution type `' . $id . '` does not exist.', PEAR_LOG_WARNING);
			return null;
		}

		return $result->fetchRow();
	}

	/* Get a label. Return values on error:
	 * -2 : ID does not exist.
	 * -1 : DB error.
	 */
	public function getLabel($id){
		$res = $this->get($id);
		if (!$res || MDB2::isError($res))
			return $res;
		return _T($res->libelle_type_cotis);
	}

	/* Get a contribution type ID from a label. Return values on error:
	 * -2 : ID does not exist.
	 * -1 : DB error.
	 */
	public function getIdByLabel($label){
		global $mdb, $log;

		$stmt = $mdb->prepare('SELECT '. self::PK .' FROM ' . PREFIX_DB . self::TABLE 
				      . ' WHERE ' . $mdb->quoteIdentifier('libelle_type_cotis') . '= :libelle', 
				      array('text'), MDB2_PREPARE_MANIP);
		$result = $stmt->execute(array('libelle' => $label));

		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return -1;
		  }

		if ($result == 0 || $result->numRows() == 0)
			return null;

		return $result->fetchOne();
	}

	/* Add a new contribution type. Return id on success, else:
	 * -1 : DB error.
	 * -2 : label already exists.
	*/
	public function add($label, $extension){
		global $mdb, $log;

		// Avoid duplicates.
		$ret = $this->getidByLabel($label);
		if (MDB2::isError($ret))
			return -1;
		if ($ret != null)
			{
				$log->log('Contribution type `' . $label . '` already exists', PEAR_LOG_WARNING);
				return -2;
			}

		$stmt = $mdb->prepare('INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) 
				      . ' (' . $mdb->quoteIdentifier('libelle_type_cotis') 
				      . ', ' . $mdb->quoteIdentifier('cotis_extension') 
				      . ') VALUES(:libelle, :extension)',
				      array('text', 'integer'),
				      MDB2_PREPARE_MANIP);
		$stmt->execute(array(
				     'libelle'   => $mdb->escape($label),
				     'extension' => $extension
				     ));

		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to add new contribution type `' . $label . '` | ' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return -1;
		}

		$stmt->free();
		$log->log('New contribution type `' . $label . '` added successfully.', PEAR_LOG_INFO);
		return $mdb->getDb()->lastInsertId(PREFIX_DB . self::TABLE,
						   'libelle_type_cotis');
	}

	/* Update a contribution type. Return values:
	 * -2 : ID does not exist.
	 * -1 : DB error.
	 *  0 : success.
	 */
	public function update($id, $field, $value) {
		global $mdb, $log;

		$ret = $this->get($id);
		if (!$ret || MDB2::isError($ret))
			/* get() already logged and set $this->error. */
			return ($ret ? -1 : -2);

		$fieldtype = '';
		# label.
		if ($field == self::$fields[1]) { $fieldtype = 'text'; }
		# membership extension.
		elseif(self::$fields[2]) { $fieldtype = 'integer'; }

		$log->log("Setting field $field to $value for ctype $id", PEAR_LOG_INFO);

		$stmt = $mdb->prepare('UPDATE ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' SET '
				      . $mdb->quoteIdentifier($field) . ' = :field '
				      . 'WHERE ' . self::PK . ' = '.$id,
				      array($fieldtype),
				      MDB2_PREPARE_MANIP);
		$stmt->execute(array('field'  => $value));

		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to update contribution type ' . $id . ' | ' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return -1;
		}

		$stmt->free();
		$log->log('Contribution type ' . $id . ' updated successfully.', PEAR_LOG_INFO);
		return 0;
	}

	/* Delete a contribution type. Return values:
	 * -2 : ID does not exist.
	 * -1 : DB error.
	 *  0 : success.
	 */
	public function delete($id)
	{
		global $mdb, $log;

		$ret = $this->get($id);
		if (!$ret || MDB2::isError($ret))
			/* get() already logged and set $this->error. */
			return ($ret ? -1 : -2);

		$query = 'DELETE FROM ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE)
			. ' WHERE ' . self::PK . ' = ' . $id;
		$result = $mdb->execute($query);

		if (MDB2::isError($result)) {
			$this->error = $result;
			$log->log('Unable to delete contribution type ' . $id . ' | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return -1;
		}

		$log->log('Contribution type ' . $id . ' deleted successfully.', PEAR_LOG_INFO);
		return 0;
	}

	/* Check whether this contribution type is used. Return values:
	 * -1 : DB error.
	 *  0 : not used.
	 *  1 : used.
	 */
	public function isUsed($id){
		global $mdb, $log;

		// Check if it's used.
		$query = 'SELECT * FROM ' . $mdb->quoteIdentifier(PREFIX_DB . 'cotisations')
			. ' WHERE ' . $mdb->quoteIdentifier('id_type_cotis') . ' = ' . $id;
		$result = $mdb->query($query);
		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return -1;
		  }

		return ($result->numRows() == 0) ? 0 : 1;
	}

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