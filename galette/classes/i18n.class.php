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

	private $langs;
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
		$this->langs = simplexml_load_file($this->file);

		if( !$lang )
			$this->changeLanguage(self::DEFAULT_LANG);
		else $this->load($lang);
	}

	public function __destruct(){
		$this->s_langs = $this->langs->asXML();
		unset($this->langs);
	}

	public function __wakeup(){
		$this->langs = simplexml_load_string($this->s_langs);
	}

	/**
	* Load language parameters from the XML file
	*/
	public function changeLanguage($id){
		//echo 'trying to change to ' . $id ."\n";
		/** FIXME: is session already started at this point ? It should be :) */
		//ini_set("session.use_trans_sid", "0");
		//session_start();

		$current = $this->langs->xpath('//lang[@id=\'' . $id . '\']');
		//if no match, switch to default
		if(!isset($current[0])) $id = self::DEFAULT_LANG;
		//print_r($current);
		$sxe = $current[0];
		$this->id = $id;
		$this->longid = ( isset($sxe['long']) )?$sxe['long']:$id;
		$this->name = $sxe->longname;
		$this->abbrev = $sxe->shortname;
		$this->flag = $sxe->flag;
		$this->filename = $sxe->filename;

		//we store lang object in session
// 		$_SESSION['galette']['lang'] = serialize($this);
// 		echo 'pre isset ?' . isset($_SESSION['galette']['lang']);
	}

	private function load($id){
		$current = $this->langs->xpath('//lang[@id=\'' . $id . '\']');
		$sxe = $current[0];
		$this->id = $id;
		$this->longid = ( isset($sxe['long']) )?$sxe->long:$id;
		$this->name = $sxe->longname;
		$this->abbrev = $sxe->shortname;
		$this->flag = $sxe->flag;
		$this->filename = $sxe->filename;
	}

	public function getList(){
		$result = array();
		foreach( $this->langs->lang as $lang ){
			$result[] = new i18n( $lang['id'] );
		}

		return $result;
	}

	/**
	* Gets language full name from its ID
	* @param id the language identifier
	*/
	public function getNameFromId($id){
		$current = $this->langs->xpath('//lang[@id=\'' . $id . '\']');
		$sxe = $current[0];
		return $sxe->longname;
	}

	/**
	* Gets the language flag from its ID
	* @param id the language identifier
	*/
	public function getFlagFromId($id){
		$current = $this->langs->xpath('//lang[@id=\'' . $id . '\']');
		$sxe = $current[0];
		return $this->dir . $sxe->flag;
	}

	public function getID(){ return $this->id; }
	public function getLongID(){ return $this->longid; }
	public function getName(){ return utf8_decode($this->name); }
	public function getAbbrev(){ return $this->abbrev; }
	public function getFlag(){ return $this->dir . $this->flag;}
	public function getFileName(){ return $this->filename; }
}
?>