<?php
//
//  i18n.class.php, 05 octobre 2007
//
// Copyright © 2007 Johan Cwiklinski
//
// File :               	i18n.class.php
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
 * i18n.class.php, 06 juillet 2007
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
 * i18n class for galette
 *
 * @name i18n
 * @package Galette
 *
 */

class i18n{
	private $id;
	private $longid;
	private $name;
	private $abbrev;
	private $flag;
	private $filename;

	private $s_langs;

	const DEFAULT_LANG = 'fr_FR';

	private $dir = 'lang/';
	private $path;
	private $file = 'languages.xml';

	/**
	* Default constructor.
	* Initialize default language and set environment variables
	*/
	function __construct($lang = false){
		$this->path = WEB_ROOT . $this->dir;
		$this->file = $this->path . $this->file;

		if( !$lang )
			$this->changeLanguage(self::DEFAULT_LANG);
		else $this->load($lang);
	}

	/**
	* Load language parameters from the XML file
	*/
	public function changeLanguage($id){
		global $log;
		$log->log('Trying to set locale to ' . $id, PEAR_LOG_DEBUG);

		$xml = simplexml_load_file($this->file);
		$current = $xml->xpath('//lang[@id=\'' . $id . '\']');

		//if no match, switch to default
		if(!isset($current[0])){
			$log->log($id . ' does not exist in XML file, switching to default.', PEAR_LOG_WARNING);
			$id = self::DEFAULT_LANG;
			//do not forget to reload informations from the xml file
			$current = $xml->xpath('//lang[@id=\'' . $id . '\']');
		}

		$sxe = $current[0];
		$this->id = $id;
		$this->longid = ( isset($sxe['long']) )?(string)$sxe['long']:$id;
		$this->name = (string)$sxe->longname;
		$this->abbrev = (string)$sxe->shortname;
		$this->flag = (string)$sxe->flag;
		$this->filename = (string)$sxe->filename;
	}

	private function load($id){
		$xml = simplexml_load_file($this->file);
		$current = $xml->xpath('//lang[@id=\'' . $id . '\']');
		$sxe = $current[0];
		$this->id = $id;
		$this->longid = ( isset($sxe['long']) )?(string)$sxe['long']:$id;
		$this->name = (string)$sxe->longname;
		$this->abbrev = (string)$sxe->shortname;
		$this->flag = (string)$sxe->flag;
		$this->filename = (string)$sxe->filename;
	}

	public function getList(){
		$result = array();
		$xml = simplexml_load_file($this->file);
		foreach( $xml->lang as $lang ){
			$result[] = new i18n( $lang['id'] );
		}

		return $result;
	}

	/**
	* Gets language full name from its ID
	* @param id the language identifier
	*/
	public function getNameFromId($id){
		$xml = simplexml_load_file($this->file);
		$current = $xml->xpath('//lang[@id=\'' . $id . '\']');
		$sxe = $current[0];
		return (string)$sxe->longname;
	}

	/**
	* Gets the language flag from its ID
	* @param id the language identifier
	*/
	public function getFlagFromId($id){
		$xml = simplexml_load_file($this->file);
		$current = $xml->xpath('//lang[@id=\'' . $id . '\']');
		$sxe = $current[0];
		return $this->dir . $sxe->flag;
	}

	public function getID(){ return $this->id; }
	public function getLongID(){ return $this->longid; }
	public function getName(){ return $this->name; }
	public function getAbbrev(){ return $this->abbrev; }
	public function getFlag(){ return $this->dir . $this->flag;}
	public function getFileName(){ return $this->filename; }
}
?>