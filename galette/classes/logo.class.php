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
 * Logo handling
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski
 * @copyright  2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 */

/** @ignore */
require_once('picture.class.php');

/**
 * This class stores and serve the logo.
 * If no custom logo is found, we take galette's default one.
 * @name Logo
 * @package Galette
 */
class Logo extends Picture{
	protected $id = 'custom_logo';
	//database wants a member id (integer), not a string. Will be used to query the correct id
	protected $db_id = 0;

	/**
	* Default constructor.
	*/
	public function __construct(){
		parent::__construct($this->id);
	}

	/**
	* @see Picture::getDefaultPicture()
	*/
	protected function getDefaultPicture(){
		$this->file_path = _current_template_path . 'images/galette.png';
		$this->format = 'png';
		$this->mime = 'image/png';
		$this->custom = false;
	}

	/**
	* @see picture::getCheckFileQuery()
	*/
	protected function getCheckFileQuery(){
		return 'SELECT picture, format FROM ' . PREFIX_DB . self::TABLE . ' WHERE ' . self::PK . '=\'' . $this->db_id . '\'';
	}
}
?>
