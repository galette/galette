<?php
/* lostpasswd.php
 * - Lost password
 * Copyright (c) 2004 Stéphane Salès
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

	function isEmail($login) {
		if( empty($login) ) {
			$GLOBALS["error_detected"] = _T("login vide");
		} else {
			$req = "SELECT email_adh
				FROM ".PREFIX_DB."adherents
				WHERE login_adh=".txt_sqls($login);
			$result = &$GLOBALS["DB"]->Execute($req);

			if ($result->EOF) {
				$GLOBALS["error_detected"] = _T("login inexistant");
				dblog(_T("Login inexistant envoyé via le formulaire de récupération du mot de passe")." \"" . $login ."\"");
			}else{
				$email=$result->fields[0];
				if( empty($email) ) {
					$GLOBALS["error_detected"] = _T("Ce compte ne contient pas d'adresse email, veuillez contacter un administrateur");
					dblog(_T("Demande d'envoi de mot de passe mais email vide. Login :")." \"" . $login . "\"");
				}else
				return $email;
			}
		}
	}

	if( isset($_POST["login"]) ) {
		$login_adh=$_POST['login'];
		$email_adh=isEmail($login_adh);

		//send the password
		if(	$email_adh!="" )
		{
			$req = "SELECT mdp_adh from ".PREFIX_DB."adherents where login_adh=".txt_sqls($login_adh);
			$result = &$DB->Execute($req);
			if (!$result->EOF)
				$mdp_adh = $result->fields[0];
			$mail_subject = _T("Vos identifiants Galette");
			$mail_text =  _T("Bonjour,")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Quelqu'un (sûrement vous) à demandé à ce que l'on vous renvoie votre mot de passe.")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Veuillez vous identifier à cette adresse :")."\n";
			$mail_text .= "http://".$_SERVER["SERVER_NAME"].dirname($_SERVER["REQUEST_URI"])."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Identifiant :")." ".custom_html_entity_decode($login_adh, ENT_QUOTES)."\n";
			$mail_text .= _T("Mot de passe :")." ".custom_html_entity_decode($mdp_adh, ENT_QUOTES)."\n";
			$mail_text .= "\n";
			$mail_text .= _T("A trés bientôt !")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("(ce mail est un envoi automatique)")."\n";
			$mail_headers = "From: ".PREF_EMAIL_NOM." <".PREF_EMAIL.">\n";
			if(  mail($email_adh,$mail_subject,$mail_text, $mail_headers) ) {
				dblog(_T("Mot de passe envoyé. Login :")." \"" . $login_adh . "\"");
				$warning_detected = _T("Mot de passe envoyé. Login :")." \"" . $login_adh . "\"";
				$password_sent = true;
			}else{
				dblog(_T("Un problème est survenu dans l'envoi du mot de passe pour le compte :")." \"" . $login_adh . "\"");
				$warning_detected = _T("Un problème est survenu dans l'envoi du mot de passe pour le compte :")." \"" . $login_adh . "\"";
			}
		}
	}
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
			<FORM action="lostpasswd.php" method="post"> 
				<B class="title"><? echo _T("Récupération de mot de passe"); ?></B><BR>
				<BR>
				
<?php if( isset($error_detected) ) { ?>
<DIV id="errorbox" style="width: 300px">
	<H1><? echo _T("- ERREUR -"); ?></H1>
	<UL>
		<? echo $error_detected; ?>
	</UL>
</DIV>
<?php } ?>

<?php if( isset($warning_detected) ) { ?>
<DIV id="errorbox" style="width: 300px">
	<H1><? echo _T("- AVERTISSEMENT -"); ?></H1>
	<UL>
		<? echo $warning_detected; ?>
	</UL>
</DIV>
<?php } ?>

				<BR>
<?php if( !isset($password_sent) ) { ?>
				<TABLE> 
					<TR> 
						<TD><? echo _T("Identifiant :"); ?></TD> 
						<TD><INPUT type="text" name="login"></TD> 
					</TR> 
				</TABLE>
				<INPUT type="submit" name="lostpasswd" value="<? echo _T("Envoyez-moi mon mot de passe"); ?>">
				<BR>
				<BR>
<?php } ?>
			</FORM>
			<FORM action="index.php" method="get">
				<INPUT type="submit" name="lostpasswd" value="<? echo _T("Retour à l'identification"); ?>">
			</FORM>
		</TD>
	</TR>
</TABLE> 
</BODY>
</HTML>
