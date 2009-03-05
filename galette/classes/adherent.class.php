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
 * adhrent.class.php, 28 février 2009
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
 * Member class for galette
 *
 * @name Adherent
 * @package Galette
 *
 */

require_once('politeness.class.php');
require_once('status.class.php');

class Adherent {
	const TABLE = 'adherents';
	const PK = 'id_adh';

	private $id;
	//Identity
	private $politeness;
	private $name;
	private $surname;
	private $nickname; //redundant with login ?
	private $birthdate;
	private $job;
	private $language;
	private $active;
	private $status;
	//Contact informations
	private $adress;
	private $adress_continuation;
	private $zipcode;
	private $town;
	private $country;
	private $phone;
	private $gsm;
	private $email;
	private $website;
	private $icq; /** TODO: remove */
	private $jabber; /** TODO: remove */
	private $gnupgid; /** TODO: remove */
	private $fingerprint; /** TODO: remove */
	//Galette relative informations
	private $appears_in_list;
	private $admin;
	private $due_free;
	private $login;
	private $password;
	private $creation_date;
	private $others_infos;
	private $others_infos_admin;
	private $picture;

	/**
	* Default constructor
	*/
	public function __construct(){
		$this->active = true;
		$this->language = i18n::DEFAULT_LANG;
		$this->creation_date = date("d/m/Y");
		$this->status = Status::DEFAULT_STATUS;
		$this->politeness = Politeness::MR;
		$this->password = makeRandomPassword(7); //Usefull ?
		$this->picture = new picture();
	}

	/**
	* Loads a member from its id
	* @param id the identifiant for the member to load
	*/
	public function load($id){
		global $mdb, $log;

		$this->id = $id;
		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=' . $id;

		$result = $mdb->query( $requete );

		if (MDB2::isError($result)) {
			$log->log('Cannot load member form id `' . $id . '` | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$r = $result->fetchRow();
		//Identity
		$this->politeness = $r->titre_adh;
		$this->name = $r->nom_adh;
		$this->surname = $r->prenom_adh;
		$this->nickname = $r->pseudo_adh; //redundant with login ?
		$this->birthdate = $r->ddn_adh;
		$this->job = $r->prof_adh;
		$this->language = $r->pref_lang;
		$this->active = $r->activite_adh;
		$this->status = $r->id_statut;
		//Contact informations
		$this->adress = $r->adresse_adh;
		$this->adress_continuation = $r->adresse2_adh; /** TODO: remove and merge with adress */
		$this->zipcode = $r->cp_adh;
		$this->town = $r->ville_adh;
		$this->country = $r->pays_adh;
		$this->phone = $r->tel_adh;
		$this->gsm = $r->gsm_adh;
		$this->email = $r->email_adh;
		$this->website = $r->url_adh;
		$this->icq = $r->icq_adh; /** TODO: remove */
		$this->jabber = $r->jabber_adh; /** TODO: remove */
		$this->gnupgid = $r->gpgid; /** TODO: remove */
		$this->fingerprint = $r->fingerprint; /** TODO: remove */
		//Galette relative informations
		$this->appears_in_list = $r->bool_display_info;
		$this->admin = $r->bool_admin_adh;
		$this->due_free = $r->bool_exempt_adh;
		$this->login = $r->login_adh;
		$this->password = $r->mdp_adh;
		$this->creation_date = $r->date_crea_adh;
		$this->others_infos = $r->info_public_adh;
		$this->others_infos_admin = $r->info_adh;
		$this->picture = new picture($this->id);

		return true;
	}

	/* GETTERS */
	public function isAdmin(){
		return $this->admin;
	}

	public function isDueFree(){
		return $this->due_free;
	}

	public function appearsInMembersList(){
		return $this->appears_in_list;
	}

	public function isActive(){
		return $this->active;
	}

	public function hasPicture(){
		return $this->picture->hasPicture();
	}

	public function __get($name){
		$forbidden = array('admin', 'due_free', 'appears_in_list', 'active');
		$virtuals = array('sadmin', 'sdue_free', 'sappears_in_list', 'sactive', 'spoliteness', 'sstatus');
		if( !in_array($name, $forbidden) && isset($this->$name)){
			switch($name){
				case 'birthdate':
				case 'creation_date':
					return date_db2text($this->$name);
					break;
				default:
					return $this->$name;
					break;
			}
		} else if( !in_array($name, $forbidden) && in_array($name, $virtuals) ){
			$real = substr($name, 1);
			switch($name){
				case 'sadmin':
				case 'sdue_free':
				case 'sappears_in_list':
					return (($this->$real) ? _T("Yes") : _T("No"));
					break;
				case 'sactive':
					return (($this->$real) ? _T("Active") : _T("Inactive"));
					break;
				case 'spoliteness':
					return Politeness::getPoliteness($this->politeness);
					break;
				case 'sstatus':
					return Status::getLabel($this->status);
					break;
			}
		} else return false;
	}

	/* SETTERS */
	public function __set($name, $value){
		/** TODO: What to do ? :-) */
	}
}
?>