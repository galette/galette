<?php
//
//  mdb2.class.php, 05 septembre 2007
//
// Copyright © 2007 Johan Cwiklinski
//
// File :               	mdb2.class.php
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
 * galette_mdb2.class.php, 05 septembre 2007
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
* We define here include path
* for Galette to include embedded MDB2 and PEAR
*/
require_once("MDB2.php");

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
	private $db;

	function __construct($persistent = false){
		/** TODO: declare PEAR::Log somewhere... */
		global $log;
		$this->persistent = $persistent;
		$dsn = TYPE_DB . '://' . USER_DB . ':' . PWD_DB . '@' . HOST_DB . '/' . NAME_DB;
		$options = array(
			'persistent'	=>	$persistent,
			'debug'		=>	2,
			'portability'	=>	MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
		);
		$this->dsn_array = MDB2::parseDSN($dsn);

		$this->db = MDB2::connect($dsn, $options);

		if (MDB2::isError($this->db)) {
			$log->log('MDB2 : no connexion (' . $this->db->getMessage() . ') - ' . $this->db->getDebugInfo(), PEAR_LOG_ALERT);
		}else
			$log->log('MDB2 : connected successfully', PEAR_LOG_INFO);

		$this->db->setFetchMode(MDB2_FETCHMODE_OBJECT);
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
			$log->log('There were an error executing query ' . $query . '(' . $result->getMessage() . ') - ' . $result->getDebugInfo(), PEAR_LOG_ERR);
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
		$result = $this->db->execute($query);
				// Vérification des erreurs
		if (MDB2::isError($result)) {
			$log->log('There were an error executing query ' . $query . '(' . $result->getMessage() . ') - ' . $result->getDebugInfo(), PEAR_LOG_ERR);
			return -1;
		}else{
			$log->log('Query successfull : ' . $query, PEAR_LOG_DEBUG);
			return $result;
		}
	}

	public function getOption($arg){
		return $this->db->getOption($arg);
	}

	public function prepare($query, $types = null, $result_types = null, $lobs = array()){
		return $this->db->prepare($query, $types, $result_types, $lobs);
	}
}
?>