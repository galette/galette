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
 * members.class.php, 28 février 2009
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
 * Members class for galette
 *
 * @name Members
 * @package Galette
 *
 */

require_once('adherent.class.php');
require_once('status.class.php');

class Members {
	const TABLE = Adherent::TABLE;
	const PK = Adherent::PK;

	private $filter = null;

	/**
	* Default constructor
	*/
	public function __construct(){}

	/**
	* Get members list
	*/
	public function getList($filter){
		/** TODO: Check if filter is valid ? */
		if( $filter != null && trim($filter) != '' ) $this->filter = $filter;
	}

	/**
	* Get list of members that has been selected
	* @param ids an array of members id that has been selected
	* @param orderby SQL order clause (optionnal)
	* @return an array of Adherent object
	*/
	public static function getArrayList($ids, $orderby = null){
		global $mdb, $log;

		if( !is_array($ids) || count($ids) < 1 ){
			$log->log('No member selected for labels.', PEAR_LOG_INFO );
			return false;
		}

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=';
		$requete .= implode(' OR ' . self::PK . '=', $ids);

		if( $orderby != null && trim($orderby) != '' ) $requete .= ' ORDER BY ' . $orderby;

		$result = $mdb->query( $requete );

		if (MDB2::isError($result)) {
			$log->log('Cannot load members form ids array | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$members = array();
		foreach( $result->fetchAll() as $row ){
			$members[] = new Adherent($row);
		}
		return $members;
	}
}
?>