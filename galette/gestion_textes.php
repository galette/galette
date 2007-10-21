<?php
/*
 * gestion_texte.php, 16 septembre 2007
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
include(WEB_ROOT."classes/texts.class.php");

// initialize warnings
$error_detected = array();
$warning_detected = array();

if (!isset($_SESSION["cur_lang"])) {
	$_SESSION["cur_lang"] = PREF_LANG;
}

if (!isset($_SESSION["cur_ref"])) {
	$_SESSION["cur_ref"] = "sub";
}

$texts = new texts();

if (isset($_POST['valid']) && $_POST['valid'] == "1") {
// Formulaire validé
// we update texts only if Save button pushed
	    if (($_SESSION["cur_ref"] ==  $_POST['sel_ref']) && ($_SESSION["cur_lang"] ==  $_POST['sel_lang'])){
			$texts->setTexts($_SESSION["cur_ref"],$_SESSION["cur_lang"], $_POST['text_subject'],$_POST['text_body']);
			$mtxt=$texts->getTexts($_SESSION["cur_ref"],$_SESSION["cur_lang"]);
			$warning_detected[] = _T("Email : \"").$mtxt[tcomment]._T("\" has been succesfully modified");
		 }
		 $_SESSION["cur_ref"] =  $_POST['sel_ref'];
		 $_SESSION["cur_lang"] =  $_POST['sel_lang'];
}

$tpl->assign("reflist",$texts->getRefs($_SESSION["cur_lang"]));
$tpl->assign("langlist",$i18n->getList());
$tpl->assign("cur_lang",$_SESSION["cur_lang"]);
$tpl->assign("cur_ref",$_SESSION["cur_ref"]);
$tpl->assign("mtxt",$texts->getTexts($_SESSION["cur_ref"],$_SESSION["cur_lang"]));
$tpl->assign("error_detected",$error_detected);
$tpl->assign("warning_detected",$warning_detected);
$content = $tpl->fetch("gestion_textes.tpl");
$tpl->assign("content",$content);
$tpl->display("page.tpl");

?>
