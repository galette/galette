<?php
/* index.php
 * - Identification
 * Copyright (c) 2003 Frédéric Jacquot
 * Copyright (c) 2007-2010 Johan Cwiklinski <johan@x-tnd.be>
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
	/*
	if (isset($_GET['pref_lang']))
	{
		// set priority to the GET var, which overrides the cookie.
		setcookie('pref_lang',$_GET['pref_lang']);
		$_SESSION["pref_lang"]=$_GET['pref_lang'];
	}
	*/

	// test if galette is already installed and redirect to install page if not
	$installed = file_exists(dirname( __FILE__).'/config/config.inc.php');
	if (! $installed) {
		header("location: install/index.php");
	}

	include("config/config.inc.php");
	include(WEB_ROOT."includes/database.inc.php");
	include(WEB_ROOT."includes/functions.inc.php");
	include(WEB_ROOT."includes/session.inc.php");
	include_once(WEB_ROOT."includes/i18n.inc.php");
	include_once(WEB_ROOT."includes/smarty.inc.php");
  require_once(WEB_ROOT."includes/picture.class.php");

	function self_adhesion()
	{
		global $_POST, $_GET, $pref_lang;
		if (isset($_POST["pref_lang"])) $pref_lang=$_POST["pref_lang"];
		if (isset($_GET["pref_lang"])) $pref_lang=$_GET["pref_lang"];
		if (!isset($pref_lang)) $pref_lang=PREF_LANG;
		echo "<a href=\"self_adherent.php?pref_lang=$pref_lang\">"._T("Subscribe")."</a>";
	}

	// Authentication procedure
	if (isset($_POST["ident"]))
	{
		if (
			($_POST["login"]==PREF_ADMIN_LOGIN && $_POST["password"]==PREF_ADMIN_PASS) ||
			($_POST["login"]==PREF_ADMIN_LOGIN && md5($_POST["password"])==PREF_ADMIN_PASS)
			)
		{
			$_SESSION["logged_status"]=1;
			$_SESSION["admin_status"]=1;
			$_SESSION["logged_username"]=$_POST["login"];
			$_SESSION["logged_nom_adh"]="Admin";
			dblog("Login");
		}
		else
		{
			$requete = "SELECT id_adh, bool_admin_adh, nom_adh, prenom_adh, mdp_adh, pref_lang
					FROM ".PREFIX_DB."adherents
					WHERE login_adh=" . $DB->qstr($_POST["login"], get_magic_quotes_gpc()) . "
					AND activite_adh='1'";
			$resultat = &$DB->Execute($requete);
			if (!$resultat->EOF &&
				($resultat->fields[4] == $_POST["password"] ||
				$resultat->fields[4] == md5($_POST["password"]))
                           )
			{
				if ($resultat->fields[1]=="1")
					$_SESSION["admin_status"]=1;
				$_SESSION["logged_id_adh"]=$resultat->fields[0];
				$_SESSION["logged_status"]=1;
				$_SESSION["logged_nom_adh"]=strtoupper($resultat->fields[2]) . " " . strtolower($resultat->fields[3]);
				$pref_lang = $resultat->fields[5];
				setcookie("pref_lang",$pref_lang);
				dblog("Login");
			}
			else
				dblog("Authentication failed",$_POST["login"]);
		}
	}

	if ($_SESSION["logged_status"]!=0)
		header("location: gestion_adherents.php");
	else
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
		$tpl->display("index.tpl");
	}
?>
