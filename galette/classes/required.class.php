<?php
/*
 * required.class.php, 06 juillet 2007
 * 
 * This file is part of Galette.
 *
 * Copyright © 2007 Johan Cwiklinski
 *
 * File :               	required.class.php
 * Author's email :     	johan@x-tnd.be
 * Author's Website :   	http://galette.tuxfamily.org
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
 */

/** TODO
* This constant should be defined at higher level
*/
define("MDB2_VERSION", "2.4.1");
require_once(WEB_ROOT."classes/MDB2-".MDB2_VERSION."/MDB2.php");

class Required{
	private $all_required;
	private $error = array();
	private $db;

	private $defaults = array(
		'titre_adh',
		'nom_adh',
		'login_adh',
		'email_adh',
		'mdp_adh',
		'adresse_adh',
		'cp_adh',
		'ville_adh'
	);
	
	function __construct(){
		$dsn = TYPE_DB.'://'.USER_DB.':'.PWD_DB.'@'.HOST_DB.'/'.NAME_DB;
		$options = array(
			'debug'       => 2,
			'portability' => MDB2_PORTABILITY_ALL,
		);
		
		$this->db = & MDB2::connect($dsn, $options);
		// Vérification des erreurs
		if (MDB2::isError($this->db)) {
			echo $db->getDebugInfo().'<BR/>';
			echo $db->getMessage();
		}
		$this->db->setFetchMode(MDB2_FETCHMODE_OBJECT);

		$this->checkUpdate();
	}

	function __destruct(){
		$this->db->disconnect();
	}

	/**
	* Checks if the required table should be updated
	* since it has not yet appened or adherents table
	* has been modified.
	*/
	private function checkUpdate(){
		if ($this->db->getOption('result_buffering')){
			$requete = "SELECT * FROM ".PREFIX_DB."adherents LIMIT 1";
			$result2 = $this->db->query( $requete );
			// Vérification des erreurs
			if (MDB2::isError($result2)) {
				echo $result2->getDebugInfo().'<BR/>';
				echo $result2->getMessage();
			}

			$requete = "SELECT * FROM ".PREFIX_DB."required";
			$result = $this->db->query( $requete );
			// Vérification des erreurs
			if (MDB2::isError($result)) {
				echo $result->getDebugInfo().'<BR/>';
				echo $result->getMessage();
			}
			
			if($result->numRows()==0){
				$this->init();
				exit();
			}else{
				if($result2->numCols() != $result->numRows()){
					$this->init(true);
					exit();
				}
			}
			$required = $result->fetchAll();

			foreach($required as $k){
				if($k->required == 1)
					$this->all_required[$k->field_id] = $k->required;
			}
		}else{
			/** TODO :
			* Informer de l'erreur
			*/
		}
	}

	/**
	* Init data into required table.
	* @param $reinit : true if we must first delete all data on required table.
	* This should occurs when adherents table has been updated. For the first
	* initialisation, value should be off.
	*/
	function init($reinit=false){
		if($reinit){
			$requetesup = "DELETE FROM ".PREFIX_DB."required";
			$DB->Execute($requetesup);
		}
	
		$requete = "SELECT * FROM ".PREFIX_DB."adherents LIMIT 1";
		$result = $this->db->query( $requete );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<BR/>';
			echo $result->getMessage();
		}
		$fields = $result->getColumnNames();

		$f = array();
		foreach($fields as $key=>$value){
			$f[] = array($key,(in_array($key, $this->defaults))?true:false);
		}

		$stmt = $this->db->prepare('INSERT INTO '.PREFIX_DB.'required VALUES(?,?)', array('text', 'boolean'), false);
		foreach ($f as $row){
			/** TODO :
			* Informer dans le log que la table des required a été mise à jour
			*/
			$stmt->bindParamArray($row);
			$stmt->execute();
		}
		if (MDB2::isError($stmt)) {
			echo $result->getDebugInfo().'<BR/>';
			echo $result->getMessage();
		}else{
			$this->checkUpdate();
		}
	}


	/* GETTERS */
	public function getRequired(){return $this->all_required;}

	/* SETTERS */
	public function setRequired($value){
		$requete = "UPDATE ".PREFIX_DB."required SET required=1 WHERE field_id=";
		$requete .= (is_array($value))?implode(" OR field_id=", $value):"$value";
		echo $requete;

		$result = $this->db->query( $requete );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<BR/>';
			echo $result->getMessage();
		}else{
			return $result;
		}
	}
	/*public function setRequired($value){
		if(!is_array($value)) $value[] = $value;
		foreach($value as $k=>$v){
			
		}
	}*/
}
?>