<?php
/* picture.php
 * - Display a picture
 * Copyright (c) 2006 Frédéric Jaqcuot
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

	require_once('includes/session.inc.php');

	if ($_SESSION["logged_status"]==0)
	{
		header("location: index.php");
		die();
	}

	require_once('includes/picture.class.php');

	if ($_SESSION["admin_status"]==0)
		$id_adh = $_SESSION["logged_id_adh"];
	else
		$id_adh = $_GET['id_adh'];

	$picture = new picture($id_adh);
	$picture->display();
?>
