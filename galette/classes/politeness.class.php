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
 * politeness.class.php, 4 mars 2009
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
 * Politeness class for galette
 *
 * @name Politeness
 * @package Galette
 *
 */

class Politeness {
	const MR = 1;
	const MRS = 2;
	const MISS = 3;
	const COMPANY = 4;

	/**
	* Default constructor
	*/
	public function __construct(){}

	public static function getPoliteness($politeness){
		switch( $politeness ){
			case self::MR:
				return _T("Mr.");
				break;
			case self::MRS:
				return _T("Mrs.");
				break;
			case self::MISS:
				return _T("Miss.");
				break;
			case self::COMPANY:
				return _T("Society");
				break;
			default:
				return '';
				break;
		}
	}
}
?>