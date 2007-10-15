<?php
//
//  preferences.class.php, 14 octobre 2007
//
// Copyright © 2007 Johan Cwiklinski
//
// File :               	preferences.class.php
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
 * preferences.class.php, 14 octobre 2007
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
 * Preferences for galette
 *
 * @name Preferences
 * @package Galette
 *
 */

class Preferences{
	private $prefs;

	const TABLE = 'preferences';
	const PK = 'id_pref';

	/**
	* Default constructor
	*/
	public function __construct(){
		$this->load();
	}

	/**
	* Load current preferences from database.
	*/
	public function load(){
		global $mdb2_db;

		$this->prefs = array();

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE;

		if( !$result = $mdb2_db->query( $requete ) )
			return -1;
		
		if($result->numRows() == 0){
			/** TODO: Log a fatal error, application cannot work if settings are not setted correctly */
			return(-10);
		}else{
			$r = $result->fetchAll();
			$array = array();
			foreach($r as $pref){
				$this->prefs[$pref->nom_pref] = $pref->val_pref;
			}
		}
	}

	/* GETTERS */
	public function __get($name){
		$forbidden = array('logged', 'admin', 'active');
		if( !in_array($name, $forbidden) )
			return $this->$name;
		else return false;
	}
	/* SETTERS */

}
?>