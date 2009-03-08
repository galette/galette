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
 * varslist.class.php, 7 mars 2009
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
 * Members list parameters class for galette
 *
 * @name	VarsList
 * @package	Galette
 *
 */

class VarsList{
	private $current_page;
	private $orderby;
	private $ordered;
	private $show;

	//filters
	private $search_str;
	private $field_filter;
	private $state_filter;
	private $status_filter;

	private $selected;
	private $unreachable;

	const ORDER_ASC = 'ASC';
	const ORDER_DESC = 'DESC';
	
	/**
	* Default constructor
	*/
	public function __construct(){
		$this->reinit();
	}

	/**
	* Reinit default parameters
	*/
	public function reinit(){
		global $preferences;
		$this->current_page = 1;
		$this->orderby = 'nom_adh';
		$this->ordered = self::ORDER_ASC;
		$this->show = $preferences->pref_numrows;
		$this->search_str = null;
		$this->field_filter = null;
		$this->state_filter = null;
		$this->status_filter = null;
		$this->selected = array();
	}

	/**
	* Reset selected array
	*/
	public function clearSelected(){
		$this->selected = array();
	}

	/**
	* Invert sort order
	*/
	public function invertorder(){
		$actual=$this->ordered;
		if($actual == self::ORDER_ASC){
			$this->ordered = self::ORDER_DESC;
		}
		if($actual == self::ORDER_DESC){
			$this->ordered = self::ORDER_ASC;
		}
	}

	/* GETTERS */
	public function __get($name){
		global $log;
		$return_ok = array(
			'current_page',
			'orderby',
			'ordered',
			'show',
			'search_str',
			'field_filter',
			'state_filter',
			'status_filter',
			'selected',
			'unreachable',
		);
		if(in_array($name, $return_ok))
			return $this->$name;
		else
			$log->log('[varslist.class.php] Unable to get proprety `' . $name . '`', PEAR_LOG_WARNING);
	}

	/* SETTERS */
	public function __set($name, $value){
		global $log;

		switch($name){
			case 'current_page':
				$log->log('[varslist.class.php] Setting property `' . $name . '`', PEAR_LOG_DEBUG);
				if( is_int($value) && $value > 0 ){
					$this->$name = $value;
				} else {
					$log->log('[varslist.class.php] Value for field `' . $name . '` should be a positive integer - (' . gettype($value) . ')' . $value . ' given', PEAR_LOG_WARNING);
				}
				break;
			case 'show':
				$log->log('[varslist.class.php] Setting property `' . $name . '`', PEAR_LOG_DEBUG);
				if($value == 'all' || preg_match('/[[:digit:]]/', $value) && $value > 0)
					$this->$name = (int)$value;
				else
					$log->log('[varslist.class.php] Value for `' . $name . '` should be a positive integer or \'all\' - (' . gettype($value) . ')' . $value . ' given', PEAR_LOG_WARNING);
				break;
			case 'selected':
			case 'unreachable':
				$log->log('[varslist.class.php] Setting property `' . $name . '`', PEAR_LOG_DEBUG);
				if(is_array($value)){
					$this->$name = $value;
				} else
					$log->log('[varslist.class.php] Value for property `' . $name . '` should be an array (' . gettype($value) . ' given)', PEAR_LOG_DEBUG);
				break;
			default:
				$log->log('[varslist.class.php] Unable to set proprety `' . $name . '`', PEAR_LOG_WARNING);
				break;
		}
	}
}
?>