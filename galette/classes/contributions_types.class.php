<?php
//
//  contributions_types.class.php, 27 octobre 2007
//
// Copyright © 2007 Johan Cwiklinski
//
// File :               	contributions_types.class.php
// Author's email :     	johan@x-tnd.be
// Author's Website :   	http://galette.tuxfamily.org
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
/**
 * contributions_types.class.php, 27 octobre 2007
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2007 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GPL License 2.0 or (at your option) any later version
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
	public function __construct(){}

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