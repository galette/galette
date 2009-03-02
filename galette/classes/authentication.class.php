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
 * authentification.class.php, 28 février 2009
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
 * @name Authentication
 * @package Galette
 *
 */

abstract class Authentication{
	private $login;
	private $passe;
	private $name;
	private $surname;
	private $admin = false;
	private $id;
	private $lang;
	private $logged = false;
	private $active = false;

	/**
	* Default constructor
	*/
	public function __construct(){}

	/**
	* Logs in user.
	* @param user user's login
	* @param passe md5 hashed password
	* @returns integer state : 
	* 	'-1' if there were an error
	*	'-10' if user cannot login (mistake or user doesn't exists)
	*	'1' if user were logged in successfully
	*/
	abstract public function logIn($user, $passe);

	/**
	* Does this login already exists ?
	* These function should be used for setting admin login into Preferences
	* @param user the username
	* @return true if the username already exists, false otherwise
	*/
	abstract public function loginExists($user);

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
	public function __get($name){
		$forbidden = array('logged', 'admin', 'active');
		if( !in_array($name, $forbidden) )
			return $this->$name;
		else return false;
	}
	/* SETTERS */
}
?>