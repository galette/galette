<? 
 
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
	include(WEB_ROOT."includes/functions.inc.php"); 
	include(WEB_ROOT."includes/lang.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php"); 
	 
	if (isset($_POST["ident"])) 
	{ 
		if ($_POST["login"]==PREF_ADMIN_LOGIN && $_POST["password"]==PREF_ADMIN_PASS)
		{
			$_SESSION["logged_status"]=1;
			$_SESSION["admin_status"]=1;
			$_SESSION["logged_username"]=$_POST["login"];
			$_SESSION["logged_nom_adh"]=_T("Administrateur");
			dblog(_T("Identification"));
		}
		else
		{
			$requete = "SELECT id_adh, bool_admin_adh, nom_adh, prenom_adh
									FROM adherents
									WHERE login_adh=" . txt_sqls($_POST["login"]) . "
									AND activite_adh='1'
									AND mdp_adh=" . txt_sqls($_POST["password"]);
			$resultat = &$DB->Execute($requete);
			if (!$resultat->EOF)
			{
				if ($resultat->fields[1]=="1")
					$_SESSION["admin_status"]=1;
				$_SESSION["logged_id_adh"]=$resultat->fields[0];
				$_SESSION["logged_status"]=1;
				$_SESSION["logged_nom_adh"]=strtoupper($resultat->fields[2]) . " " . strtolower($resultat->fields[3]);
				dblog(_T("Identification"));
			}
			else
				dblog(_T("Echec authentification. Login :")." \"" . $_POST["login"] . "\"");
		}
	} 
	 
	if ($_SESSION["logged_status"]!=0) 
		header("location: gestion_adherents.php");
	else
	{ 
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
			<TABLE border="0">
				<TR>
					<TD><IMG src="images/galette.jpg" alt="[ Galette ]" width="103" height="80">&nbsp;&nbsp;&nbsp;</TD>
					<TD class="acronyme">
						<B>G</B>estionnaire d'<BR>
						<B>A</B>dhérents en<BR>
						<B>L</B>igne<BR>
						<B>E</B>xtrèmement<BR>
						<B>T</B>arabiscoté mais<BR>
						<B>T</B>ellement<BR>
						<B>E</B>fficace...<BR>
						<BR>
					</TD>
				</TR>
			</TABLE>
			<FORM action="index.php" method="post"> 
				<B class="title"><? echo _T("Identification"); ?></B> 
				<BR>
				<TABLE> 
					<TR> 
						<TD><? echo _T("Identifiant :"); ?></TD> 
						<TD><INPUT type="text" name="login"></TD> 
					</TR> 
					<TR> 
						<TD><? echo _T("Mot de passe :"); ?></TD> 
						<TD><INPUT type="password" name="password"></TD> 
					</TR> 
					<TR> 
						<TD>&nbsp;</TD> 
						<TD><INPUT type="submit" name="ident" value="<? echo _T("Identification"); ?>"></TD> 
					</TR> 
				</TABLE> 
			</FORM>
		</TD>
	</TR>
</TABLE> 
</BODY>
</HTML>

<?
	}
?>
