<?php

// Copyright © 2007 John perr
// Copyright © 2007-2008 Johan Cwiklinski
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
 * utilitaires.php, 31 octobre 2007
 *
 * @package    Galette
 *
 * @author     John Perr
 * @copyright  2007 John Perr
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7
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
