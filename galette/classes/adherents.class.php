<?php
//
//  adherents.class.php, 06 juillet 2007
//
// Copyright © 2007 Johan Cwiklinski
//
// File :               	adherents.class.php
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
 * adherents.class.php, 06 juillet 2007
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
 * Adhrents class for galette
 *
 * @name Adherents
 * @package Galette
 *
 */

class Adherents{
	private $login;
	private $passe;
	private $name;
	private $surname;
	private $admin = false;
	private $id;
	private $lang;
	private $logged = false;
	private $active = false;

	const TABLE = 'adherents';
	const PK = 'login_adh';

	/**
	* Default constructor
	*/
	public function __construct(){}

	/**
	* Logs in user.
	* @param user user's login
	* @param passe md5 hashed password
	* @returns integer state : 
	* 	'-1' if there were a database error
	*	'-10' if user cannot login (mistake or user doesn't exists)
	*	'1' if user were logged in successfully
	*/
	public function logIn($user, $passe){
		global $mdb2_db;

		$requete = 'SELECT id_adh, bool_admin_adh, nom_adh, prenom_adh, mdp_adh, pref_lang, activite_adh FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=\'' . $user. '\' AND mdp_adh=\'' . $passe . '\'';

		if( !$result = $mdb2_db->query( $requete ) )
			return -1;
		
		if($result->numRows() == 0){
			return(-10);
		}else{
			$row = $result->fetchRow();
			$this->id = $row->id_adh;
			$this->login = $row->login_adh;
			$this->passe = $row->mdp_adh;
			//$this->email = $row->mail;
			$this->admin = $row->bool_admin_adh;
			//$this->auth = $row->auth;
			//$this->date_inscription = $row->date;
			//$this->last_login = $row->last_connect;
			//$this->use_ajax = $row->use_ajax;
			$this->name = $row->nom_adh;
			$this->surname = $row->prenom_adh;
			$this->lang = $row->pref_lang;
			$this->active = $row->activite_adh;
			$this->logged = true;
			//$this->upLastConn($this->login);
			return(1);
		}
	}

	/**
	* Login for the superuser
	* @param login login name
	*/
	public function logAdmin($login){
		$this->logged = true;
		$this->name = 'Admin';
		$this->login = $login;
		$this->admin = true;
		$this->active = true;
	}

	/**
	* Log out user and unset variables
	*/
	public function logOut(){
		$this->logged = false;
		$this->name = null;
		$this->login = null;
		$this->admin = false;
		$this->active = false;
	}

	/* GETTERS */
	public function isLogged(){return $this->logged;}
	public function isAdmin(){return $this->admin;}
	public function isActive(){return $this->active;}
	//public function isAuth(){return $this->auth;}
	//public function getLogin(){return $this->login;}
	//public function getPasse(){return $this->passe;}
	//public function getMail(){return $this->email;}
	//public function getDateInscription(){return $this->date_inscription;}
	//public function getLastConnect(){return $this->last_login;}
	//public function getCompte(){return $this->compte;}
	//public function useAjax(){return $this->use_ajax;}
	public function __get($name){
		$forbidden = array('logged', 'admin', 'active');
		if( !in_array($name, $forbidden) )
			return $this->$name;
		else return false;
	}
	/* SETTERS */

}
?>