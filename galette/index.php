<?php
//
//  index.php, 07 octobre 2007
//
// Copyright © 2004 Frédéric Jacquot
// Copyright © 2007 Johan Cwiklinski
//
// File :               	index.php
// Author's email :     	johan@x-tnd.be
// Author's Website :   	http://galette.tuxfamily.org
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//

/**
 * index.php, 07 octobre 2007
 *
 * @package Galette
 * 
 * @author     Frédéric Jacquot
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2007 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GPL License 2.0 or (at your option) any later version
 * @version    $Id$
 */

// test if galette is already installed and redirect to install page if not
$installed = file_exists(dirname( __FILE__).'/includes/config.inc.php');
if (! $installed) {
	header("location: install/index.php");
}

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
	$tpl->assign("languages",drapeaux());
	$tpl->assign("languages_new", $i18n->getList());
	$tpl->assign('loginfault', $loginfault);
	$tpl->display("index.tpl");
}else{
	if ( $login->isAdmin() )
		header('location: gestion_adherents.php');
	else
		header('location: voir_adherent.php');
}
?>
