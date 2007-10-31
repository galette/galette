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
 * @since      Disponible depuis la Release 0.7alpha
 */

/**
 * Required class for galette :
 * defines which fields are mandatory and which are not.
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
	const TABLE = 'required';

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
		$this->checkUpdate();
	}

	/**
	* Checks if the required table should be updated
	* since it has not yet appened or adherents table
	* has been modified.
	*/
	private function checkUpdate($try = true){
		global $mdb, $log;
		if ($mdb->getOption('result_buffering')){
			$requete = 'SELECT * FROM ' . PREFIX_DB . Adherents::TABLE . ' LIMIT 1';

			/** TODO: what to do on error ? */
			if( !$result2 = $mdb->query( $requete ) )
				return -1;

			/*$result2 = $this->db->query( $requete );
			// Vérification des erreurs
			if (MDB2::isError($result2)) {
				echo $result2->getDebugInfo().'<br/>';
				echo $result2->getMessage();
			}*/

			$requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE;

			/** TODO: what to do on error ? */
			if( !$result = $mdb->query( $requete ) )
				return -1;

			/*$result = $this->db->query( $requete );
			// Vérification des erreurs
			if (MDB2::isError($result)) {
				echo $result->getDebugInfo().'<br/>';
				echo $result->getMessage();
			}*/
			
			if($result->numRows()==0 && $try){
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
					$log->log('Count for adherents columns does not match required records. Is : ' . $result->numRows() . ' and should be ' . $result2->numCols() . '. Reinit.', PEAR_LOG_DEBUG);
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
		global $mdb, $log;
		$log->log('Initializing required fiels', PEAR_LOG_DEBUG);
		if($reinit){
			$log->log('Reinit mode, we delete table\'s content', PEAR_LOG_DEBUG);
			$requetesup = 'DELETE FROM ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
			/** TODO: what to do on error ? */
			if( !$init_result = $mdb->execute( $requetesup ) )
				return -1;
			//$this->db->query( $requetesup );
		}
	
		$requete = 'SELECT * FROM ' . $mdb->quoteIdentifier(PREFIX_DB . Adherents::TABLE) . ' LIMIT 1';

		/** TODO: what to do on error ? */
		if( !$result = $mdb->query( $requete ) )
			return -1;

		/*$result = $this->db->query( $requete );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}*/

		$fields = $result->getColumnNames();

		$f = array();
		foreach($fields as $key=>$value){
			$f[] = array('id' => $key, 'required' => (($reinit)?array_key_exists($key, $this->all_required):in_array($key, $this->defaults)?true:false));
		}

		$stmt = $mdb->prepare(
				'INSERT INTO ' . PREFIX_DB . self::TABLE . ' (field_id, required) VALUES(:id, :required)',
				array('text', 'boolean'),
				MDB2_PREPARE_MANIP
			);

		foreach ($f as $row){
			/** TODO :
			* Informer dans le log que la table des required a été mise à jour
			*/
			$stmt->bindParamArray($row);
			$stmt->execute();
		}

		if (MDB2::isError($stmt)) {
			echo $stmt->getDebugInfo().'<br/>';
			echo $stmt->getMessage();
		}else{
			$log->log('Initialisation seems successfull, we reload the object', PEAR_LOG_DEBUG);
			$this->checkUpdate(false);
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
		$requete = 'UPDATE ' . PREFIX_DB . self::TABLE . ' SET required=1 WHERE field_id=\'';
		$requete .= implode('\' OR field_id=\'', $value);
		$requete .= '\'';

		/** TODO: what to do on error ? */
		if( !$result = $mdb->query( $requete ) )
			return -1;

		/*$result = $this->db->query( $requete );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}*/

		//set not required fields (ie. all others...)
		$not_required = array_diff($this->fields, $value);
		$requete2 = 'UPDATE ' . PREFIX_DB . self::TABLE . ' SET required=0 WHERE field_id=\'';
		$requete2 .= implode('\' OR field_id=\'', $not_required);
		$requete2 .= '\'';

		/** TODO: what to do on error ? */
		if( !$result = $mdb->query( $requete2 ) )
			return -1;

		/*$result = $this->db->query( $requete2 );
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}*/

		$this->checkUpdate();
	}
}
?>