<?php

// Copyright © 2003 Frédéric Jaqcuot
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
 * Visualisation d'un adhérent
 *
 * Affichage des cractéristiques d'un adhérent et possibilités de :
 * - Modifier ces caractéristiques
 * - De visualiser les contributions
 * - De saisir une contribution
 * - De générer la carte de membre en pdf
 * 
 * @package    Galette
 * @author     Frédéric Jaqcuot
 * @copyright  2003 Frédéric Jaqcuot
 * @copyright  2007-2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.60
 */
 
require_once('includes/galette.inc.php');

if( !$login->isLogged() ) {
	header("location: index.php");
	die();
}

$id_adh = get_numeric_form_value("id_adh", "");

if( !$login->isAdmin() )
	$id_adh = $login->id;

if ($id_adh=="") {
	header("location: index.php");
	die();
}
if ( isset($_SESSION['galette']['pdf_error']) ) {
	$error_detected[] = $_SESSION['galette']['pdf_error_msg'];
}

require_once('classes/adherent.class.php');
require_once('classes/politeness.class.php');
include(WEB_ROOT."includes/dynamic_fields.inc.php");

$member = new Adherent();
$member->load($id_adh);

// Set caller page ref for cards error reporting	
$_SESSION['galette']['caller']='voir_adherent.php?id_adh='.$id_adh;

// declare dynamic field values
$adherent['dyn'] = get_dynamic_fields($DB, 'adh', $id_adh, true);

// - declare dynamic fields for display
$disabled['dyn'] = array();
$dynamic_fields = prepare_dynamic_fields_for_display($DB, 'adh', $adherent['dyn'], $disabled['dyn'], 0);

if(isset($error_detected))
	$tpl->assign("error_detected",$error_detected);
$tpl->assign('member', $member);
$tpl->assign('pref_lang_img', $i18n->getFlagFromId($member->language));
$tpl->assign('pref_lang', ucfirst($i18n->getNameFromId($member->language)));
$tpl->assign('pref_card_self', $preferences->pref_card_self);
$tpl->assign('dynamic_fields', $dynamic_fields);
$tpl->assign('time', time());
//if we got a mail warning when adding/editing a member, we show it and delete it from session
if( isset($_SESSION['galette']['mail_warning']) ){
	$tpl->assign('mail_warning', $_SESSION['galette']['mail_warning']);
	unset($_SESSION['galette']['mail_warning']);
}
$content = $tpl->fetch('voir_adherent.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
