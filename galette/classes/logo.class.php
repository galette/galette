<?php

// Copyright © 2006 Frédéric Jaqcuot
// Copyright © 2007-2009 Johan Cwiklinski
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
 * Picture handling
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski
 * @copyright  2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id: logo.class.php 546 2009-03-05 06:09:00Z trashy $
 */

require_once('picture.class.php');

class Logo extends Picture{
	protected $id = 'custom_logo';

	/**
	* Default constructor.
	* @param int id_adh the id of the member
	*/
	public function __construct(){
		parent::__construct($this->id);
	}

	protected function getDefaultPicture(){
		$this->file_path = _current_template_path . 'images/galette.png';
		$this->format = 'png';
		$this->mime = 'image/png';
	}
}
?>
