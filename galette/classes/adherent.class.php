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

/** @ignore */
require_once('politeness.class.php');
require_once('status.class.php');
require_once('fields_config.class.php');
require_once('fields_categories.class.php');
require_once('picture.class.php');

/**
 * Member class for galette
 *
 * @name Adherent
 * @package Galette
 *
 */
class Adherent {
	const TABLE = 'adherents';
	const PK = 'id_adh';

	private $id;
	//Identity
	private $politeness;
	private $name;
	private $surname;
	private $nickname; //redundant with login ? -seems not see dev mailing list archives
	private $birthdate;
	private $job;
	private $language;
	private $active;
	private $status;
	//Contact informations
	private $adress;
	private $adress_continuation; /** TODO: remove */
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
	//fields list and their translation
	private $fields;
	private $requireds = array(
		'titre_adh',
		'nom_adh',
		'login_adh',
		'mdp_adh',
		'adresse_adh',
		'cp_adh',
		'ville_adh'
	);


	/**
	* Default constructor
	*/
	public function __construct($args = null){
		/**
		* Fields configuration. Each field is an array and must reflect:
		* array( (string)label, (boolean)required, (boolean)visible, (int)position, (int)category )
		*
		* I'd prefer a static private variable for this...
		* But call to the _T function does not seems to be allowed there :/
		*/
		$this->fields = array(
			"id_adh"		=>	array( 'label'=>_T("Identifiant:"), 'required'=>true, 'visible'=>FieldsConfig::HIDDEN, 'position'=>0, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"id_statut"		=>	array( 'label'=>_T("Status:"), 'required'=>true, 'visible'=>FieldsConfig::VISIBLE, 'position'=>1, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"nom_adh"		=>	array( 'label'=>_T("Name:"), 'required'=>true , 'visible'=>FieldsConfig::VISIBLE, 'position'=>2, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"prenom_adh"		=>	array( 'label'=>_T("First name:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>3, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"pseudo_adh"		=>	array( 'label'=>_T("Nickname:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>4, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"titre_adh"		=>	array( 'label'=>_T("Title:"), 'required'=>true, 'visible'=>FieldsConfig::VISIBLE, 'position'=>5, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"ddn_adh"		=>	array( 'label'=>_T("birth date:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>6, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"adresse_adh"		=>	array( 'label'=>_T("Address:"), 'required'=>true, 'visible'=>FieldsConfig::VISIBLE, 'position'=>7, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			/** TODO remove second adress... */
			"adresse2_adh"		=>	array( 'label'=>_T("Address (continuation)"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>8, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"cp_adh"		=>	array( 'label'=>_T("Zip Code:"), 'required'=>true, 'visible'=>FieldsConfig::VISIBLE, 'position'=>9, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"ville_adh"		=>	array( 'label'=>_T("City:"), 'required'=>true, 'visible'=>FieldsConfig::VISIBLE, 'position'=>10, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"pays_adh"		=>	array( 'label'=>_T("Country:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>11, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"tel_adh"		=>	array( 'label'=>_T("Phone:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>12, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"gsm_adh"		=>	array( 'label'=>_T("Mobile phone:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>13, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"email_adh"		=>	array( 'label'=>_T("E-Mail:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>14, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"url_adh"		=>	array( 'label'=>_T("Website:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>15, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"icq_adh"		=>	array( 'label'=>_T("ICQ:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>16, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"msn_adh"		=>	array( 'label'=>_T("MSN:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>17, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"jabber_adh"		=>	array( 'label'=>_T("Jabber:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>18, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"info_adh"		=>	array( 'label'=>_T("Other informations (admin):"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>19, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"info_public_adh"	=>	array( 'label'=>_T("Other informations:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>20, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"prof_adh"		=>	array( 'label'=>_T("Profession:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>21, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"login_adh"		=>	array( 'label'=>_T("Username:"), 'required'=>true, 'visible'=>FieldsConfig::VISIBLE, 'position'=>22, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"mdp_adh"		=>	array( 'label'=>_T("Password:"), 'required'=>true, 'visible'=>FieldsConfig::VISIBLE, 'position'=>23, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"date_crea_adh"		=>	array( 'label'=>_T("Creation date:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>24, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"activite_adh"		=>	array( 'label'=>_T("Account:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>25, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"bool_admin_adh"	=>	array( 'label'=>_T("Galette Admin:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>26, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"bool_exempt_adh"	=>	array( 'label'=>_T("Freed of dues:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>27, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"bool_display_info"	=>	array( 'label'=>_T("Be visible in the<br /> members list:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>28, 'category'=>FieldsCategories::ADH_CATEGORY_GALETTE),
			"date_echeance"		=>	array( 'label'=>_T("Due date:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>29, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"pref_lang"		=>	array( 'label'=>_T("Language:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>30, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"lieu_naissance"	=>	array( 'label'=>_T("Birthplace:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>31, 'category'=>FieldsCategories::ADH_CATEGORY_IDENTITY),
			"gpgid"			=>	array( 'label'=>_T("Id GNUpg (GPG):"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>32, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT),
			"fingerprint"		=>	array( 'label'=>_T("fingerprint:"), 'required'=>false, 'visible'=>FieldsConfig::VISIBLE, 'position'=>33, 'category'=>FieldsCategories::ADH_CATEGORY_CONTACT)
		);
		if( $args == null || is_int($args) ) {
			$this->active = true;
			$this->language = i18n::DEFAULT_LANG;
			$this->creation_date = date("Y-m-d");
			$this->status = Status::DEFAULT_STATUS;
			$this->politeness = Politeness::MR;
			$this->password = makeRandomPassword(7); //Usefull ?
			$this->picture = new Picture();
			if( is_int($args) && $args > 0 ) $this->load($args);
		} elseif ( is_object($args) ){
			$this->loadFromRS($args);
		}
	}

	/**
	* Loads a member from its id
	* @param id the identifiant for the member to load
	*/
	public function load($id){
		global $mdb, $log;

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=' . $id;

		$result = $mdb->query( $requete );

		if (MDB2::isError($result)) {
			$log->log('Cannot load member form id `' . $id . '` | ' . $result->getMessage() . '(' . $result->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$this->loadFromRS($result->fetchRow());
		$result->free();

		return true;
	}

	/**
	* Populate object from a resultset row
	*/
	private function loadFromRS($r){
		$this->id = $r->id_adh;
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
		$this->picture = new Picture($this->id);
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
		$virtuals = array('sadmin', 'sdue_free', 'sappears_in_list', 'sactive', 'spoliteness', 'sstatus', 'sfullname');
		if( !in_array($name, $forbidden) && isset($this->$name)){
			switch($name){
				case 'birthdate':
				case 'creation_date':
					/** FIXME: date function from functions.inc.php does use adodb */
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
				case 'sfullname':
					return Politeness::getPoliteness($this->politeness) . ' ' . $this->name . ' ' . $this->surname;
					break;
			}
		} else return false;
	}

	/* SETTERS */
	public function __set($name, $value){
		$forbidden = array('fields');
		/** TODO: What to do ? :-) */
	}
}
?>