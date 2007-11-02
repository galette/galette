<?php
/*
 * required.class.php, 06 juillet 2007
 * 
 * This file is part of Galette.
 *
 * Copyright © 2007 Johan Cwiklinski
 *
 * File :               	required.class.php
 * Author's email :     	johan@x-tnd.be
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

if( !$login->isLogged() ){
	header("location: index.php");
	die();
}
if ( !$login->isAdmin() ){
	header("location: voir_adherent.php");
	die();
}

include(WEB_ROOT."classes/required.class.php");
include(WEB_ROOT.'champs_adherents.php');

$requires = new Required();
$fields = $requires->getFields();

/* Fields that are not visible in the 
* form should not be visible here.
*/
unset($fields[array_search('id_adh', $fields)]);
unset($fields[array_search('date_echeance', $fields)]);
unset($fields[array_search('bool_display_info', $fields)]);
unset($fields[array_search('bool_display_in', $fields)]);
unset($fields[array_search('bool_exempt_adh', $fields)]);
unset($fields[array_search('bool_admin_adh', $fields)]);
unset($fields[array_search('lieu_naissance', $fields)]); //this one does not appear on the form. TODO
unset($fields[array_search('activite_adh', $fields)]);
unset($fields[array_search('date_crea_adh', $fields)]);

if(isset($_POST) && count($_POST)>1){
// 	echo "execute !";
	$values = array();
	foreach($_POST as $field => $value)
		if($value == 1) $values[] = $field;
	//we update values
	$requires->setRequired($values);
}

$required = $requires->getRequired();

$tpl->assign("time",time());
$tpl->assign('fields', $fields);
$tpl->assign('adh_fields', $adh_fields);
$tpl->assign('required', $required);
$content = $tpl->fetch("champ_requis.tpl");
$tpl->assign("content",$content);
$tpl->display("page.tpl");

?>
