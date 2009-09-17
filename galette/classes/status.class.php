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
 * status.class.php, 27 octobre 2007
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 */

/* TODO: Most of the code is duplicated in contribution_types.class.php. Should
 * probably use a superclass for genericity.
 */

/**
 * Members status
 *
 * @name Status
 * @package Galette
 *
 */

class Status{
	private $status;
	private $error;

	const DEFAULT_STATUS = 4;
	const TABLE = 'statuts';
	const PK = 'id_statut';
	const ORDER_FIELD = 'priorite_statut';
	private static $fields = array(
		'id_statut',
		'libelle_statut',
		'priorite_statut'
	);

	private static $defaults = array(
		array('id' => 1, 'libelle' => 'President', 'priority' => 0),
		array('id' => 2, 'libelle' => 'Treasurer', 'priority' => 10),
		array('id' => 3, 'libelle' => 'Secretary', 'priority' => 20),
		array('id' => 4, 'libelle' => 'Active member', 'priority' => 30),
		array('id' => 5, 'libelle' => 'Benefactor member', 'priority' => 40),
		array('id' => 6, 'libelle' => 'Founder member', 'priority' => 50),
		array('id' => 7, 'libelle' => 'Old-timer', 'priority' => 60),
		array('id' => 8, 'libelle' => 'Society', 'priority' => 70),
		array('id' => 9, 'libelle' => 'Non-member', 'priority' => 80),
		array('id' => 10, 'libelle' => 'Vice-president', 'priority' => 5)
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
	* Set default status at install time
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
				'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' (' . $mdb->quoteIdentifier('id_statut') . ', ' . $mdb->quoteIdentifier('libelle_statut') . ', ' . $mdb->quoteIdentifier('priorite_statut') . ') VALUES(:id, :libelle, :priority)',
				array('integer', 'text', 'integer'),
				MDB2_PREPARE_MANIP
			);

		$mdb->getDb()->loadModule('Extended', null, false);
		$mdb->getDb()->extended->executeMultiple($stmt, self::$defaults);

		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to initialize default status.' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$stmt->free();
		$log->log('Default status were successfully stored into database.', PEAR_LOG_INFO);
		return true;
	}

	/**
	* Returns the list of statuses, in an array built as :
	* $array[id] = label status
	*/
	public function getList(){
		global $mdb, $log;
		$list = array();

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' ORDER BY ' . self::ORDER_FIELD . ', ' . self::PK;

		$result = $mdb->query($requete);
		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return false;
		  }

		if($result->numRows() == 0){
			$log->log('No status defined in database.', PEAR_LOG_INFO);
			return(-10);
		}else{
			$r = $result->fetchAll();
			$array = array();
			foreach($r as $status){
				$list[$status->id_statut] = _T($status->libelle_statut);
			}
			return $list;
		}

	}

	/**
	* Returns the complete list of statuses, as an array of Status objects
	* TODO: replace with a static function ?
	*/
        public function getCompleteList(){
		global $mdb, $log;
		$list = array();

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' ORDER BY ' . self::ORDER_FIELD . ', ' . self::PK;

		$result = $mdb->query($requete);
		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return false;
		  }

		if($result->numRows() == 0){
			$log->log('No status defined in database.', PEAR_LOG_INFO);
			return(-10);
		}else{
			/** TODO: an array of Objects would be more relevant here (see members and adherent class) */
			/*foreach( $result->fetchAll() as $row ){
				$list[] = new Status($row);
			}*/
			/** END TODO */
			$r = $result->fetchAll();
			foreach($r as $status){
				$list[$status->id_statut] = array(
					_T($status->libelle_statut),
					$status->priorite_statut
				);
			}
			return $list;
		}
	}

	/* Get a status. Return values on error:
	 * null : no such $id.
	 * MDB2::Error object : DB error.
	 */
	public function get($id) {
		global $mdb, $log;

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK .'=' . $id;

		$result = $mdb->query($requete);
		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return $result;
		  }

		if ($result->numRows() == 0) {
			$log->log('Status `' . $id . '` does not exist.', PEAR_LOG_WARNING);
			return null;
		}

		return $result->fetchRow();
	}

	/* Get a label. Return values on error:
	 * null : no such id.
	 * MDB2::Error object : DB error.
	 */
	public static function getLabel($id) {
	        $res = self::get($id);
		if (!$res || MDB2::isError($res))
			return $res;

		return _T($res->libelle_statut);
	}

	/* Get a status ID from a label. Return an object on success, else:
	 * null : ID does not exist.
	 * MDB2::Error object : DB error.
	 */
	public function getIdByLabel($label){
		global $mdb, $log;

		$stmt = $mdb->prepare('SELECT '. self::PK .' FROM ' . PREFIX_DB . self::TABLE 
				      . ' WHERE ' . $mdb->quoteIdentifier('libelle_statut') . '= :libelle', 
				      array('text'), MDB2_PREPARE_MANIP);
		$result = $stmt->execute(array('libelle' => $label));

		if (MDB2::isError($result))
		  {
		    $this->error = $result;
		    return $result;
		  }

		if ($result == 0 || $result->numRows() == 0)
			return null;

		return $result->fetchOne();
	}


	/* Add a new status. Return id on success, else:
	 * -1 : DB error.
	 * -2 : label already exists.
	*/
	public function add($label, $priority)
	{
		global $mdb, $log;

		// Avoid duplicates.
		$ret = $this->getidByLabel($label);
		if (MDB2::isError($ret))
			return -1;
		if ($ret != null)
			{
				$log->log('Status `' . $label . '` already exists', PEAR_LOG_WARNING);
				return -2;
			}

		$stmt = $mdb->prepare('INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) 
				      . ' (' . $mdb->quoteIdentifier('libelle_statut') 
				      . ', ' . $mdb->quoteIdentifier('priorite_statut') 
				      . ') VALUES(:libelle, :priorite)',
				      array('text', 'integer'),
				      MDB2_PREPARE_MANIP);
		$stmt->execute(array(
				     'libelle'  => $label,
				     'priorite' => $priority
				     ));
		
		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to add new status `' . $label . '` | ' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return -1;
		}

		$stmt->free();
		$log->log('New status `' . $label . '` added successfully.', PEAR_LOG_INFO);
		return $mdb->getDb()->lastInsertId(PREFIX_DB . self::TABLE,
						   'libelle_statut');
	}

	/* Update a status. Return values:
	 * -2 : ID does not exist.
	 * -1 : DB error.
	 *  0 : success.
	 */
	public function update($id, $field, $value)
	{
		global $mdb, $log;

		$ret = $this->get($id);
		if (!$ret || MDB2::isError($ret))
			/* get() already logged and set $this->error. */
			return ($ret ? -1 : -2);

		$fieldtype = '';
		# label.
		if ($field == self::$fields[1]) { $fieldtype = 'text'; }
		# priority.
		elseif(self::$fields[2]) { $fieldtype = 'integer'; }

		$stmt = $mdb->prepare('UPDATE ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' SET '
				      . $mdb->quoteIdentifier($field) . ' = :field '
				      . 'WHERE ' . self::PK . ' = '.$id,
				      array($fieldtype),
				      MDB2_PREPARE_MANIP);
		$stmt->execute(array('field'  => $value));

		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to update status ' . $id . ' | ' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return -1;
		}

		$stmt->free();
		$log->log('Status ' . $id . ' updated successfully.', PEAR_LOG_INFO);
		return 0;
	}

	/* Delete a status. Return values:
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
			$log->log('Unable to delete status ' . $id . ' | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return -1;
		}

		$log->log('Status ' . $id . ' deleted successfully.', PEAR_LOG_INFO);
		return 0;
	}

	/* Check whether this status is used. Return values:
	 * -1 : DB error.
	 *  0 : not used.
	 *  1 : used.
	 */
	public function isUsed($id){
		global $mdb, $log;

		// Check if it's used.
		$query = 'SELECT * FROM ' . $mdb->quoteIdentifier(PREFIX_DB . 'adherents')
			. ' WHERE ' . $mdb->quoteIdentifier('id_statut') . ' = ' . $id;
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
