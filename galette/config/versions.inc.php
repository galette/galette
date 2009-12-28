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
 * versions.inc.php
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 *
 * Defines various library versions, to avoid use of problematic symlinks under windows or via FTP.
 */

define('SMARTY_VERSION', '2.6.26');
define('PEAR_VERSION', '1.9.0');
define('MDB2_VERSION', '2.4.1');
define('LOG_VERSION', '1.11.6');
define('TCPDF_VERSION', '4.8.017');
define('JQUERY_VERSION', '1.3.2');
define('JQUERY_UI_VERSION', '1.7.1');
define('JQUERY_MARKITUP_VERSION', '1.1.5');
//will be erased when completly obsoleted
define('ADODB_VERSION', '492');
?>
