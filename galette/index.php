<? // -*- Mode: PHP; tab-width: 2; indent-tabs-mode: nil; c-basic-offset: 4 -*-

 
/* index.php
 * - Identification
 * Copyright (c) 2003 Frédéric Jacquot
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
 
	include("includes/config.inc.php"); 
	include(WEB_ROOT."includes/database.inc.php"); 
  include_once("includes/i18n.inc.php"); 
	include(WEB_ROOT."includes/functions.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php"); 
	 
	if (isset($_POST["ident"])) 
	{ 
	        include(WEB_ROOT."includes/lang.inc.php"); 
		if ($_POST["login"]==PREF_ADMIN_LOGIN && $_POST["password"]==PREF_ADMIN_PASS)
		{
			$_SESSION["logged_status"]=1;
			$_SESSION["admin_status"]=1;
			$_SESSION["logged_username"]=$_POST["login"];
			$_SESSION["logged_nom_adh"]=_("Admin");
			dblog(_("Login"));
		}
		else
		{
			$requete = "SELECT id_adh, bool_admin_adh, nom_adh, 
                                        prenom_adh, mdp_adh
					FROM ".PREFIX_DB."adherents
					WHERE login_adh=" . txt_sqls($_POST["login"]) . "
					AND activite_adh='1'";
			$resultat = &$DB->Execute($requete);
			if (!$resultat->EOF&&
                            ($resultat->fields[4] == $_POST["password"] ||
                             $resultat->fields[4] == 
                               crypt($_POST["password"],$resultat->fields[4]))
                           )
			{
				if ($resultat->fields[1]=="1")
					$_SESSION["admin_status"]=1;
				$_SESSION["logged_id_adh"]=$resultat->fields[0];
				$_SESSION["logged_status"]=1;
				$_SESSION["logged_nom_adh"]=strtoupper($resultat->fields[2]) . " " . strtolower($resultat->fields[3]);
				dblog(_("Login"));
			}
			else
				dblog(_("Authentication failed. Login:")." \"" . $_POST["login"] . "\"");
		}
	}

	if ($_SESSION["logged_status"]!=0)
		header("location: gestion_adherents.php");
	else
	{ 
	  $req = "SELECT pref_lang FROM ".PREFIX_DB."adherents
			WHERE id_adh=".$_SESSION["logged_id_adh"];
	  $pref_lang = &$DB->Execute($req);
	  $pref_lang = $pref_lang->fields[0];
	  include(WEB_ROOT."includes/lang.inc.php"); 
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"> 
<HTML> 
<HEAD> 
<TITLE>Galette <? echo GALETTE_VERSION ?></TITLE> 
<META http-equiv="Content-Type" content="text/html; charset=iso-8859-1"> 
<LINK rel="stylesheet" type="text/css" href="galette.css"> 
</HEAD> 
<BODY bgcolor="#FFFFFF">
<TABLE width="100%" style="height: 100%">
	<TR>
		<TD align="center">
			<IMG src="images/galette.jpg" alt="[ Galette ]" width="103" height="80"><BR>
			<FORM action="index.php" method="post"> 
				<B class="title"><? echo _("Login"); ?></B><BR>
				<BR>
				<BR>
				<TABLE> 
					<TR> 
						<TD><? echo _("Username:"); ?></TD> 
						<TD><INPUT type="text" name="login"></TD> 
					</TR> 
					<TR> 
						<TD><? echo _("Password:"); ?></TD> 
						<TD><INPUT type="password" name="password"></TD> 
					</TR> 
				</TABLE>
				<INPUT type="submit" name="ident" value="<? echo _("Login"); ?>"><BR>
				<BR>
				<A HREF="lostpasswd.php"><? echo _("Lost your password?"); ?></a>
			</FORM>
		</TD>
	</TR>
</TABLE> 
</BODY>
</HTML>

<?
	}
?>
