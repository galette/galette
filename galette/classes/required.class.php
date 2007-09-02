<?php
//
//  required.class.php, 06 juillet 2007
//
// Copyright © 2007 Johan Cwiklinski
//
// File :               	required.class.php
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
 * required.class.php, 06 juillet 2007
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2007 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GPL License 2.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.63
 */

/** TODO
* - The above constant should be defined at higher level
* - all errors messages should be handled by pear::log
*/
set_include_path(get_include_path() . PATH_SEPARATOR . WEB_ROOT . "includes/pear/" . PATH_SEPARATOR . WEB_ROOT . "includes/pear/PEAR/" . PATH_SEPARATOR . WEB_ROOT . "includes/pear/MDB2");

require_once("MDB2.php");


/**
 * Required class for galette
 *
 * @name Required
 * @package Galette
 *
 */

class Required{
	private $all_required;
	private $error = array();
	private $db;
	private $fields = array();
	const TABLE = "required";

	private $defaults = array(
		'titre_adh',
		'nom_adh',
		'login_adh',
		'mdp_adh',
		'adresse_adh',
		'cp_adh',
		'ville_adh'
	);
	
	function __construct(){
		$dsn = TYPE_DB.'://'.USER_DB.':'.PWD_DB.'@'.HOST_DB.'/'.NAME_DB;
		//$dsn = 'mysqli://'.USER_DB.':'.PWD_DB.'@'.HOST_DB.'/'.NAME_DB;
		$options = array(
			'debug'       => 2,
			'portability' => MDB2_PORTABILITY_ALL,
		);
		
		$this->db = & MDB2::connect($dsn, $options);
		// Vérification des erreurs
		if (MDB2::isError($this->db)) {
			echo $this->db->getDebugInfo().'<br/>';
			echo $this->db->getMessage();
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
				echo $result2->getDebugInfo().'<br/>';
				echo $result2->getMessage();
			}

			$requete = "SELECT * FROM ".PREFIX_DB.self::TABLE;
			$result = $this->db->query( $requete );
			// Vérification des erreurs
			if (MDB2::isError($result)) {
				echo $result->getDebugInfo().'<br/>';
				echo $result->getMessage();
			}
			
			if($result->numRows()==0){
				$this->init();
				//exit();
			}else{
				$required = $result->fetchAll();
				$this->fields = null;
				foreach($required as $k){
					$this->fields[] = $k->field_id;
					if($k->required == 1)
						$this->all_required[$k->field_id] = $k->required;
				}
				if($result2->numCols() != $result->numRows()){
					$this->init(true);
					//exit();
				}
			}
		}else{
			/** TODO :
			* Informer de l'erreur
			*/
			echo 'An error has occured';
		}
	}

	/**
	* Init data into required table.
	* @param boolean: true if we must first delete all data on required table.
	* This should occurs when adherents table has been updated. For the first
	* initialisation, value should be off.
	*/
	function init($reinit=false){
		if($reinit){
			$requetesup = "DELETE FROM ".PREFIX_DB."required";
			$this->db->query( $requetesup );
		}
	
		$requete = "SELECT * FROM ".PREFIX_DB."adherents LIMIT 1";
		$result = $this->db->query( $requete );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}
		$fields = $result->getColumnNames();

		$f = array();
		foreach($fields as $key=>$value){
			$f[] = array("id" => $key,"required" => (($reinit)?array_key_exists($key, $this->all_required):in_array($key, $this->defaults)?true:false));
		}

		$stmt = $this->db->prepare('INSERT INTO '.PREFIX_DB.'required (field_id, required) VALUES(:id, :required)', array('text', 'boolean'), MDB2_PREPARE_MANIP);
		foreach ($f as $row){
			/** TODO :
			* Informer dans le log que la table des required a été mise à jour
			*/
			$stmt->bindParamArray($row);
			$stmt->execute();
		}
		if (MDB2::isError($stmt)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}else{
			$this->checkUpdate();
		}
	}


	/**
	* GETTERS
	* @return array of all required fields. Field names = keys
	*/
	public function getRequired(){return $this->all_required;}
	public function getFields(){return $this->fields;}

	/**
	* SETTERS
	* @param string: Field name to set to required state
	* @return boolean: true = field set
	*/
	public function setRequired($value){
		//set required fields
		$requete = "UPDATE ".PREFIX_DB."required SET required=1 WHERE field_id='";
		$requete .= implode("' OR field_id='", $value);
		$requete .= "'";

		$result = $this->db->query( $requete );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}

		//set not required fields (ie. all others...)
		$not_required = array_diff($this->fields, $value);
		$requete2 = "UPDATE ".PREFIX_DB."required SET required=0 WHERE field_id='";
		$requete2 .= implode("' OR field_id='", $not_required);
		$requete2 .= "'";

		$result = $this->db->query( $requete2 );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}

		$this->checkUpdate();
	}
}
?>