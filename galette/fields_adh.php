<?php
/*
 * fields_adh.php, 31 octobre 2007
 * 
 * This file is part of Galette.
 *
 * Copyright © 2007 John Perr
 *
 * File :               	fields_adh.php
 * Author's Website :   	http://galette.tuxfamily.org
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
 */

require_once('includes/galette.inc.php');

if ($_SESSION["logged_status"]==0){
	header("location: index.php");
	die();
}
if ($_SESSION["admin_status"]==0){
	header("location: voir_adherent.php");
	die();
}
include(WEB_ROOT."classes/models.class.php");

$mods = new models();

$mods->writeFields($_SESSION['galette']['fields_file']);

?>
