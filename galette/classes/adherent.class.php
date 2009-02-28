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
 * adhrent.class.php, 28 février 2009
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
 * Member class for galette
 *
 * @name Adherent
 * @package Galette
 *
 */

class Adherent {
	const TABLE = 'adherents';
	const PK = 'login_adh';

	/**
	* Default constructor
	*/
	public function __construct(){}
}
?>