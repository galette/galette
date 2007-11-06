<?php
/*
 * utilitaires.php, 31 octobre 2007
 * 
 * This file is part of Galette.
 *
 * Copyright © 2007 John Perr
 *
 * File :               	gestion_texte.php.php
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

// initialize warnings
$error_detected = array();
$warning_detected = array();

$mods = new models();

if (isset($_POST['fieldsfile'])) {
	$_SESSION['galette']['fields_file'] = $_POST['exportfields'];
	header('location: fields_adh.php');
}

if (isset($_POST['xmlupload'])) {
//	$err = $mods->readXMLModels($_POST['loadxml']);
	if ($err) {
		$error_detected = $mods->errors;	
	} else {
		array_push ($warning_detected,$_POST['loadxml']._T(" sucessfully read."));
	}
}


$tpl->assign("loadxml",$_POST['loadxml']);
$tpl->assign("exportfields",empty($_POST['exportfields'])?'adh_fields.txt':$_POST['exportfields']);
$tpl->assign("error_detected",$error_detected);
$tpl->assign("warning_detected",$warning_detected);
$content = $tpl->fetch("utilitaires.tpl");
$tpl->assign("content",$content);
$tpl->display("page.tpl");

?>
