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
 * gestion_textes.php, 16 septembre 2007
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

if ( !$login->isLogged() ){
	header('location: index.php');
	die();
}elseif ( !$login->isAdmin() ){
	header('location: voir_adherent.php');
	die();
}
include(WEB_ROOT . 'classes/texts.class.php');

// initialize warnings
$error_detected = array();
$warning_detected = array();

if (!isset($_SESSION['cur_lang'])) {
	$_SESSION['cur_lang'] = PREF_LANG;
}

if (!isset($_SESSION['cur_ref'])) {
	$_SESSION['cur_ref'] = 'sub';
}

$texts = new texts();

if (isset($_POST['valid']) && $_POST['valid'] == '1') {
	// Formulaire validé
	// we update texts only if Save button pushed
	if (($_SESSION['cur_ref'] == $_POST['sel_ref']) && ($_SESSION['cur_lang'] == $_POST['sel_lang'])){
		$res = $texts->setTexts(
			$_SESSION['cur_ref'],
			$_SESSION['cur_lang'],
			$_POST['text_subject'],
			$_POST['text_body']);
		$mtxt = $texts->getTexts($_SESSION['cur_ref'], $_SESSION['cur_lang']);

		if( MDB2::isError($res)){
			$error_detected[] = preg_replace('(%s)', $mtxt['tcomment'], _T("Email: '%s' has not been modified!"));
		}else{
			$warning_detected[] = preg_replace('(%s)', $mtxt['tcomment'], _T("Email: '%s' has been successfully modified."));
		}
	}
	$_SESSION['cur_ref'] = $_POST['sel_ref'];
	$_SESSION['cur_lang'] = $_POST['sel_lang'];
}

$tpl->assign('reflist', $texts->getRefs($_SESSION['cur_lang']));
$tpl->assign('langlist', $i18n->getList());
$tpl->assign('cur_lang', $_SESSION['cur_lang']);
$tpl->assign('cur_ref', $_SESSION['cur_ref']);
$tpl->assign('mtxt', $texts->getTexts($_SESSION['cur_ref'], $_SESSION['cur_lang']));
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$content = $tpl->fetch('gestion_textes.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
