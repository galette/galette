<?php
//
//  texts.class.php, 16 septembre 2007
//
// Copyright © 2007 John Perr
//
// File :               	required.class.php
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
 * texts.class.php, 16 septembre 2007
 *
 * @package Galette
 * 
 * @author     John Perr
 * @copyright  2007 John Perr
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
 * Texts class for galette
 *
 * @name Texts
 * @package Galette
 *
 */

class Texts{
	private $all_texts;
	private $error = array();
	private $db;
	const TABLE = "texts";

	
	function __construct(){
		$dsn = TYPE_DB.'://'.USER_DB.':'.PWD_DB.'@'.HOST_DB.'/'.NAME_DB;
		$options = array(
			'debug'       => 2,
			'portability' => MDB2_PORTABILITY_ALL);
		
		$this->db = & MDB2::connect($dsn, $options);
		// Vérification des erreurs
		if (MDB2::isError($this->db)) {
			echo $this->db->getDebugInfo().'<br/>';
			echo $this->db->getMessage();
		}
		$this->db->setFetchMode(MDB2_FETCHMODE_ASSOC);

	}

	function __destruct(){
		$this->db->disconnect();
	}

	/**
	* GETTERS
	* @param string: Reference of text to get
	* @param string: Language texts to get
	* @return array of all text fields for one language.
	*/
	public function getTexts($ref,$lang){
			$requete = "SELECT * FROM ".PREFIX_DB.self::TABLE." WHERE tref='".$ref."' AND tlang='".$lang."'";
			$result = $this->db->query($requete);
			// Vérification des erreurs
			if (MDB2::isError($result)) {
				echo $result->getDebugInfo().'<br/>';
				echo $result->getMessage();
			}
			
			if($result->numRows()>0){
				$this->all_texts = $result->fetchRow(MDB2_FETCHMODE_ASSOC);
			}
	return $this->all_texts;
	}

	/**
	* SETTERS
	* @param string: Texte ref to locate
	* @param string: Texte language to locate
	* @param string: Subject to set
	* @param string: Body text to set
	* @return boolean: true = field set
	*/
	public function setTexts($ref,$lang,$subject,$body){
		//set texts
		$requete = "UPDATE ".PREFIX_DB.self::TABLE;
		$requete .= " SET tsubject='".$subject."', tbody='".$body."'";
		$requete .= " WHERE tref='".$ref."' AND tlang='".$lang."'";

		$result = $this->db->exec($requete);
		// Vérification des erreurs
		if (MDB2::isError($result)) {
			echo $result->getDebugInfo().'<br/>';
			echo $result->getMessage();
		}
	}
	/**
	* Ref List
	* @return array: list of references used for texts
	*/
	public function getRefs($lang){
			$requete = "SELECT tref,tcomment FROM ".PREFIX_DB.self::TABLE." WHERE tlang='".$lang."'";
			$result = $this->db->query($requete);
			// Vérification des erreurs
			if (MDB2::isError($result)) {
				echo $result->getDebugInfo().'<br/>';
				echo $result->getMessage();
			}
			
			if($result->numRows()>0){
				$refs = $result->fetchAll(MDB2_FETCHMODE_ASSOC);
			}
	return $refs;
	}
}
?>