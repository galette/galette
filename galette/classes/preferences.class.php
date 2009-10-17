<?php

// Copyright © 2007-2008 Johan Cwiklinski
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
 * preferences.class.php, 14 octobre 2007
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 */

/** @ignore */
require_once('i18n.class.php');

/**
 * Preferences for galette
 *
 * @name Preferences
 * @package Galette
 *
 */
class Preferences{
	private $prefs;
	private $error;

	const TABLE = 'preferences';
	const PK = 'nom_pref';
	private static $fields = array(
		'nom_pref',
		'val_pref'
	);

	private static $defaults = array(
		'pref_admin_login'	=>	'admin',
		'pref_admin_pass'	=>	'admin',
		'pref_nom'		=>	'Galette',
		'pref_slogan'		=>	'',
		'pref_adresse'		=>	'-',
		'pref_adresse2'		=>	'',
		'pref_cp'		=>	'',
		'pref_ville'		=>	'',
		'pref_pays'		=>	'',
		'pref_lang'		=>	i18n::DEFAULT_LANG,
		'pref_numrows'		=>	30,
		'pref_log'		=>	2,
		'pref_email_nom'	=>	'Galette',
		'pref_email'		=>	'mail@domain.com',
		'pref_email_newadh'	=>	'mail@domain.com',
		'pref_bool_mailadh'	=>	false,
		'pref_editor_enabled'	=>	false,
		/** FIXME: get constant value from mail class here ? */
		'pref_mail_method'	=>	0,
		'pref_mail_smtp'	=>	'',
		'pref_membership_ext'	=>	12,
		'pref_beg_membership'	=>	'',
		'pref_email_reply_to'	=>	'',
		'pref_website'		=>	'',
		/* Preferences for labels */
		'pref_etiq_marges_v'	=>	10,
		'pref_etiq_marges_h'	=>	10,
		'pref_etiq_hspace'	=>	10,
		'pref_etiq_vspace'	=>	5,
		'pref_etiq_hsize'	=>	90,
		'pref_etiq_vsize'	=>	35,
		'pref_etiq_cols'	=>	2,
		'pref_etiq_rows'	=>	7,
		'pref_etiq_corps'	=>	12,
		/* Preferences for members cards */
		'pref_card_abrev'	=>	'GALETTE',
		'pref_card_strip'	=>	'Gestion d\'Adherents en Ligne Extrêmement Tarabiscotée',
		'pref_card_tcol'	=>	'FFFFFF',
		'pref_card_scol'	=>	'8C2453',
		'pref_card_bcol'	=>	'53248C',
		'pref_card_hcol'	=>	'248C53',
		'pref_bool_display_title'	=>	false,
		'pref_card_address'	=>	1,
		'pref_card_year'	=>	'',
		'pref_card_marges_v'	=>	15,
		'pref_card_marges_h'	=>	20,
		'pref_card_vspace'	=>	5,
		'pref_card_hspace'	=>	10,
		'pref_card_self'	=>	1,
		'pref_theme'		=>	'default'
	);

	/**
	* Default constructor
	*/
	public function __construct(){
		$error = null;
		$this->load();
	}

	/**
	* Load current preferences from database.
	*/
	public function load(){
		global $mdb, $log;

		$this->prefs = array();

		$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE;

		$result = $mdb->query( $requete );
		if( MDB2::isError($result) )
			return -1;
		
		if($result->numRows() == 0){
			$log->log('Preferences cannot be loaded. Galette should not work without preferences. Exiting.', PEAR_LOG_EMERG);
			return(-10);
		}else{
			$r = $result->fetchAll();
			$array = array();
			foreach($r as $pref){
				$this->prefs[$pref->nom_pref] = $pref->val_pref;
			}
		}
	}

	/**
	* Set default preferences at install time
	* @param lang language selected at install screen
	* @param adm_login admin login entered at install time
	* @param adm_pass admin password entered at install time
	*/
	public function installInit($lang, $adm_login, $adm_pass){
		global $mdb, $log;

		//first, we drop all values
		$query = 'DELETE FROM '  . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
		$result = $mdb->execute($query);

		if (MDB2::isError($result)) {
			print_r($result);
		}

		//we then replace default values with the ones user has selected
		$values = self::$defaults;
		$values['pref_lang'] = $lang;
		$values['pref_admin_login'] = $adm_login;
		$values['pref_admin_pass'] = $adm_pass;
		$values['pref_card_year'] = date('Y');

		$stmt = $mdb->prepare(
				'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' (' . $mdb->quoteIdentifier('nom_pref') . ', ' . $mdb->quoteIdentifier('val_pref') . ') VALUES(:nom_pref, :val_pref)',
				array('text', 'text'),
				MDB2_PREPARE_MANIP
			);

		$params = array();
		foreach($values as $k=>$v){
			$params[] = array(
				'nom_pref'	=>	$k,
				'val_pref'	=>	$v
			);
		}

		$mdb->getDb()->loadModule('Extended', null, false);
		$mdb->getDb()->extended->executeMultiple($stmt, $params);

		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to initialize default preferences.' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$stmt->free();
		$log->log('Default preferences were successfully stored into database.', PEAR_LOG_INFO);
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

	public function getFieldsNames(){
		return array_keys($this->prefs);
	}

	/**
	* Will store all preferences in the database
	*/
	public function store(){
		global $mdb, $log;
		$stmt = $mdb->prepare(
				'UPDATE ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' SET ' . $mdb->quoteIdentifier('val_pref') . '=:value WHERE ' . $mdb->quoteIdentifier('nom_pref') . '=:name',
				array('text', 'text'),
				MDB2_PREPARE_MANIP
			);

		$params = array();
		foreach(self::$defaults as $k=>$v){
			$params[] = array(
				'value'	=>	$this->prefs[$k],
				'name'	=>	$k
			);
		}

		$mdb->getDb()->loadModule('Extended', null, false);
		$mdb->getDb()->extended->executeMultiple($stmt, $params);

		if (MDB2::isError($stmt)) {
			$this->error = $stmt;
			$log->log('Unable to store preferences.' . $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')', PEAR_LOG_WARNING);
			return false;
		}

		$stmt->free();
		$log->log('Preferences were successfully stored into database.', PEAR_LOG_INFO);
		return true;

	}

	/* GETTERS */
	public function __get($name){
		$forbidden = array('logged', 'admin', 'active', 'defaults');
		if( !in_array($name, $forbidden) && isset($this->prefs[$name]))
			return $this->prefs[$name];
		else return false;
	}
	/* SETTERS */
	public function __set($name, $value){
		global $mdb, $log;
		/** FIXME: perfs : cette méthode est utile pour enregistrer/mettre à jour une préférence, mais il serait peut être plus judicieux d'exécuter une requête multiple pour la mise à jour de l'ensemble des préférences depuis l'interface administrateur. */

		//does this pref exists ?
		if( !array_key_exists($name, self::$defaults) ){
			$log->log('Trying to set a preference value which does not seems to exist', PEAR_LOG_WARNING);
			return false;
		}

		//some values need to be changed (eg. md5 passwords)
		if($name == 'pref_admin_pass') $value = md5($value);

		//okay, let's update value
		$this->prefs[$name] = $value;
	}

}
?>
