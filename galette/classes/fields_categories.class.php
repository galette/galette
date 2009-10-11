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
 * fields_categories.class.php, 28 mars 2009
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
 * Fields categories class for galette :
 *
 * @name FieldsCategories
 * @package Galette
 *
 */

class FieldsCategories{
	private $id;
	private $category;
	const TABLE = 'fields_categories';
	const PK = 'id_field_category';

	const ADH_CATEGORY_IDENTITY = 1;
	const ADH_CATEGORY_GALETTE = 2;
	const ADH_CATEGORY_CONTACT = 3;

	/**
	* Default constructor
	* @param table (string): the table for which to get fields configuration
	*/
	function __construct(){}

	public static function getList(){
		global $mdb, $log;
		$query = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' ORDER BY position';

		$result = $mdb->query( $query );

		if (MDB2::isError($result)) {
			$log->log('Cannot get fields categories list | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		return $result->fetchAll();
	}


	/**
	* GETTERS
	*/

	/**
	* SETTERS
	*/
}
?>