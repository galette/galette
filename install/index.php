<?
	session_start();
	define("WEB_ROOT", realpath(dirname($_SERVER["SCRIPT_FILENAME"])."/../")."/");
	$step="1";
	$error_detected="";
	
	// traitement page 1 - language
	if (isset($_POST["install_lang"]))
	{
		if (file_exists(WEB_ROOT . "lang/lang_" . $_POST["install_lang"] . ".php"))
		{
			define("PREF_LANG",$_POST["install_lang"]);
			$step="2";
			include ("../includes/lang.inc.php");
		}
		else
	  		$error_detected .= "<LI>Unknown language</LI>";
	 }

	if ($error_detected=="" && isset($_POST["install_type"]))
	{
		if ($_POST["install_type"]=="install")
			$step="i3";
		elseif (substr($_POST["install_type"],0,7)=="upgrade")
			$step="u3";
		else
	  		$error_detected .= "<LI>"._T("Type d'installation inconnu")."</LI>";
	 }

	if ($error_detected=="" && isset($_POST["install_permsok"]))
	{
		if ($_POST["install_type"]=="install")
			$step="i4";
		elseif (substr($_POST["install_type"],0,7)=="upgrade")
			$step="u4";
		else
	  		$error_detected .= "<LI>"._T("Type d'installation inconnu")."</LI>";
	 }

	if ($error_detected=="" && isset($_POST["install_dbtype"])  
		&& isset($_POST["install_dbhost"]) 
		&& isset($_POST["install_dbuser"]) 
		&& isset($_POST["install_dbpass"]) 
		&& isset($_POST["install_dbname"])
		&& isset($_POST["install_dbprefix"]))
	{
		if ($_POST["install_dbtype"]!="mysql" && $_POST["install_dbtype"]!="pgsql")
	  		$error_detected .= "<IMG src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Type de base inconnu")."<BR>";
		if ($_POST["install_dbuser"]=="")
	  		$error_detected .= "<IMG src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Nom d'utilisateur vide")."<BR>";
		if ($_POST["install_dbpass"]=="")
	  		$error_detected .= "<IMG src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Mot de passe vide")."<BR>";
		if ($_POST["install_dbname"]=="")
	  		$error_detected .= "<IMG src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Nom de la base non précisé")."<BR>";
		if ($error_detected=="")
		{
			if (isset($_POST["install_dbconn_ok"]))
			{
				include(WEB_ROOT."/includes/adodb/adodb.inc.php");
				$DB = ADONewConnection($_POST["install_dbtype"]);
				$DB->debug = false;
				$permsdb_ok = true;
				@$DB->Connect($_POST["install_dbhost"], $_POST["install_dbuser"], $_POST["install_dbpass"], $_POST["install_dbname"]);
				if ($_POST["install_type"]=="install")
					$step="i6";
				elseif (substr($_POST["install_type"],0,7)=="upgrade")
					$step="u6";
					
				if (isset($_POST["install_dbperms_ok"]))
				if ($_POST["install_type"]=="install")
					$step="i7";					
				elseif (substr($_POST["install_type"],0,7)=="upgrade")
					$step="u7";
					
				if (isset($_POST["install_dbwrite_ok"]))
				if ($_POST["install_type"]=="install")
					$step="i8";					
				elseif (substr($_POST["install_type"],0,7)=="upgrade")
					$step="u8";
					
				if (isset($_POST["install_adminlogin"]) && isset($_POST["install_adminpass"]))
				{
					if ($_POST["install_adminlogin"]=="")
				  		$error_detected .= "<IMG src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Nom d'utilisateur vide")."<BR>";
					if ($_POST["install_adminpass"]=="")
				  		$error_detected .= "<IMG src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Mot de passe vide")."<BR>";
					if ($error_detected=="")
					if ($_POST["install_type"]=="install")
						$step="i9";					
					elseif (substr($_POST["install_type"],0,7)=="upgrade")
						$step="u9";
						
					if (isset($_POST["install_prefs_ok"]))
					if ($_POST["install_type"]=="install")
						$step="i10";					
					elseif (substr($_POST["install_type"],0,7)=="upgrade")
						$step="u10";
				}					
			}
			else
				$step="i5";
		}
	 }
	
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"> 
<HTML> 
<HEAD> 
	<TITLE>Galette Installation</TITLE> 
	<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=iso-8859-1"> 
	<LINK rel="stylesheet" type="text/css" href="../galette.css" > 
</HEAD> 
<H1 class="titreinstall">Galette installation</H1>
<DIV id="installpage" align="center">
<BR>
	
<?
	switch ($step)
	{
		case "1":
?>

	<H1>Welcome to the Galette Install!</H1>
	<P>Please select your language</P>
	<FORM action="index.php" method="POST">
		<SELECT name="install_lang">
<?
			$path = "../lang";
			$dir_handle = @opendir($path);
			while ($file = readdir($dir_handle))
			{
				if (substr($file,0,5)=="lang_" && substr($file,-4)==".php")
				{
		        $file = substr(substr($file,5),0,-4);
?>
		<OPTION value="<? echo $file; ?>"><? echo ucfirst($file); ?></OPTION>
<?
				}
			}
			closedir($dir_handle);
?>
		</SELECT>
		<P id="submitbutton3">
			<INPUT type="submit" value="Next Page">
		</P>
	</FORM>
	<BR>
	</DIV>
	<H1 class="footerinstall">Step 1 - Language</H1>

<?
			break;
		case "2":
?>

	<H1><? echo _T("Type d'installation"); ?></H1>
	<P><? echo _T("Selectionnez le type d'installation à lancer"); ?></P>
	<FORM action="index.php" method="POST">
		<P>
			<INPUT type="radio" name="install_type" value="install" SELECTED> <? echo _T("Nouvelle installation :"); ?><BR>
		 	<? echo _T("Vous installez Galette pour la première fois, ou vous souhaitez écraser une ancienne version de Galette sans conserver vos données"); ?>
		</P>
<?
			$dh = opendir("sql");
			$update_scripts = array();
			while (($file = readdir($dh)) !== false)
			{
				if (ereg("upgrade-to-(.*)-mysql.sql",$file,$ver))
					$update_scripts[] = $ver[1];
			}
			closedir($dh);
			asort($update_scripts);
			$last = "0.00";
			while (list ($key, $val) = each ($update_scripts))
			{
?>
		<P>
			<INPUT type="radio" name="install_type" value="upgrade-<? echo $val; ?>"> <? echo _T("Mise à jour :"); ?><BR>
<?
				if ($last!=number_format($val-0.01,2))
					echo _T("Votre version actuelle de Galette est comprise entre")." ".$last." "._T("et")." ".number_format($val-0.01,2)."<br>";
				else
					echo _T("Votre version actuelle de Galette est la")." ".number_format($val-0.01,2)."<br>";
				$last = $val;
				echo _T("Attention : Pensez à sauvegarder votre base existante.");
?>
		</P>
<?
			}
?>
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
	</FORM>
	<BR>
	</DIV>
	<H1 class="footerinstall"><? echo _T("Etape 2 - Type d'installation"); ?></H1>

<?
			break;
?>

<?
			break;
		case "i3":
		case "u3":
?>

	<H1><? echo _T("Permissions de fichiers"); ?></H1>
	<P><? echo _T("Vérification des permissions des fichiers et dossier"); ?></P>
	<TABLE>
<?
			$perms_ok = true;
			$arr = array("includes/config.inc.php", "photos");
			foreach ($arr as $fileperm)
			{
				if (is_dir(WEB_ROOT."/".$fileperm))
					$texttype = _T("Le dossier");
				else
					$texttype = _T("Le fichier");
			
				if (!is_writable(WEB_ROOT."/".$fileperm))
				{
					$perms_ok = false;
?>
		<TR>
			<TD>
				<IMG src="no.gif" width="6" height="12" border="0" alt="">
				<? echo $texttype . " " . $fileperm . " " . _T("n'est pas autorisé en écriture"); ?>
			</TD>
		</TR>
<?
				}
				else
				{
?>
		<TR>
			<TD>
				<IMG src="yes.gif" width="6" height="12" border="0" alt="">
				<? echo $texttype . " " . $fileperm . " " . _T("est autorisé en écriture"); ?>
			</TD>
		</TR>
<?
				}
			}
?>
	</TABLE>
<?
			if (!$perms_ok)
			{
?>
	<P>
		<? if ($step=="i3") echo _T("Pour fonctionner correctement, Galette a besoin d'avoir les droits en écriture sur ces fichiers."); ?>
		<? if ($step=="u3") echo _T("Pour être mis à jour et fonctionner correctement, Galette a besoin d'avoir les droits en écriture sur ces fichiers."); ?>
	</P>
	<P>
		<? echo _T("Sous UNIX/Linux, vous pouvez donner ces droits par les commandes"); ?><BR>
		<CODE>chown <I><? echo _T("utilisateur_apache"); ?></I> <I><? echo _T("nom_fichier"); ?></I><BR>
		chmod 600 <I><? echo _T("nom_fichier"); ?></I> <? echo _T("(pour un fichier)"); ?><BR>
		chmod 700 <I><? echo _T("nom_dossier"); ?></I> <? echo _T("(pour un dossier)"); ?></CODE>
	<P>
	<P>
		<? echo _T("Sous Windows, vérifiez que les fichiers en question ne sont pas en lecture seule dans leurs propriétés."); ?>
	<P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton2">
			<INPUT type="submit" value="<? echo _T("Rééssayer"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
	</FORM>		
<?
			}
			else
			{
?>
	<P><? echo _T("Les permissions des fichiers sont correctes !"); ?></P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
	</FORM>
<?
			}
?>
	<BR>
	</DIV>
	<H1 class="footerinstall"><? echo _T("Etape 3 - Permissions"); ?></H1>

<?
			break;
			case "i4":
			case "u4";
?>

	<H1><? echo _T("Base de données"); ?></H1>
	<P>
<?
				if ($error_detected!="")
					echo "<TABLE><TR><TD>".$error_detected."</TD></TR></TABLE><BR>";
?>	
		<? if ($step=="i4") echo _T("Si ce n'est pas déjà fait, créez une base de données et un utilisateur pour Galette."); ?><BR>
		<? if ($step=="u4") echo _T("Veuillez entrer les paramètres de connexion à la base existante."); ?><BR>
		<? echo _T("Les droits nécessaires sont CREATE, DROP, DELETE, UPDATE, SELECT et INSERT."); ?></P>
	<FORM action="index.php" method="POST">
		<TABLE>
			<TR>
				<TD><? echo _T("Type de base de données :"); ?></TD>
				<TD>
					<SELECT name="install_dbtype">
						<OPTION value="mysql">MySQL</OPTION>
						<OPTION value="pgsql">PostgreSQL</OPTION>
					</SELECT>
				</TD>
			</TR>
			<TR>
				<TD><? echo _T("Hôte :"); ?></TD>
				<TD>
					<INPUT type="text" name="install_dbhost" value="<? if(isset($_POST["install_dbhost"])) echo $_POST["install_dbhost"]; ?>">
				</TD>
			</TR>
			<TR>
				<TD><? echo _T("Utilisateur :"); ?></TD>
				<TD>
					<INPUT type="text" name="install_dbuser" value="<? if(isset($_POST["install_dbuser"])) echo $_POST["install_dbuser"]; ?>">
				</TD>
			</TR>
			<TR>
				<TD><? echo _T("Mot de passe :"); ?></TD>
				<TD>
					<INPUT type="password" name="install_dbpass" value="<? if(isset($_POST["install_dbpass"])) echo $_POST["install_dbpass"]; ?>">
				</TD>
			</TR>
			<TR>
				<TD><? echo _T("Nom de la base :"); ?></TD>
				<TD>
					<INPUT type="text" name="install_dbname" value="<? if(isset($_POST["install_dbname"])) echo $_POST["install_dbname"]; ?>">
				</TD>
			</TR>					
                        <TR>
                                <TD><? echo _T("Prefixe de table :"); ?></TD>
                                <TD>
                                        <INPUT type="text" name="install_dbprefix" value="<? if(isset($_POST["install_dbprefix"])) echo $_POST["install_dbprefix"]; else echo "galette_" ?>">
                                </TD>
                	</TR>
		</TABLE>
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
	</FORM>
	<BR>
	</DIV>
	<H1 class="footerinstall"><? echo _T("Etape 4 - Base de données"); ?></H1>
	
<?
			break;
			case "i5":
			case "u5":
?>

	<H1><? echo _T("Vérification de la base"); ?></H1>
	<P><? echo _T("Vérification des paramètres et de l'existence de la base"); ?></P>
<?
				include(WEB_ROOT."/includes/adodb/adodb.inc.php");
				$DB = ADONewConnection($_POST["install_dbtype"]);
				$DB->debug = false;
				$permsdb_ok = true;
				if(!@$DB->Connect($_POST["install_dbhost"], $_POST["install_dbuser"], $_POST["install_dbpass"], $_POST["install_dbname"]))
				{
					$permsdb_ok = false;
					echo "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Connexion à la base impossible")."<BR>";
				}
				else
				{
					echo "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("La connexion à la base est établie")."<BR>";
					$DB->Close();
				}

				if (!$permsdb_ok)
				{
?>
	<P><? echo _T("La base n'est accessible. Veuillez revenir en arrière pour saisir à nouveau les paramètres de connexion."); ?></P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton2">
			<INPUT type="submit" value="<? echo _T("Retour"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
	</FORM>		
<?
				}
				else
				{
?>
	<P><? echo _T("La base existe et les paramètres de connexion sont corrects."); ?></P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
                <INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
	</FORM>
<?
				}
?>

	<BR>
	</DIV>
	<H1 class="footerinstall"><? echo _T("Etape 5 - Accès à la base"); ?></H1>
	

<?
			break;
			case "i6":
			case "u6":
?>


	<H1><? echo _T("Permissions sur la base"); ?></H1>
	<P>
		<? if ($step=="i6") echo _T("Pour fonctionner, Galette doit avoir un certain nombre de droits sur la base de données (CREATE, DROP, DELETE, UPDATE, SELECT et INSERT)"); ?>
		<? if ($step=="u6") echo _T("Pour être mis à jour, Galette doit avoir un certain nombre de droits sur la base de données (CREATE, DROP, DELETE, UPDATE, SELECT, INSERT et ALTER)"); ?>
	</P>
<?
				$result = "";
				
				// drop de table (si 'test' existe)
				$tables = $DB->MetaTables('TABLES');
				while (list($key,$value)=each($tables))
				{
					if ($value=="galette_test")
					{
						$droptest =1;
						$requete = "DROP table ".$value;
						$DB->Execute($requete);
						if($DB->ErrorNo())
						{
							$error = 1;
							$result = "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération DROP non autorisée")."<BR>";
						}
						else
							$result = "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération DROP autorisée")."<BR>";
					}
				}
					
				// création de table
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="CREATE table galette_test (testcol text)";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération CREATE non autorisée")."<BR>";
						$error = 1;
					}
					else
						$result .= "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération CREATE autorisée")."<BR>";
				}
				
				// création d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="INSERT INTO galette_test VALUES (".$DB->qstr("test").")";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération INSERT non autorisée")."<BR>";
						$error = 1;
					}
					else
						$result .= "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération INSERT autorisée")."<BR>";
				}				

				// mise à jour d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="UPDATE galette_test SET testcol=".$DB->qstr("test");
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération UPDATE non autorisée")."<BR>";
						$error = 1;
					}
					else
						$result .= "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération UPDATE autorisée")."<BR>";
				}				

				// selection d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="SELECT * FROM galette_test";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération SELECT non autorisée")."<BR>";
						$error = 1;
					}
					else
						$result .= "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération SELECT autorisée")."<BR>";
				}

				// alter pour la mise à jour
				if (!isset($error) && $step=="u6")
				{	
					// à adapter selon le type de base
					$requete="ALTER TABLE galette_test ADD testalter text";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération ALTER non autorisée")."<BR>";
						$error = 1;
					}
					else
						$result .= "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération ALTER autorisée")."<BR>";
				}

				// suppression d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="DELETE FROM galette_test";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération DELETE non autorisée")."<BR>";
						$error = 1;
					}
					else
						$result .= "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération DELETE autorisée")."<BR>";
				}				

				// suppression de table
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="DROP TABLE galette_test";
					$DB->Execute($requete);
					if (!isset($droptest))
					if($DB->ErrorNo())
					{
						$result .= "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération DROP non autorisée")."<BR>";
						$error = 1;
					}
					else
						$result .= "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Opération DROP autorisée")."<BR>";
				}				

				if ($result!="")
					echo "<TABLE><TR><TD>".$result."</TD></TR></TABLE>";

				if (isset($error))
				{		
?>
	<P>
		<? if ($step=="i6") echo _T("Galette ne dispose pas de droits suffisants sur la base de données pour poursuivre l'installation."); ?>
		<? if ($step=="u6") echo _T("Galette ne dispose pas de droits suffisants sur la base de données pour poursuivre la mise à jour."); ?>
	</P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton2">
			<INPUT type="submit" value="<? echo _T("Rééssayer"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
		<INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
	</FORM>
<?
				}
				else
				{
?>
	<P><? echo _T("Les droits d'accès à la base sont corrects."); ?></P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
		<INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
		<INPUT type="hidden" name="install_dbperms_ok" value="1">
	</FORM>
<?
				}
?>
	<BR>
	</DIV>
	<H1 class="footerinstall"><? echo _T("Etape 6 - Droits d'accès à la base"); ?></H1>
	
<?
			break;
		case "i7":
		case "u7":
?>

	<H1>
		<? if ($step=="i7") echo _T("Création de la base"); ?>
		<? if ($step=="u7") echo _T("Mise à jour de la base"); ?>
	</H1>
	<P>
		<? if ($step=="i7") echo _T("Compte rendu d'installation"); ?>
		<? if ($step=="u7") echo _T("Compte rendu de mise à jour"); ?>
	</P>
	<TABLE><TR><TD>
<?
			// BEGIN : copyright (2002) The phpBB Group (support@phpbb.com)	
			// Load in the sql parser
			include("sql_parse.php");
			
			$prefix = "";
			$table_prefix = $_POST["install_dbprefix"];
			if ($step=="u7")
			{
				$prefix="upgrade-to-";
				//echo $_POST["install_type"];

				$dh = opendir("sql");
       	                	$update_scripts = array();
				$first_file_found = false;
				while (($file = readdir($dh)) !== false)
				{
					if (ereg("upgrade-to-(.*)-".$_POST["install_dbtype"].".sql",$file,$ver))
					{
						if (substr($_POST["install_type"],8)<=$ver[1])
							$update_scripts[$ver[1]] = $file;
					}
				}
				ksort($update_scripts);
			}
			else
				$update_scripts["current"] = $_POST["install_dbtype"].".sql";

			ksort($update_scripts);
			$sql_query = "";
			while(list($key,$val)=each($update_scripts))
				$sql_query .= @fread(@fopen("sql/".$val, 'r'), @filesize("sql/".$val))."\n";
			
			$sql_query = preg_replace('/galette_/', $table_prefix, $sql_query);
			$sql_query = remove_remarks($sql_query);
			
			$sql_query = split_sql_file($sql_query, ";");
                                                                                                                                                  
			for ($i = 0; $i < sizeof($sql_query); $i++)
			{
				if (trim($sql_query[$i]) != '')
				{
					$DB->Execute($sql_query[$i]);
					@list($w1, $w2, $w3, $extra) = split(" ", $sql_query[$i], 4);
					if ($extra!="") $extra="...";
					if ($DB->ErrorNo())
					{
						echo "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> ".$w1." ".$w2." ".$w3." ".$extra."<BR>";
						if (trim($w1) != "DROP" && trim($w1) != "RENAME") $error = true;
					}
					else
						echo "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> ".$w1." ".$w2." ".$w3." ".$extra."<BR>";
				}
			}
			// END : copyright (2002) The phpBB Group (support@phpbb.com)

?>	
	</TD></TR></TABLE>
	<P><? echo _T("(Les erreurs sur les opérations DROP et RENAME peuvent être ignorées)"); ?></P>
	<?
			if (isset($error))
			{
?>
	<P>
		<? if ($step=="i7") echo _T("La base de données n'a pas pu être totalement créée, il s'agit peut-être d'un problème de droits."); ?>
		<? if ($step=="u7") echo _T("La base de données n'a pas pu être totalement mise à jour, il s'agit peut-être d'un problème de droits."); ?>
		<? if ($step=="u7") echo _T("Votre base est peut-être inutilisable, essayez de restaurer une ancienne version."); ?>
	</P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton2">
			<INPUT type="submit" value="<? echo _T("Rééssayer"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
		<INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
		<INPUT type="hidden" name="install_dbperms_ok" value="1">
	</FORM>
<?
			}
			else
			{
?>	
	<P>
		<? if ($step=="i7") echo _T("La base de données a été correctement créée."); ?>
		<? if ($step=="u7") echo _T("La base de données a été correctement mise à jour."); ?>
	</P>
	<FORM action="index.php" method="POST">
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
		<INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
		<INPUT type="hidden" name="install_dbperms_ok" value="1">
		<INPUT type="hidden" name="install_dbwrite_ok" value="1">
	</FORM>
	<?
			}
?>	
	<BR>
	</DIV>
	<H1 class="footerinstall">
		<? if ($step=="i7") echo _T("Etape 7 - Création de la base"); ?>
		<? if ($step=="u7") echo _T("Etape 7 - Mise à jour de la base"); ?>
	</H1>
	
<?
			break;
		case "i8":
		case "u8":
?>

	<H1><? echo _T("Paramètres administrateur"); ?></H1>
<?
				if ($error_detected!="")
					echo "<P><TABLE><TR><TD>".$error_detected."</TD></TR></TABLE></P>";
?>	
	<P><? echo _T("Veuillez choisir les paramètres du compte administrateur Galette"); ?></P>
	<FORM action="index.php" method="POST">
		<TABLE>
			<TR>
				<TD><? echo _T("Identifiant :"); ?></TD>
				<TD>
					<INPUT type="text" name="install_adminlogin" value="<? if(isset($_POST["install_adminlogin"])) echo $_POST["install_adminlogin"]; ?>">
				</TD>
			</TR>
			<TR>
				<TD><? echo _T("Mot de passe :"); ?></TD>
				<TD>
					<INPUT type="text" name="install_adminpass" value="<? if(isset($_POST["install_adminpass"])) echo $_POST["install_adminpass"]; ?>">
				</TD>
			</TR>
		</TABLE>
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
		<INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
		<INPUT type="hidden" name="install_dbperms_ok" value="1">
		<INPUT type="hidden" name="install_dbwrite_ok" value="1">
	</FORM>
	<BR>
	</DIV>
	<H1 class="footerinstall"><? echo _T("Etape 8 - Paramètres administrateur"); ?></H1>
	
<?
			break;
		case "i9";
		case "u9";
?>

	<H1><? echo _T("Sauvegarde des paramètres"); ?></H1>
	<P><TABLE><TR><TD>
<?
			// création du fichier de configuration
			
			if($fd = @fopen (WEB_ROOT ."includes/config.inc.php", "w"))
			{
				$data = "<?
define(\"TYPE_DB\", \"".$_POST["install_dbtype"]."\");
define(\"HOST_DB\", \"".$_POST["install_dbhost"]."\");
define(\"USER_DB\", \"".$_POST["install_dbuser"]."\");
define(\"PWD_DB\", \"".$_POST["install_dbpass"]."\");
define(\"NAME_DB\", \"".$_POST["install_dbname"]."\");
define(\"WEB_ROOT\", \"".WEB_ROOT."\");
define(\"PREFIX_DB\", \"".$_POST["install_dbprefix"]."\");
?>";
				fwrite($fd,$data);
				fclose($fd);	
				echo "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Fichier de configuration crée (includes/config.inc.php)")."<BR>";
			}
			else
			{
				echo "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Impossible de créer le fichier de configuration (includes/config.inc.php)")."<BR>";
				$error = true;
			}

			// sauvegarde des parametres
			$default = "DELETE FROM ".$_POST["install_dbprefix"]."preferences";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (1,'pref_nom','Galette')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (2,'pref_adresse','-')";
			$DB->Execute($default);		
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (2,'pref_adresse2','')";
			$DB->Execute($default);
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (3,'pref_cp','-')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (4,'pref_ville','-')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (4,'pref_pays','-')";
                        $DB->Execute($default);
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (5,'pref_lang',".$DB->qstr($_POST["install_lang"]).")";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (6,'pref_numrows','30')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (7,'pref_log','2')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (8,'pref_email_nom','Galette')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (9,'pref_email','mail@domain.com')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (10,'pref_etiq_marges','10')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (11,'pref_etiq_hspace','10')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (12,'pref_etiq_vspace','5')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (13,'pref_etiq_hsize','90')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (14,'pref_etiq_vsize','35')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (15,'pref_etiq_cols','2')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (16,'pref_etiq_rows','7')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (17,'pref_etiq_corps','12')";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (18,'pref_admin_login',".$DB->qstr($_POST["install_adminlogin"]).")";
			$DB->Execute($default);			
			$default = "INSERT INTO ".$_POST["install_dbprefix"]."preferences VALUES (19,'pref_admin_pass',".$DB->qstr($_POST["install_adminpass"]).")";
			
			// NB: il faudrait améliorer cette partie car la détection
			// d'erreur ne s'effectue que sur le dernier insert. Prévoir une boucle.
			
			$DB->Execute($default);
			if (!$DB->ErrorNo())
				echo "<IMG src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Paramètres sauvegardés dans la base de données")."<BR>";
			else
			{
				echo "<IMG src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._T("Les paramètres n'ont pas pu être sauvegardés dans la base de données")."<BR>";
				$error = true;
			}
?>
	</TD></TR></TABLE></P>
<?			
			if (!isset($error))
			{
?>
	<FORM action="index.php" method="POST">
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Etape suivante"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
		<INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
		<INPUT type="hidden" name="install_dbperms_ok" value="1">
		<INPUT type="hidden" name="install_dbwrite_ok" value="1">
		<INPUT type="hidden" name="install_adminlogin" value="<? echo $_POST["install_adminlogin"]; ?>">
		<INPUT type="hidden" name="install_adminpass" value="<? echo $_POST["install_adminpass"]; ?>">
		<INPUT type="hidden" name="install_prefs_ok" value="1">
	</FORM>
<?
			}
			else
			{
?>
	<FORM action="index.php" method="POST">
		<P><? echo _T("Les paramètres n'ont pas pu être sauvegardés."); ?></P>
		<P><? echo _T("Ceci peut provenir des droits sur le fichier includes/config.inc.php ou de l'impossibilité de faire un INSERT dans la base."); ?></P>
		<P id="submitbutton2">
			<INPUT type="submit" value="<? echo _T("Rééssayer"); ?>">
		</P>
		<INPUT type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
		<INPUT type="hidden" name="install_type" value="<? echo $_POST["install_type"]; ?>">
		<INPUT type="hidden" name="install_permsok" value="1">
		<INPUT type="hidden" name="install_dbtype" value="<? echo $_POST["install_dbtype"]; ?>">
		<INPUT type="hidden" name="install_dbhost" value="<? echo $_POST["install_dbhost"]; ?>">
		<INPUT type="hidden" name="install_dbuser" value="<? echo $_POST["install_dbuser"]; ?>">
		<INPUT type="hidden" name="install_dbpass" value="<? echo $_POST["install_dbpass"]; ?>">
		<INPUT type="hidden" name="install_dbname" value="<? echo $_POST["install_dbname"]; ?>">
		<INPUT type="hidden" name="install_dbprefix" value="<? echo $_POST["install_dbprefix"]; ?>">
		<INPUT type="hidden" name="install_dbconn_ok" value="1">
		<INPUT type="hidden" name="install_dbperms_ok" value="1">
		<INPUT type="hidden" name="install_dbwrite_ok" value="1">
		<INPUT type="hidden" name="install_adminlogin" value="<? echo $_POST["install_adminlogin"]; ?>">
		<INPUT type="hidden" name="install_adminpass" value="<? echo $_POST["install_adminpass"]; ?>">
	</FORM>
<?
			}
?>
	<BR>
	</DIV>
	<H1 class="footerinstall"><? echo _T("Etape 9 - Sauvegarde des paramètres"); ?></H1>

<?
			break;
		case "i10":
		case "u10":
?>

	<H1>
		<? if ($step=="i10") echo _T("Fin de l'installation"); ?>
		<? if ($step=="u10") echo _T("Fin de la mise à jour"); ?>
	</H1>
	<P>
		<? if ($step=="i10") echo _T("Galette a été installé avec succès !"); ?>
		<? if ($step=="u10") echo _T("Galette a été mis à jour avec succès !"); ?>
	</P>
	<P><? echo _T("Pour sécuriser le système, veuillez supprimer le dossier install"); ?></P>
	<FORM action="../index.php" method="GET">
		<P id="submitbutton3">
			<INPUT type="submit" value="<? echo _T("Page d'accueil"); ?>">
		</P>
	</FORM>
	<BR>
	</DIV>
	<H1 class="footerinstall">
		<? if ($step=="i10") echo _T("Etape 10 - Fin de l'installation"); ?>
		<? if ($step=="u10") echo _T("Etape 10 - Fin de la mise à jour"); ?>
	</H1>






<?
			break;
?>


<?
	}
?>	
	
</BODY>
</HTML>
