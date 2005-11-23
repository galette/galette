<?
        if (!isset($_POST["install_lang"])) $pref_lang="english";
        else $pref_lang=$_POST["install_lang"];
	define("WEB_ROOT", realpath(dirname($_SERVER["SCRIPT_FILENAME"])."/../")."/");
        include_once("../includes/i18n.inc.php"); 
	session_start();
	$step="1";
	$error_detected="";
	
	// traitement page 1 - language
	if (isset($_POST["install_lang"]))
	{
		$lang_inc = WEB_ROOT . "lang/lang_" . $_POST["install_lang"] . ".php";
		if ($lang_inc)
		{
			define("PREF_LANG",$_POST["install_lang"]);
			$step="2";
			include ($lang_inc);
		}
		else
	  		$error_detected .= "<li>Unknown language</li>";
	 }

	if ($error_detected=="" && isset($_POST["install_type"]))
	{
		if ($_POST["install_type"]=="install")
			$step="i3";
		elseif (substr($_POST["install_type"],0,7)=="upgrade")
			$step="u3";
		else
	  		$error_detected .= "<li>"._T("Installation mode unknown")."</li>";
	 }

	if ($error_detected=="" && isset($_POST["install_permsok"]))
	{
		if ($_POST["install_type"]=="install")
			$step="i4";
		elseif (substr($_POST["install_type"],0,7)=="upgrade")
			$step="u4";
		else
	  		$error_detected .= "<li>"._T("Installation mode unknown")."</li>";
	 }

	if ($error_detected=="" && isset($_POST["install_dbtype"])  
		&& isset($_POST["install_dbhost"]) 
		&& isset($_POST["install_dbuser"]) 
		&& isset($_POST["install_dbpass"]) 
		&& isset($_POST["install_dbname"])
		&& isset($_POST["install_dbprefix"]))
	{
		if ($_POST["install_dbtype"]!="mysql" && $_POST["install_dbtype"]!="pgsql")
	  		$error_detected .= "<img src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Database type unknown")."<br />";
		if ($_POST["install_dbuser"]=="")
	  		$error_detected .= "<img src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("No user name")."<br />";
		if ($_POST["install_dbpass"]=="")
	  		$error_detected .= "<img src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("No password")."<br />";
		if ($_POST["install_dbname"]=="")
	  		$error_detected .= "<img src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("No database name")."<br />";
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
				  		$error_detected .= "<img src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("No user name")."<br />";
					if ($_POST["install_adminpass"]=="")
				  		$error_detected .= "<img src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("No password")."<br />";
          if ( ! $_POST["install_passwdverified"] && strcmp($_POST["install_adminpass"],$_POST["install_adminpass_verif"]) ) {
            $error_detected .= "<img src=\"no.gif\" width=\"6\" height=\"10\" border=\"0\" alt=\"\"> "._T("Passwords mismatch")."<br />";
          }
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
<html> 
<head> 
	<title>Galette Installation</title> 
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"> 
	<link rel="stylesheet" type="text/css" href="../templates/default/galette.css" > 
</head> 
<h1 class="titreinstall">Galette installation</h1>
<div id="installpage" align="center">
<br />
	
<?
	switch ($step)
	{
		case "1":
?>

	<h1>Welcome to the Galette Install!</h1>
	<p>Please select your administration language</p>
	<form action="index.php" method="POST">
		<select name="install_lang">
<?
			$path = "../lang";
			$dir_handle = @opendir($path);
			while ($file = readdir($dir_handle))
			{
				if (substr($file,0,5)=="lang_" && substr($file,-4)==".php")
				{
		        $file = substr(substr($file,5),0,-4);
?>
		<option value="<? echo $file; ?>"><? echo ucfirst($file); ?></option>
<?
				}
			}
			closedir($dir_handle);
?>
		</select>
		<p id="submitbutton3">
			<input type="submit" value="Next Page">
		</p>
	</form>
	<br />
	</div>
	<h1 class="footerinstall">Step 1 - Language</h1>

<?
			break;
		case "2":
?>

	<h1><? echo _T("Installation mode"); ?></h1>
	<p><? echo _T("Select installation mode to launch"); ?></p>
	<form action="index.php" method="POST">
		<p>
			<input type="radio" name="install_type" value="install" selected="selected"> <? echo _T("New installation:"); ?><br />
		 	<? echo _T("You're installing Galette for the first time, or you wish to erase an older version of Galette without keeping your data"); ?>
		</p>
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
		<p>
			<input type="radio" name="install_type" value="upgrade-<? echo $val; ?>"> <? echo _T("Update:"); ?><br />
<?
				if ($last!=number_format($val-0.01,2))
					echo _T("Your current Galette version is comprised between")." ".$last." "._T("and")." ".number_format($val-0.01,2)."<br />";
				else
					echo _T("Your current Galette version is")." ".number_format($val-0.01,2)."<br />";
				$last = $val;
				echo _T("Warning: Don't forget to backup your current database.");
?>
		</p>
<?
			}
?>
		<p id="submitbutton3">
			<input type="submit" value="<? echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_POST["install_lang"]; ?>">
	</form>
	<br />
	</div>
	<h1 class="footerinstall"><? echo _T("Step 2 - Installation mode"); ?></h1>

<?
			break;
?>

<?
			break;
		case "i3":
		case "u3":
?>

	<h1><? echo _T("Files permissions"); ?></h1>
<?
			$perms_ok = true;
			if (!$perms_ok)
			{
?>
	<p>
		<? if ($step=="i3") echo _T("For a correct functioning, Galette needs the Write permission on these files."); ?>
		<? if ($step=="u3") echo _T("In order to be updated, Galette needs the Write permission on these files."); ?>
	</p>
	<p>
		<? echo _T("Under UNIX/Linux, you can give the permissions using those commands"); ?><br />
		<code>chown <i><? echo _T("apache_user"); ?></i> <i><? echo _T("file_name"); ?></i><br />
		chmod 600 <i><? echo _T("file_name"); ?></i> <? echo _T("(for a file)"); ?><br />
		chmod 700 <i><? echo _T("direcory_name"); ?></i> <? echo _T("(for a directory)"); ?></code>
	<p>
	<p>
		<? echo _T("Under Windows, check these files are not in Read-Only mode in their property panel."); ?>
	<p>
	<form action="index.php" method="POST">
		<p id="submitbutton2">
			<input type="submit" value="<? echo _T("Retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
	</form>		
<?
			}
			else
			{
?>
	<p><? echo _t("files permissions are ok!"); ?></p>
	<form action="index.php" method="post">
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
	</form>
<?
			}
?>
	<br />
	</div>
	<h1 class="footerinstall"><? echo _t("step 3 - permissions"); ?></h1>

<?
			break;
			case "i4":
			case "u4";
?>

	<h1><? echo _t("database"); ?></h1>
	<p>
<?
				if ($error_detected!="")
					echo "<table><tr><td>".$error_detected."</td></tr></table><br />";
?>	
		<? if ($step=="i4") echo _t("if it hadn't been made, create a database and a user for galette."); ?><br />
		<? if ($step=="u4") echo _t("enter connection data for the existing database."); ?><br />
		<? echo _t("the needed permissions are create, drop, delete, update, select and insert."); ?></p>
	<form action="index.php" method="post">
		<table>
			<tr>
				<td><? echo _t("database type:"); ?></td>
				<td>
					<select name="install_dbtype">
						<option value="mysql">mysql</option>
						<option value="pgsql">postgresql</option>
					</select>
				</td>
			</tr>
			<tr>
				<td><? echo _t("host:"); ?></td>
				<td>
					<input type="text" name="install_dbhost" value="<? if(isset($_post["install_dbhost"])) echo $_post["install_dbhost"]; ?>">
				</td>
			</tr>
			<tr>
				<td><? echo _t("user:"); ?></td>
				<td>
					<input type="text" name="install_dbuser" value="<? if(isset($_post["install_dbuser"])) echo $_post["install_dbuser"]; ?>">
				</td>
			</tr>
			<tr>
				<td><? echo _t("password:"); ?></td>
				<td>
					<input type="password" name="install_dbpass" value="<? if(isset($_post["install_dbpass"])) echo $_post["install_dbpass"]; ?>">
				</td>
			</tr>
			<tr>
				<td><? echo _t("database:"); ?></td>
				<td>
					<input type="text" name="install_dbname" value="<? if(isset($_post["install_dbname"])) echo $_post["install_dbname"]; ?>">
				</td>
			</tr>					
                        <tr>
                                <td>
					<? echo _t("table prefix:"); ?>
				</td>
                                <td>
                                        <input type="text" name="install_dbprefix" value="<? if(isset($_post["install_dbprefix"])) echo $_post["install_dbprefix"]; else echo "galette_" ?>">
                                </td>
			</tr>
			<?
				if (substr($_post["install_type"],0,8)=="upgrade-")
				{
			?>
			<tr>
				<td colspan="2" style="color: #ff0000; font-weight: bold;">
					<? echo _t("(indicate the current prefix of your galette tables)"); ?>
				</td>
			</tr>
			<?
				}
			?>
		</table>
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
	</form>
	<br />
	</div>
	<h1 class="footerinstall"><? echo _t("step 4 - database"); ?></h1>
	
<?
			break;
			case "i5":
			case "u5":
?>

	<h1><? echo _t("check of the database"); ?></h1>
	<p><? echo _t("check the parameters and the existence of the database"); ?></p>
<?
				include(web_root."/includes/adodb/adodb.inc.php");
				$db = adonewconnection($_post["install_dbtype"]);
				$db->debug = false;
				$permsdb_ok = true;
				if(!@$db->connect($_post["install_dbhost"], $_post["install_dbuser"], $_post["install_dbpass"], $_post["install_dbname"]))
				{
					$permsdb_ok = false;
					echo "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("unable to connect to the database")."<br />";
				}
				else
				{
					echo "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("connection to database is ok")."<br />";
					$db->close();
				}

				if (!$permsdb_ok)
				{
?>
	<p><? echo _t("database can't be reached. please go back to enter the connection parameters again."); ?></p>
	<form action="index.php" method="post">
		<p id="submitbutton2">
			<input type="submit" value="<? echo _t("go back"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
	</form>		
<?
				}
				else
				{
?>
	<p><? echo _t("database exists and connection parameters are ok."); ?></p>
	<form action="index.php" method="post">
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
                <input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
	</form>
<?
				}
?>

	<br />
	</div>
	<h1 class="footerinstall"><? echo _t("step 5 - access to the database"); ?></h1>
	

<?
			break;
			case "i6":
			case "u6":
?>


	<h1><? echo _t("permissions on the base"); ?></h1>
	<p>
		<? if ($step=="i6") echo _t("to run, galette needs a number of rights on the database (create, drop, delete, update, select and insert)"); ?>
		<? if ($step=="u6") echo _t("in order to be updated, galette needs a number of rights on the database (create, drop, delete, update, select and insert)"); ?>
	</p>
<?
				$result = "";
				
				// drop de table (si 'test' existe)
				$tables = $db->metatables('tables');
				while (list($key,$value)=each($tables))
				{
					if ($value=="galette_test")
					{
						$droptest =1;
						$requete = "drop table ".$value;
						$db->execute($requete);
						if($db->errorno())
						{
							$error = 1;
							$result = "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("drop operation not allowed")."<br />";
						}
						else
							$result = "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("drop operation allowed")."<br />";
					}
				}
					
				// création de table
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="create table galette_test (testcol text)";
					$db->execute($requete);
					if($db->errorno())
					{
						$result .= "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("create operation not allowed")."<br />";
						$error = 1;
					}
					else
						$result .= "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("create operation allowed")."<br />";
				}
				
				// création d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="insert into galette_test values (".$db->qstr("test", get_magic_quotes_gpc()).")";
					$db->execute($requete);
					if($db->errorno())
					{
						$result .= "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("insert operation not allowed")."<br />";
						$error = 1;
					}
					else
						$result .= "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("insert operation allowed")."<br />";
				}				

				// mise à jour d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="update galette_test set testcol=".$db->qstr("test", get_magic_quotes_gpc());
					$db->execute($requete);
					if($db->errorno())
					{
						$result .= "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("update operation not allowed")."<br />";
						$error = 1;
					}
					else
						$result .= "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("update operation allowed")."<br />";
				}				

				// selection d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="select * from galette_test";
					$db->execute($requete);
					if($db->errorno())
					{
						$result .= "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("select operation not allowed")."<br />";
						$error = 1;
					}
					else
						$result .= "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("select operation allowed")."<br />";
				}

				// alter pour la mise à jour
				if (!isset($error) && $step=="u6")
				{	
					// à adapter selon le type de base
					$requete="alter table galette_test add testalter text";
					$db->execute($requete);
					if($db->errorno())
					{
						$result .= "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("alter operation not allowed")."<br />";
						$error = 1;
					}
					else
						$result .= "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("alter operation allowed")."<br />";
				}

				// suppression d'enregistrement
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="delete from galette_test";
					$db->execute($requete);
					if($db->errorno())
					{
						$result .= "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("delete operation not allowed")."<br />";
						$error = 1;
					}
					else
						$result .= "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("delete operation allowed")."<br />";
				}				

				// suppression de table
				if (!isset($error))
				{	
					// à adapter selon le type de base
					$requete="drop table galette_test";
					$db->execute($requete);
					if (!isset($droptest))
					if($db->errorno())
					{
						$result .= "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("drop operation not allowed")."<br />";
						$error = 1;
					}
					else
						$result .= "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("drop operation allowed")."<br />";
				}				

				if ($result!="")
					echo "<table><tr><td>".$result."</td></tr></table>";

				if (isset($error))
				{		
?>
	<p>
		<? if ($step=="i6") echo _t("galette hasn't got enough permissions on the database to continue the installation."); ?>
		<? if ($step=="u6") echo _t("galette hasn't got enough permissions on the database to continue the update."); ?>
	</p>
	<form action="index.php" method="post">
		<p id="submitbutton2">
			<input type="submit" value="<? echo _t("retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
	</form>
<?
				}
				else
				{
?>
	<p><? echo _t("permissions to database are ok."); ?></p>
	<form action="index.php" method="post">
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
	</form>
<?
				}
?>
	<br />
	</div>
	<h1 class="footerinstall"><? echo _t("step 6 - access permissions to database"); ?></h1>
	
<?
			break;
		case "i7":
		case "u7":
?>

	<h1>
		<? if ($step=="i7") echo _t("creation of the database"); ?>
		<? if ($step=="u7") echo _t("update of the database"); ?>
	</h1>
	<p>
		<? if ($step=="i7") echo _t("installation report"); ?>
		<? if ($step=="u7") echo _t("update report"); ?>
	</p>
	<table><tr><td>
<?
			// begin : copyright (2002) the phpbb group (support@phpbb.com)	
			// load in the sql parser
			include("sql_parse.php");
			
			$prefix = "";
			$table_prefix = $_post["install_dbprefix"];
			if ($step=="u7")
			{
				$prefix="upgrade-to-";
				//echo $_post["install_type"];

				$dh = opendir("sql");
       	                	$update_scripts = array();
				$first_file_found = false;
				while (($file = readdir($dh)) !== false)
				{
					if (ereg("upgrade-to-(.*)-".$_post["install_dbtype"].".sql",$file,$ver))
					{
						if (substr($_post["install_type"],8)<=$ver[1])
							$update_scripts[$ver[1]] = $file;
					}
				}
				ksort($update_scripts);
			}
			else
				$update_scripts["current"] = $_post["install_dbtype"].".sql";

			ksort($update_scripts);
			$sql_query = "";
			while(list($key,$val)=each($update_scripts))
				$sql_query .= @fread(@fopen("sql/".$val, 'r'), @filesize("sql/".$val))."\n";
			
			$sql_query = preg_replace('/galette_/', $table_prefix, $sql_query);
			$sql_query = remove_remarks($sql_query);
			
			$sql_query = split_sql_file($sql_query, ";");
                                                                                                                                                  
			for ($i = 0; $i < sizeof($sql_query); $i++)
			{
				$query = trim($sql_query[$i]);
				if ($query != '' && $query[0] != '-')
				{
					$db->execute($query);
					@list($w1, $w2, $w3, $extra) = split(" ", $query, 4);
					if ($extra!="") $extra="...";
					if ($db->errorno())
					{
						echo "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> ".$w1." ".$w2." ".$w3." ".$extra."<br />";
						if (trim($w1) != "drop" && trim($w1) != "rename") $error = true;
					}
					else
						echo "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> ".$w1." ".$w2." ".$w3." ".$extra."<br />";
				}
			}
			// end : copyright (2002) the phpbb group (support@phpbb.com)

			// begin: fix overlapping fees
			$cotis = array();
			$query = "select id_cotis, date_enreg, date_debut_cotis, date_fin_cotis
				    from ".$table_prefix."cotisations, ".$table_prefix."types_cotisation
				   where ".$table_prefix."cotisations.id_type_cotis = ".$table_prefix."types_cotisation.id_type_cotis
					   and ".$table_prefix."types_cotisation.cotis_extension = '1'
				   order by date_enreg;";
			$result = $db->execute($query);
			if (!$result)
				print $query.": ".$db->errormsg();
			else {
				while (!$result->eof) {
					$c = $result->fetchrow();
					$newc = array('id_cotis' => $c['id_cotis']);
					list($by, $bm, $bd) = split("-", $c['date_debut_cotis']);
					list($ey, $em, $ed) = split("-", $c['date_fin_cotis']);
					$newc['start_date'] = mktime(0, 0, 0, $bm, $bd, $by);
					$newc['end_date'] = mktime(0, 0, 0, $em, $ed, $ey);
					if ($bm > $em) {
						$em += 12;
						$ey--;
					}
					$newc['duration'] = ($ey -$by)*12 + $em - $bm;
					$cotis[] = $newc;
				}
				$result->close();
			}
			if (count($cotis) > 0) {
				unset($cprev);
				foreach ($cotis as $c) {
					if (isset($cprev) && $c['start_date'] < $cprev['end_date']) {
						$c['start_date'] = $cprev['end_date'];
						$start_date = $db->dbdate($c['start_date']);
						$new_start_date = localtime($c['start_date'], 1);
						$c['end_date'] = mktime(0, 0, 0, $new_start_date['tm_mon'] + $c['duration'] + 1, $new_start_date['tm_mday'], $new_start_date['tm_year']);
						$end_date = $db->dbdate($c['end_date']);
						$query = "update ".$table_prefix."cotisations 
							     set date_debut_cotis = ".$start_date.", 
								 date_fin_cotis = ".$end_date."
							     where id_cotis = ".$c['id_cotis'];
						$result = $db->execute($query);
						if (!$result)
							print $query.": ".$db->errormsg();
						else
							$result->close();
					}
					$cprev = $c;
				}
			}
			// end: fix overlapping fees

?>	
	</td></tr></table>
	<p><? echo _t("(errors on drop and rename operations can be ignored)"); ?></p>
	<?
			if (isset($error))
			{
?>
	<p>
		<? if ($step=="i7") echo _t("the database isn't totally created, it's maybe a permission problem."); ?>
		<? if ($step=="u7") echo _t("the database isn't totally updated, it's maybe a permission problem."); ?>
		<? if ($step=="u7") echo _t("your database is maybe not usable, try to restore the older version."); ?>
	</p>
	<form action="index.php" method="post">
		<p id="submitbutton2">
			<input type="submit" value="<? echo _t("retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
	</form>
<?
			}
			else
			{
?>	
	<p>
		<? if ($step=="i7") echo _t("the database has been correctly created."); ?>
		<? if ($step=="u7") echo _t("the database has been correctly updated."); ?>
	</p>
	<form action="index.php" method="post">
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
	</form>
	<?
			}
?>	
	<br />
	</div>
	<h1 class="footerinstall">
		<? if ($step=="i7") echo _t("step 7 - database creation"); ?>
		<? if ($step=="u7") echo _t("step 7 - database update"); ?>
	</h1>
	
<?
			break;
		case "i8":
		case "u8":
?>

	<h1><? echo _t("admin settings"); ?></h1>
<?
				if ($error_detected!="")
					echo "<p><table><tr><td>".$error_detected."</td></tr></table></p>";
?>	
	<p><? echo _t("please chose the parameters of the admin account on galette"); ?></p>
	<form action="index.php" method="post">
		<table>
			<tr>
				<td><? echo _t("username:"); ?></td>
				<td>
					<input type="text" name="install_adminlogin" value="<? if(isset($_post["install_adminlogin"])) echo $_post["install_adminlogin"]; ?>">
				</td>
			</tr>
			<tr>
				<td><? echo _t("password:"); ?></td>
				<td>
          <!--
					<input type="text" name="install_adminpass" value="<? //if(isset($_post["install_adminpass"])) echo $_post["install_adminpass"]; ?>">
          //-->
					<input type="password" name="install_adminpass" value="">
        </td>
      </tr>
      <tr>
				<td><? echo _t("retype password:"); ?></td>
        <td>
					<input type="password" name="install_adminpass_verif" value="">
				</td>
			</tr>
		</table>
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
	</form>
	<br />
	</div>
	<h1 class="footerinstall"><? echo _t("step 8 - admin parameters"); ?></h1>
	
<?
			break;
		case "i9";
		case "u9";
?>

	<h1><? echo _t("save the parameters"); ?></h1>
	<p><table><tr><td>
<?
			// création du fichier de configuration
			
			if($fd = @fopen (web_root ."includes/config.inc.php", "w"))
			{
				$data = "<?
define(\"type_db\", \"".$_post["install_dbtype"]."\");
define(\"host_db\", \"".$_post["install_dbhost"]."\");
define(\"user_db\", \"".$_post["install_dbuser"]."\");
define(\"pwd_db\", \"".$_post["install_dbpass"]."\");
define(\"name_db\", \"".$_post["install_dbname"]."\");
define(\"web_root\", \"".web_root."\");
define(\"prefix_db\", \"".$_post["install_dbprefix"]."\");
?>";
				fwrite($fd,$data);
				fclose($fd);	
				echo "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("configuration file created (includes/config.inc.php)")."<br />";
			}
			else
			{
				echo "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("unable to create configuration file (includes/config.inc.php)")."<br />";
				$error = true;
			}

			// sauvegarde des parametres
			$default = "delete from ".$_post["install_dbprefix"]."preferences";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (1,'pref_nom','galette')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (2,'pref_adresse','-')";
			$db->execute($default);		
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (3,'pref_adresse2','')";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (4,'pref_cp','-')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (5,'pref_ville','-')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (6,'pref_pays','-')";
                        $db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (7,'pref_lang',".$db->qstr($_post["install_lang"], get_magic_quotes_gpc()).")";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (8,'pref_numrows','30')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (9,'pref_log','2')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (10,'pref_email_nom','galette')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (11,'pref_email','mail@domain.com')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (12,'pref_etiq_marges','10')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (13,'pref_etiq_hspace','10')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (14,'pref_etiq_vspace','5')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (15,'pref_etiq_hsize','90')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (16,'pref_etiq_vsize','35')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (17,'pref_etiq_cols','2')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (18,'pref_etiq_rows','7')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (19,'pref_etiq_corps','12')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (20,'pref_admin_login',".$db->qstr($_post["install_adminlogin"], get_magic_quotes_gpc()).")";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (21,'pref_admin_pass',".$db->qstr($_post["install_adminpass"], get_magic_quotes_gpc()).")";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (22,'pref_mail_method','0')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (23,'pref_mail_smtp','')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (24,'pref_membership_ext','12')";
			$db->execute($default);			
			$default = "insert into ".$_post["install_dbprefix"]."preferences values (25,'pref_beg_membership','')";
			$db->execute($default);
			
			if ($step=='i9')
			{
			
			// contribution types
			$default = "insert into ".$_post["install_dbprefix"]."types_cotisation values (1, 'annual fee', '1')";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."types_cotisation values (2, 'reduced annual fee', '1')";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."types_cotisation values (3, 'company fee', '1')";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."types_cotisation values (4, 'donation in kind', null)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."types_cotisation values (5, 'donation in money', null)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."types_cotisation values (6, 'partnership', null)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."types_cotisation values (7, 'annual fee (to be paid)', '1')";
			$db->execute($default);

			// member types
			$default = "delete from ".$_post["install_dbprefix"]."statuts";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (1,'president',0)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (2,'treasurer',10)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (3,'secretary',20)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (4,'active member',30)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (5,'benefactor member',40)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (6,'founder member',50)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (7,'old-timer',60)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (8,'society',70)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (9,'non-member',80)";
			$db->execute($default);
			$default = "insert into ".$_post["install_dbprefix"]."statuts values (10,'vice-president',5)";
			$db->execute($default);

			}
			else
			{
				// todo: reimport member and contribution types from previous installation
			}
			
			// nb: il faudrait améliorer cette partie car la détection
			// d'erreur ne s'effectue que sur le dernier insert. prévoir une boucle.
			
			if (!$db->errorno())
				echo "<img src=\"yes.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("parameters saved into the database")."<br />";
			else
			{
				echo "<img src=\"no.gif\" width=\"6\" height=\"12\" border=\"0\" alt=\"\"> "._t("parameters couldn't be save into the database")."<br />";
				$error = true;
			}
?>
	</td></tr></table></p>
<?			
			if (!isset($error))
			{
?>
	<form action="index.php" method="post">
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
		<input type="hidden" name="install_adminlogin" value="<? echo $_post["install_adminlogin"]; ?>">
		<input type="hidden" name="install_adminpass" value="<? echo $_post["install_adminpass"]; ?>">
		<input type="hidden" name="install_passwdverified" value="1">
		<input type="hidden" name="install_prefs_ok" value="1">
	</form>
<?
			}
			else
			{
?>
	<form action="index.php" method="post">
		<p><? echo _t("parameters couldn't be saved."); ?></p>
		<p><? echo _t("this can come from the permissions on the file includes/config.inc.php or the impossibility to make an insert into the database."); ?></p>
		<p id="submitbutton2">
			<input type="submit" value="<? echo _t("retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<? echo $_post["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<? echo $_post["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<? echo $_post["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<? echo $_post["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<? echo $_post["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<? echo $_post["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<? echo $_post["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<? echo $_post["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
		<input type="hidden" name="install_adminlogin" value="<? echo $_post["install_adminlogin"]; ?>">
		<input type="hidden" name="install_adminpass" value="<? echo $_post["install_adminpass"]; ?>">
		<input type="hidden" name="install_passwdverified" value="1">
	</form>
<?
			}
?>
	<br />
	</div>
	<h1 class="footerinstall"><? echo _t("step 9 - saving of the parameters"); ?></h1>

<?
			break;
		case "i10":
		case "u10":
?>

	<h1>
		<? if ($step=="i10") echo _t("installation complete !"); ?>
		<? if ($step=="u10") echo _t("update complete !"); ?>
	</h1>
	<p>
		<? if ($step=="i10") echo _t("galette has been successfully installed!"); ?>
		<? if ($step=="u10") echo _t("galette has been successfully updated!"); ?>
	</p>
	<p><? echo _t("for securing the system, please delete the install directory"); ?></p>
	<form action="../index.php" method="get">
		<p id="submitbutton3">
			<input type="submit" value="<? echo _t("homepage"); ?>">
		</p>
	</form>
	<br />
	</div>
	<h1 class="footerinstall">
		<? if ($step=="i10") echo _t("step 10 - end of the installation"); ?>
		<? if ($step=="u10") echo _t("step 10 - end of the update"); ?>
	</h1>






<?
			break;
?>


<?
	}
?>	
	
</body>
</html>
