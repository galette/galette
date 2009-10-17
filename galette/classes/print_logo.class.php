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
 * Logo handling - for printing
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski
 * @copyright  2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id: print_logo.class.php 546 2009-03-05 06:09:00Z trashy $
 * @since      Disponible depuis la Release 0.7alpha
 */

/** @ignore */
require_once('logo.class.php');

/**
* This class stores a logo for printing that could be different from the default one.
* If no print logo is found, we take the default Logo instead.
* @name PrintLogo
* @package Galette
*/
class PrintLogo extends Logo{
	protected $id = 'custom_print_logo';
	//database wants a member id (integer), not a string. Will be used to query the correct id
	protected $db_id = 999999;

	/**
	* @see Logo::getDefaultPicture()
	*/
	protected function getDefaultPicture(){
		//if we are here, we want to serve default logo
		$pic = new Logo();
		$this->file_path = $pic->getPath();
		$this->format = $pic->getFormat();
		$this->mime = $pic->getMime();
		//anyways, we have no custom print logo
		$this->custom = false;
	}

}
?>
