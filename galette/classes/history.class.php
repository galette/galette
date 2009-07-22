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
 * history.class.php, 09 fevrier 2009
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
 * History class for galette
 *
 * @name History
 * @package Galette
 *
 */

class History{
	const TABLE = 'logs';
	const PK = 'id_log';

	const ORDER_ASC = 'ASC';
	const ORDER_DESC = 'DESC';

	/** TODO: check for the date type */
	private $types = array(
		'date',
		'text',
		'text',
		'text',
		'text',
		'text'
	);

	private $fields = array(
		'date_log',
		'ip_log',
		'adh_log',
		'action_log',
		'text_log',
		'sql_log'
	);

	private $page = 1;
	private $show = null;
	private $tri = 'date_log';
	private $ordered;
	private $counter = null;
	private $pages = 1;

	/**
	* Default constructor
	*/
	public function __construct(){
		global $preferences;
		$this->show = $preferences->pref_numrows;
		$this->ordered = self::ORDER_ASC;
	}

	/**
	* Add a new entry
	* @param message message to log
	*/
	public function add($action, $argument = '', $query = ''){
		global $mdb, $log, $login;

		MDB2::loadFile('Date');

		$requete = 'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' (';
		$requete .= implode(', ', $this->fields);
		$requete .= ') VALUES (:date, :ip, :adh, :action, :text, :sql)';

		$stmt = $mdb->prepare($requete, $this->types, MDB2_PREPARE_MANIP);

		if (MDB2::isError($stmt)) {
			$log->log('Unable to initialize add log entry into database.' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$stmt->execute(array(
			'date'		=>	MDB2_Date::mdbNow(),
			'ip'		=>	$_SERVER["REMOTE_ADDR"],
			'adh'		=>	$login->login,
			'action'	=>	$action,
			'text'		=>	$argument,
			'sql'		=>	$query
		));

		if (MDB2::isError($stmt)) {
			$log->log(_t("An error occured trying to add log entry.") . $stmt->getMessage(), PEAR_LOG_ERR);
			return false;
		}else{
			$log->log('Log entry added', PEAR_LOG_DEBUG);
		}

		$stmt->free();

		return true;
	}

	/**
	* Delete all entries
	* @return integer : number of entries deleted
	*/
	public function clean(){
		global $mdb, $log;
		$requete = 'TRUNCATE TABLE ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
		
		$result = $mdb->execute($requete);

		$this->add('Logs flushed');

		return $result;
	}

	/**
	* Get the entire history list
	*/
	public function getHistory($start = 0, $count = 0){
		global $mdb, $log;

		if($this->counter == null){
			$c = $this->getCount();

			if($c == 0){
				$log->log('No entry in history (yet?).', PEAR_LOG_DEBUG);
				return;
			} else {
				$this->counter = $c;
				if ($this->counter % $this->show == 0) 
					$this->pages = intval($this->counter / $this->show);
				else 
					$this->pages = intval($this->counter / $this->show) + 1;
				if($this->pages == 0) $this->pages = 1;
			}
		}

		$requete = 'SELECT * FROM ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
		$requete .= 'ORDER BY ' . $this->tri . ' ' . $this->ordered;

		$mdb->getDb()->setLimit($this->show,($this->page - 1) * $this->show);

		$result = $mdb->query( $requete );
		if( MDB2::isError($result) )
			return -1;

		$return = array();
		while ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)){
			$return[] = $row;
		}
		return $return;
	}

	private function getCount(){
		global $mdb, $log;
		$requete = 'SELECT count(' . self::PK . ') as counter FROM ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);

		$result = $mdb->query( $requete );
		if (MDB2::isError($result)) {
			$this->error = $result;
			$log->log('Unable to get history count.' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return -1;
		}

		return $result->fetchOne();
	}

	/**
	* Changes the sort order
	*/
	public function invertorder(){
		$actual=$this->ordered;
		if($actual == self::ORDER_ASC){
			$this->ordered = self::ORDER_DESC;
		}
		if($actual == self::ORDER_DESC){
			$this->ordered = self::ORDER_ASC;
		}
	}

	/* GETTERS */
	public function getDirection(){ return $this->ordered; }
	public function __get($name){
		$forbidden = array('ordered');
		if( !in_array($name, $forbidden) )
			return $this->$name;
		else return false;
	}
	/* SETTERS */
	public function setDirection($dir){ $this->ordered = $dir; }
	public function __set($name, $value){
		$forbidden = array('ordered');
		if( !in_array($name, $forbidden) ){
			if($name == 'tri'){
				if(in_array($value, $this->fields)){
					$this->$name = $value;
				}
			} else {
				$this->$name = $value;
			}
			return $this->$name;
		}
		else return false;
	}
}
?>