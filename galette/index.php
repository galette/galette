<?php

// Copyright © 2004 Frédéric Jaqcuot
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
 * index.php
 *
 * @package    Galette
 *
 * @author     Frédéric Jaqcuot
 * @copyright  2004 Frédéric Jaqcuot
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 */

require_once('includes/galette.inc.php');
//fo default, there is no login error
$loginfault = false;

if( isset($_GET['logout']) ){
	$login->logOut();
	$_SESSION['galette']['login'] = null;
	unset($_SESSION['galette']['login']);
}

// Authentication procedure
if (isset($_POST["ident"]))
{
	if ( $_POST['login'] == PREF_ADMIN_LOGIN && md5($_POST['password']) == PREF_ADMIN_PASS )
	{
		$login->logAdmin($_POST['login']);
		$_SESSION['galette']['login'] = serialize($login);
		//for pre 0.7 compat while under devel
		$_SESSION['logged_status'] = 1;
		$_SESSION['admin_status'] = 1;
		$_SESSION['logged_username'] = $_POST['login'];
		$_SESSION['logged_nom_adh'] = 'Admin';
		//end backward compat
		dblog('Login');
		header('location: gestion_adherents.php');
	}
	else
	{
		$login->logIn($_POST['login'], md5($_POST['password']));

		if($login->isLogged()){
			$_SESSION['galette']['login'] = serialize($login);
			//for pre 0.7 compat while under devel
			if($login->isAdmin()) $_SESSION['admin_status'] = 1;
			$_SESSION['logged_id_adh'] = $login->id;
			$_SESSION['logged_status'] = 1;
			$_SESSION['logged_nom_adh']=strtoupper($login->name) . ' ' . strtolower($login->surname);
			$pref_lang = $login->lang;
			setcookie('pref_lang', $pref_lang);
			//end backward compat
			dblog('Login');
			/** FIXME: users should no try to go to admin interface */
			header('location: gestion_adherents.php');
		}else{
			$loginfault = true;
			dblog('Authentication failed', $_POST['login']);
		}
	}
}

if( !$login->isLogged() )
{
	//check if there's a custom logo
	$customLogo =& new picture(0);
	if ( $customLogo->HAS_PICTURE ) {
		$_SESSION["customLogo"] = true;
		$_SESSION["customLogoFormat"] = $customLogo->FORMAT;
		$_SESSION["customLogoHeight"] = $customLogo->OPTIMAL_HEIGHT;
		$_SESSION["customLogoWidth"] = $customLogo->OPTIMAL_WIDTH;
	} else {
		$_SESSION["customLogo"] = false;
	}

	// display page
	$tpl->assign("languages", $i18n->getList());
	$tpl->assign('loginfault', $loginfault);
	$tpl->display("index.tpl");
}else{
	if ( $login->isAdmin() )
		header('location: gestion_adherents.php');
	else
		header('location: voir_adherent.php');
}
?>
