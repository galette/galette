<?php
	if (!isset($_POST["install_lang"])) $pref_lang="english";
		else $pref_lang=$_POST["install_lang"];
	define("WEB_ROOT", realpath(dirname(__FILE__)."/../")."/");
	include_once("../includes/i18n.inc.php"); 

// test if galette is already installed and redirect to index page if so
$configfile = dirname( __FILE__).'/../includes/config.inc.php';
$installed = file_exists($configfile);
if ($installed) {
	header("location: ../index.php");
}

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
	  		$error_detected .= "<li class=\"install-bad\">"._T("Database type unknown")."</li>";
		if ($_POST["install_dbuser"]=="")
	  		$error_detected .= "<li class=\"install-bad\">"._T("No user name")."</li>";
		if ($_POST["install_dbpass"]=="")
	  		$error_detected .= "<li class=\"install-bad\">"._T("No password")."</li>";
		if ($_POST["install_dbname"]=="")
	  		$error_detected .= "<li class=\"install-bad\">"._T("No database name")."</li>";
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
						$error_detected .= "<li class=\"install-bad\">"._T("No user name")."</li>";
					if ( strpos($_POST["install_adminlogin"],'@') != FALSE )
						$error_detected[] = "<li class=\"install-bad\">"._T("The username cannot contain the @ character")."</li>";
					if ($_POST["install_adminpass"]=="")
						$error_detected .= "<li class=\"install-bad\">"._T("No password")."</li>";
          if ( ! isset($_POST["install_passwdverified"]) && strcmp($_POST["install_adminpass"],$_POST["install_adminpass_verif"]) ) {
            $error_detected .= "<li class=\"install-bad\">"._T("Passwords mismatch")."</li>";
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
	header('Content-Type: text/html; charset=iso-8859-15');	
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"> 
<html> 
<head> 
	<title>Galette Installation</title> 
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-15"> 
	<link rel="stylesheet" type="text/css" href="../templates/default/galette.css" > 
</head> 
<h1 class="titreinstall">Galette installation</h1>
<div id="installpage" align="center">
<br />
	
<?php
	switch ($step)
	{
		case "1":
?>

	<h1>Welcome to the Galette Install!</h1>
	<p>Please select your administration language</p>
	<form action="index.php" method="POST">
		<select name="install_lang">
<?php
			$path = "../lang";
			$dir_handle = @opendir($path);
			while ($file = readdir($dir_handle))
			{
				if (substr($file,0,5)=="lang_" && substr($file,-4)==".php")
				{
		        $file = substr(substr($file,5),0,-4);
?>
		<option value="<?php echo $file; ?>"><?php echo ucfirst($file); ?></option>
<?php
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

<?php
			break;
		case "2":
?>

	<h1><?php echo _T("Installation mode"); ?></h1>
	<p><?php echo _T("Select installation mode to launch"); ?></p>
	<form action="index.php" method="POST">
		<p>
			<input type="radio" name="install_type" value="install" checked="checked" id="install"> <label for="install"><?php echo _T("New installation:"); ?></label><br />
		 	<?php echo _T("You're installing Galette for the first time, or you wish to erase an older version of Galette without keeping your data"); ?>
		</p>
<?php
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
			<input type="radio" name="install_type" value="upgrade-<?php echo $val; ?>" id="upgrade-<?php echo $val; ?>"> <label for="upgrade-<?php echo $val; ?>"><?php echo _T("Update:"); ?><br />
<?php
				if ($last!=number_format($val-0.01,2))
					echo _T("Your current Galette version is comprised between")." ".$last." "._T("and")." ".number_format($val-0.01,2)."</label><br />";
				else
					echo _T("Your current Galette version is")." ".number_format($val-0.01,2)."</label><br />";
				$last = $val;
				echo _T("Warning: Don't forget to backup your current database.");
?>
		</p>
<?php
			}
?>
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
	</form>
	<br />
	</div>
	<h1 class="footerinstall"><?php echo _T("Step 2 - Installation mode"); ?></h1>

<?php
			break;
?>

<?php
			break;
		case "i3":
		case "u3":
?>

	<h1><?php echo _T("Files permissions"); ?></h1>
<ul>
<?php
			$perms_ok = true;
			$files_need_rw = array ('/templates_c',
						'/photos',
						'/cache',
						'/tempimages',
						'/includes');
			foreach ($files_need_rw as $file)
			{
				if (!is_writable(dirname(__FILE__).'/..'.$file))
				{
					$perms_ok = false;
					echo "<li class=\"install-bad\">".$file."</li>";
				}
				else
					echo "<li class=\"install-ok\">".$file."</li>";
			}
			echo "</ul>";
			if (!$perms_ok)
			{
?>
	<p>
		<?php if ($step=="i3") echo _T("For a correct functioning, Galette needs the Write permission on these files."); ?>
		<?php if ($step=="u3") echo _T("In order to be updated, Galette needs the Write permission on these files."); ?>
	</p>
	<p>
		<?php echo _T("Under UNIX/Linux, you can give the permissions using those commands"); ?><br />
		<code>chown <i><?php echo _T("apache_user"); ?></i> <i><?php echo _T("file_name"); ?></i><br />
		chmod 600 <i><?php echo _T("file_name"); ?></i> <?php echo _T("(for a file)"); ?><br />
		chmod 700 <i><?php echo _T("direcory_name"); ?></i> <?php echo _T("(for a directory)"); ?></code>
	<p>
	<p>
		<?php echo _T("Under Windows, check these files are not in Read-Only mode in their property panel."); ?>
	<p>
	<form action="index.php" method="POST">
		<p id="submitbutton2">
			<input type="submit" value="<?php echo _T("Retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
	</form>		
<?php
		}
			else
			{
?>
	<p><?php echo _T("Files permissions are OK!"); ?></p>
	<form action="index.php" method="POST">
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
	</form>
<?php
			}
?>
	<br />
	</div>
	<h1 class="footerinstall"><?php echo _T("Step 3 - Permissions"); ?></h1>

<?php
			break;
			case "i4":
			case "u4";
?>

	<h1><?php echo _T("Database"); ?></h1>
	<p>
<?php
				if ($error_detected!="")
					echo "<table><tr><td><ul>".$error_detected."</ul></td></tr></table><br />";
?>	
		<?php if ($step=="i4") echo _T("If it hadn't been made, create a database and a user for Galette."); ?><br />
		<?php if ($step=="u4") echo _T("Enter connection data for the existing database."); ?><br />
		<?php echo _T("The needed permissions are CREATE, DROP, DELETE, UPDATE, SELECT and INSERT."); ?></p>
	<form action="index.php" method="POST">
		<table>
			<tr>
				<td><label for="install_dbtype"><?php echo _T("Database type:"); ?></label></td>
				<td>
					<select name="install_dbtype" id="install_dbtype">
						<option value="mysql">Mysql</option>
						<option value="pgsql">Postgresql</option>
					</select>
				</td>
			</tr>
			<tr>
				<td><label for="install_dbhost"><?php echo _T("Host:"); ?></label></td>
				<td>
					<input type="text" name="install_dbhost" id="install_dbhost" value="<?php if(isset($_POST["install_dbhost"])) echo $_POST["install_dbhost"]; ?>">
				</td>
			</tr>
			<tr>
				<td><label for="install_dbuser"><?php echo _T("User:"); ?></label></td>
				<td>
					<input type="text" name="install_dbuser" id="install_dbuser" value="<?php if(isset($_POST["install_dbuser"])) echo $_POST["install_dbuser"]; ?>">
				</td>
			</tr>
			<tr>
				<td><label for="install_dbpass"><?php echo _T("Password:"); ?></label></td>
				<td>
					<input type="password" name="install_dbpass" id="install_dbpass" value="<?php if(isset($_POST["install_dbpass"])) echo $_POST["install_dbpass"]; ?>">
				</td>
			</tr>
			<tr>
				<td><label for="install_dbname"><?php echo _T("Database:"); ?></label></td>
				<td>
					<input type="text" name="install_dbname" id="install_dbname" value="<?php if(isset($_POST["install_dbname"])) echo $_POST["install_dbname"]; ?>">
				</td>
			</tr>
                        <tr>
                                <td>
					<label for="install_dbprefix"><?php echo _T("Table prefix:"); ?></label>
				</td>
                                <td>
                                        <input type="text" name="install_dbprefix" id="install_dbprefix" value="<?php if(isset($_POST["install_dbprefix"])) echo $_POST["install_dbprefix"]; else echo "galette_" ?>">
                                </td>
			</tr>
			<?php
				if (substr($_POST["install_type"],0,8)=="upgrade-")
				{
			?>
			<tr>
				<td colspan="2" style="color: #ff0000; font-weight: bold;">
					<?php echo _T("(Indicate the CURRENT prefix of your Galette tables)"); ?>
				</td>
			</tr>
			<?php
				}
			?>
		</table>
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
	</form>
	<br />
	</div>
	<h1 class="footerinstall"><?php echo _T("Step 4 - Database"); ?></h1>

<?php
			break;
			case "i5":
			case "u5":
?>

	<h1><?php echo _T("Check of the database"); ?></h1>
	<p><?php echo _T("Check the parameters and the existence of the database"); ?></p>
<ul>
<?php
				include("../includes/adodb/adodb.inc.php");
				$DB = adonewconnection($_POST["install_dbtype"]);
				$DB->debug = false;
				$permsdb_ok = true;
				if(!@$DB->Connect($_POST["install_dbhost"], $_POST["install_dbuser"], $_POST["install_dbpass"], $_POST["install_dbname"]))
				{
					$permsdb_ok = false;
					echo "<li class=\"install-bad\">"._T("Unable to connect to the database")."</li>";
				}
				else
				{
					echo "<li class=\"install-ok\"> "._T("Connection to database successfull")."</li>";
					$DB->Close();
				}
echo "</ul>";
				if (!$permsdb_ok)
				{
?>
	<p><?php echo _T("Database can't be reached. Please go back to enter the connection parameters again."); ?></p>
	<form action="index.php" method="POST">
		<p id="submitbutton2">
			<input type="submit" value="<?php echo _T("Go back"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
	</form>
<?php
				}
				else
				{
?>
	<p><?php echo _T("Database exists and connection parameters are OK."); ?></p>
	<form action="index.php" method="POST">
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
	</form>
<?php
				}
?>

	<br />
	</div>
	<h1 class="footerinstall"><?php echo _T("Step 5 - Access to the database"); ?></h1>
	

<?php
			break;
			case "i6":
			case "u6":
?>


	<h1><?php echo _T("Permissions on the base"); ?></h1>
	<p>
		<?php if ($step=="i6") echo _T("To run, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)"); ?>
		<?php if ($step=="u6") echo _T("In order to be updated, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)"); ?>
	</p>
<?php
				$result = "";

				// drop de table (si 'test' existe)
				$tables = $DB->MetaTables('TABLES');
				while (list($key,$value)=each($tables))
				{
					if ($value=="galette_test")
					{
						$droptest =1;
						$requete = "drop table ".$value;
						$DB->Execute($requete);
						if($DB->ErrorNo())
						{
							$error = 1;
							$result = "<li class=\"install-bad\">"._T("DROP operation not allowed")."</li>";
						}
						else
							$result = "<li class=\"install-ok\">"._T("DROP operation allowed")."</li>";
					}
				}

				// cr�ation de table
				if (!isset($error))
				{
					// � adapter selon le type de base
					$requete="create table galette_test (testcol text)";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<li class=\"install-bad\">"._T("CREATE operation not allowed")."</li>";
						$error = 1;
					}
					else
						$result .= "<li class=\"install-ok\">"._T("CREATE operation allowed")."</li>";
				}

				// cr�ation d'enregistrement
				if (!isset($error))
				{
					// � adapter selon le type de base
					$requete="INSERT into galette_test values (".$DB->qstr("test", get_magic_quotes_gpc()).")";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<li class=\"install-bad\">"._T("INSERT operation not allowed")."</li>";
						$error = 1;
					}
					else
						$result .= "<li class=\"install-ok\">"._T("INSERT operation allowed")."</li>";
				}

				// mise � jour d'enregistrement
				if (!isset($error))
				{
					// � adapter selon le type de base
					$requete="update galette_test set testcol=".$DB->qstr("test", get_magic_quotes_gpc());
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<li class=\"install-bad\">"._T("UPDATE operation not allowed")."</li>";
						$error = 1;
					}
					else
						$result .= "<li class=\"install-ok\">"._T("UPDATE operation allowed")."</li>";
				}

				// selection d'enregistrement
				if (!isset($error))
				{
					// � adapter selon le type de base
					$requete="select * from galette_test";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<li class=\"install-bad\">"._T("SELECT operation not allowed")."</li>";
						$error = 1;
					}
					else
						$result .= "<li class=\"install-ok\">"._T("SELECT operation allowed")."</li>";
				}

				// alter pour la mise � jour
				if (!isset($error) && $step=="u6")
				{
					// � adapter selon le type de base
					$requete="alter table galette_test add testalter text";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<li class=\"install-bad\">"._T("ALTER Operation not allowed")."</li>";
						$error = 1;
					}
					else
						$result .= "<li class=\"install-ok\">"._T("ALTER Operation allowed")."</li>";
				}

				// suppression d'enregistrement
				if (!isset($error))
				{
					// � adapter selon le type de base
					$requete="delete from galette_test";
					$DB->Execute($requete);
					if($DB->ErrorNo())
					{
						$result .= "<<li class=\"install-bad\">"._T("DELETE operation not allowed")."</li>";
						$error = 1;
					}
					else
						$result .= "<li class=\"install-ok\">"._T("DELETE operation allowed")."</li>";
				}

				// suppression de table
				if (!isset($error))
				{
					// � adapter selon le type de base
					$requete="drop table galette_test";
					$DB->Execute($requete);
					if (!isset($droptest))
					if($DB->ErrorNo())
					{
						$result .= "<li class=\"install-bad\">"._T("DROP OPeration not allowed")."</li>";
						$error = 1;
					}
					else
						$result .= "<li class=\"install-ok\">"._T("DROP OPeration allowed")."</li>";
				}

				if ($result!="")
					echo "<table><tr><td><ul>".$result."</ul></td></tr></table>";

				if (isset($error))
				{
?>
	<p>
		<?php if ($step=="i6") echo _T("GALETTE hasn't got enough permissions on the database to continue the installation."); ?>
		<?php if ($step=="u6") echo _T("GALETTE hasn't got enough permissions on the database to continue the update."); ?>
	</p>
	<form action="index.php" method="POST">
		<p id="submitbutton2">
			<input type="submit" value="<?php echo _T("Retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
	</form>
<?php
				}
				else
				{
?>
	<p><?php echo _T("Permissions to database are OK."); ?></p>
	<form action="index.php" method="POST">
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
	</form>
<?php
				}
?>
	<br />
	</div>
	<h1 class="footerinstall"><?php echo _T("Step 6 - Access permissions to database"); ?></h1>

<?php
			break;
		case "i7":
		case "u7":
?>

	<h1>
		<?php if ($step=="i7") echo _T("Creation of the tables"); ?>
		<?php if ($step=="u7") echo _T("Update of the tables"); ?>
	</h1>
	<p>
		<?php if ($step=="i7") echo _T("Installation Report"); ?>
		<?php if ($step=="u7") echo _T("Update Report"); ?>
	</p>
	<table><tr><td>
<ul>
<?php
			// begin : copyright (2002) the phpbb group (support@phpbb.com)	
			// load in the sql parser
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
				$query = trim($sql_query[$i]);
				if ($query != '' && $query[0] != '-')
				{
					$DB->Execute($query);
					@list($w1, $w2, $w3, $extra) = split(" ", $query, 4);
					if ($extra!="") $extra="...";
					if ($DB->ErrorNo())
					{
						echo "<li class=\"install-bad\">".$w1." ".$w2." ".$w3." ".$extra."</li>";
						//doesn't work if "drop" or "rename" is uppercase
						//if (trim($w1) != "drop" && trim($w1) != "rename") $error = true;
						//if error are not on drop,DROP,rename or RENAME we can continue
						if ( (strcasecmp(trim($w1),"drop") != 0) && (strcasecmp(trim($w1),"rename") != 0) ) $error = true;
					}
					else
						echo "<li class=\"install-ok\">".$w1." ".$w2." ".$w3." ".$extra."</li>";
				}
			}
echo "</ul>\n";
			// end : copyright (2002) the phpbb group (support@phpbb.com)

			// begin: fix overlapping fees
			/*
			$cotis = array();
			$query = "SELECT id_cotis, date_enreg, date_debut_cotis, date_fin_cotis
				    from ".$table_prefix."cotisations, ".$table_prefix."types_cotisation
				   where ".$table_prefix."cotisations.id_type_cotis = ".$table_prefix."types_cotisation.id_type_cotis
					   and ".$table_prefix."types_cotisation.cotis_extension = '1'
				   order by date_enreg;";
			$result = $DB->Execute($query);
			if (!$result)
				print $query.": ".$DB->ErrorMsg();
			else {
				while (!$result->EOF) {
					$c = $result->FetchRow();
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
				$result->Close();
			}
			if (count($cotis) > 0) {
				unset($cprev);
				foreach ($cotis as $c) {
					if (isset($cprev) && $c['start_date'] < $cprev['end_date']) {
						$c['start_date'] = $cprev['end_date'];
						$start_date = $DB->DBDate($c['start_date']);
						$new_start_date = localtime($c['start_date'], 1);
						$c['end_date'] = mktime(0, 0, 0, $new_start_date['tm_mon'] + $c['duration'] + 1, $new_start_date['tm_mday'], $new_start_date['tm_year']);
						$end_date = $DB->DBDate($c['end_date']);
						$query = "update ".$table_prefix."cotisations 
							     set date_debut_cotis = ".$start_date.", 
								 date_fin_cotis = ".$end_date."
							     where id_cotis = ".$c['id_cotis'];
						$result = $DB->Execute($query);
						if (!$result)
							print $query.": ".$DB->ErrorMsg();
						else
							$result->Close();
					}
					$cprev = $c;
				}
			}
			// end: fix overlapping fees
			*/
			// begin: fix overlapping fees
			$adh_list = array();
			$query = "SELECT id_adh from ".$table_prefix."adherents";
			$result = $DB->Execute($query);
			if (!$result)
						print $query.": ".$DB->ErrorMsg();
			else {
				while (!$result->EOF) {
					//FIXME Fields deprecated
					$adh_list[] = $result->Fields('id_adh');
					$result->MoveNext();
				}
			}

			foreach ($adh_list as $id_adh) {
				$cotis = array();
				$query = "SELECT id_cotis, date_enreg, date_debut_cotis, date_fin_cotis
						from ".$table_prefix."cotisations, ".$table_prefix."types_cotisation
						where ".$table_prefix."cotisations.id_type_cotis = ".$table_prefix."types_cotisation.id_type_cotis
						and ".$table_prefix."types_cotisation.cotis_extension = '1'
						and ".$table_prefix."cotisations.id_adh = '".$id_adh."'
						order by date_enreg;";
				$result = $DB->Execute($query);
				if (!$result)
					print $query.": ".$DB->ErrorMsg();
				else {
					while (!$result->EOF) {
						$c = $result->FetchRow();
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
					$result->Close();
				}
				if (count($cotis) > 0) {
					unset($cprev);
					foreach ($cotis as $c) {
						if (isset($cprev) && $c['start_date'] < $cprev['end_date']) {
							$c['start_date'] = $cprev['end_date'];
							$start_date = $DB->DBDate($c['start_date']);
							$new_start_date = localtime($c['start_date'], 1);
							$c['end_date'] = mktime(0, 0, 0, $new_start_date['tm_mon'] + $c['duration'] + 1, $new_start_date['tm_mday'], $new_start_date['tm_year']);
							$end_date = $DB->DBDate($c['end_date']);
							$query = "update ".$table_prefix."cotisations 
										 set date_debut_cotis = ".$start_date.", 
									 date_fin_cotis = ".$end_date."
										 where id_cotis = ".$c['id_cotis'];
							$result = $DB->Execute($query);
							if (!$result)
								print $query.": ".$DB->ErrorMsg();
							else
								$result->Close();
						}
						$cprev = $c;
					}
				}
			}

?>
	</td></tr></table>
	<p><?php echo _T("(Errors on DROP and RENAME operations can be ignored)"); ?></p>
	<?php
			if (isset($error))
			{
?>
	<p>
		<?php if ($step=="i7") echo _T("The tables are not totally created, it's maybe a permission problem."); ?>
		<?php if ($step=="u7") echo _T("The tables have not been totally created, it may be a permission problem."); ?>
		<?php if ($step=="u7") echo _T("Your database is maybe not usable, try to restore the older version."); ?>
	</p>
	<form action="index.php" method="POST">
		<p id="submitbutton2">
			<input type="submit" value="<?php echo _T("Retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
	</form>
<?php
			}
			else
			{
?>
	<p>
		<?php if ($step=="i7") echo _T("The tables has been correctly created."); ?>
		<?php if ($step=="u7") echo _T("The tables has been correctly updated."); ?>
	</p>
	<form action="index.php" method="POST">
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
	</form>
	<?php
			}
?>	
	<br />
	</div>
	<h1 class="footerinstall">
		<?php if ($step=="i7") echo _T("Step 7 - Tables Creation"); ?>
		<?php if ($step=="u7") echo _T("Step 7 - Tables Update"); ?>
	</h1>
	
<?php
			break;
		case "i8":
		case "u8":
?>

	<h1><?php echo _T("Admin settings"); ?></h1>
<?php
				if ($error_detected!="")
					echo "<p><table><tr><td><ul>".$error_detected."</ul></td></tr></table></p>";
?>	
	<p><?php echo _T("Please chose the parameters of the admin account on Galette"); ?></p>
	<form action="index.php" method="POST">
		<table>
			<tr>
				<td><label for="install_adminlogin"><?php echo _T("Username:"); ?></label></td>
				<td>
					<input type="text" name="install_adminlogin" id="install_adminlogin" value="<?php if(isset($_POST["install_adminlogin"])) echo $_POST["install_adminlogin"]; ?>">
				</td>
			</tr>
			<tr>
				<td><label for="install_adminpass"><?php echo _T("Password:"); ?></label></td>
				<td>
          <!--
					<input type="text" name="install_adminpass" value="<?php //if(isset($_POST["install_adminpass"])) echo $_POST["install_adminpass"]; ?>">
          //-->
					<input type="password" name="install_adminpass" id="install_adminpass" value="">
        </td>
      </tr>
      <tr>
				<td><label for="install_adminpass_verif"><?php echo _T("Retype password:"); ?></label></td>
        <td>
					<input type="password" name="install_adminpass_verif" id="install_adminpass_verif" value="">
				</td>
			</tr>
		</table>
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
	</form>
	<br />
	</div>
	<h1 class="footerinstall"><?php echo _T("Step 8 - Admin parameters"); ?></h1>
	
<?php
			break;
		case "i9";
		case "u9";
?>

	<h1><?php echo _T("Save the parameters"); ?></h1>
	<p><table><tr><td>
<ul>
<?php
			// cr�ation du fichier de configuration

			if($fd = @fopen (WEB_ROOT ."includes/config.inc.php", "w"))
			{
				$data = "<?php
define(\"TYPE_DB\", \"".$_POST["install_dbtype"]."\");
define(\"HOST_DB\", \"".$_POST["install_dbhost"]."\");
define(\"USER_DB\", \"".$_POST["install_dbuser"]."\");
define(\"PWD_DB\", \"".$_POST["install_dbpass"]."\");
define(\"NAME_DB\", \"".$_POST["install_dbname"]."\");
define(\"WEB_ROOT\", \"".WEB_ROOT."\");
define(\"PREFIX_DB\", \"".$_POST["install_dbprefix"]."\");
define(\"STOCK_FILES\", \"tempimages\");
?>";
				fwrite($fd,$data);
				fclose($fd);
				echo "<li class=\"install-ok\">"._T("Configuration file created (includes/config.inc.php)")."</li>";
			}
			else
			{
				echo "<li class=\"install-bad\">"._T("Unable to create configuration file (includes/config.inc.php)")."</li>";
				$error = true;
			}


			if ($step=='i9') {
				//preferences
				$default = "delete from ".$_POST["install_dbprefix"]."preferences";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_nom','galette')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_adresse','-')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_adresse2','')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_cp','-')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_ville','-')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_pays','-')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_lang',".$DB->qstr($_POST["install_lang"], get_magic_quotes_gpc()).")";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_numrows','30')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_log','2')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_email_nom','galette')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_email','mail@domain.com')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_marges_v','10')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_marges_h','10')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_hspace','10')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_vspace','5')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_hsize','90')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_vsize','35')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_cols','2')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_rows','7')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_etiq_corps','12')";
				$DB->Execute($default);
				//some new values in v0.63 for preferences
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_mail_method','0')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_mail_smtp','')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_membership_ext','12')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_beg_membership','')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_email_reply_to','')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_website','')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_abrev', 'GALETTE')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_strip','Gestion d Adherents en Ligne Extrêmement Tarabiscoté')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_tcol', 'FFFFFF')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_scol', '8C2453')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_bcol', '53248C')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_hcol', '248C53')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_bool_display_title', '')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_address', '1')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_year', '2007')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_marges_v', '15')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_marges_h', '20')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_vspace', '5')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_hspace', '10')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."preferences (nom_pref, val_pref) values ('pref_card_self', '1')";
				$DB->Execute($default);

				// contribution types
				$default = "insert into ".$_POST["install_dbprefix"]."types_cotisation(id_type_cotis,libelle_type_cotis,cotis_extension) values (1, 'annual fee', '1')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."types_cotisation(id_type_cotis,libelle_type_cotis,cotis_extension) values (2, 'reduced annual fee', '1')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."types_cotisation(id_type_cotis,libelle_type_cotis,cotis_extension) values (3, 'company fee', '1')";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."types_cotisation(id_type_cotis,libelle_type_cotis,cotis_extension) values (4, 'donation in kind', null)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."types_cotisation(id_type_cotis,libelle_type_cotis,cotis_extension) values (5, 'donation in money', null)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."types_cotisation(id_type_cotis,libelle_type_cotis,cotis_extension) values (6, 'partnership', null)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."types_cotisation(id_type_cotis,libelle_type_cotis,cotis_extension) values (7, 'annual fee (to be paid)', '1')";
				$DB->Execute($default);

				// member types
				$default = "delete from ".$_POST["install_dbprefix"]."statuts";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (1, 'President',0)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (2, 'Treasurer',10)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (3, 'Secretary',20)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (4, 'Active member',30)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (5, 'Benefactor member',40)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (6, 'Founder member',50)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (7, 'Old-timer',60)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (8, 'Society',70)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (9, 'Non-member',80)";
				$DB->Execute($default);
				$default = "insert into ".$_POST["install_dbprefix"]."statuts(id_statut,libelle_statut,priorite_statut) values (10, 'Vice-president',5)";
				$DB->Execute($default);

			}	else if ($step=='u9') {
				// TODO: reimport member and contribution types from previous installation

				//delete old admin login/password
				$default = "delete from ".$_POST["install_dbprefix"]."preferences where nom_pref = 'pref_admin_login';";
				$DB->Execute($default);
				$default = "delete from ".$_POST["install_dbprefix"]."preferences where nom_pref = 'pref_admin_pass';";
				$DB->Execute($default);
			}
			//set admin login/password
			$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_admin_login',".$DB->qstr($_POST["install_adminlogin"], get_magic_quotes_gpc()).")";
			$DB->Execute($default);
			$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_admin_pass',".$DB->qstr(md5($_POST["install_adminpass"]), get_magic_quotes_gpc()).")";
			$DB->Execute($default);

			//on some version pref_adresse2 disapeared so we test it now and add one if not present
			if( !$DB->GetRow("select nom_pref from ".$_POST["install_dbprefix"]."preferences where nom_pref='pref_adresse2'") ) {
				$default = "insert into ".$_POST["install_dbprefix"]."preferences(nom_pref,val_pref) values ('pref_adresse2','')";
				$DB->Execute($default);
			}


			// NB: il faudrait am�liorer cette partie car la d�tection
			// d'erreur ne s'effectue que sur le dernier insert. pr�voir une boucle.

			if (!$DB->ErrorNo())
				echo "<li class=\"install-ok\">"._T("Parameters saved into the database")."</li>";
			else
			{
				echo "<li class=\"install-bad\">"._T("Parameters couldn't be save into the database")."</li>";
				$error = true;
			}
?>
</ul>
	</td></tr></table></p>
<?php
			if (!isset($error))
			{
?>
	<form action="index.php" method="POST">
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Next step"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
		<input type="hidden" name="install_adminlogin" value="<?php echo $_POST["install_adminlogin"]; ?>">
		<input type="hidden" name="install_adminpass" value="<?php echo $_POST["install_adminpass"]; ?>">
		<input type="hidden" name="install_passwdverified" value="1">
		<input type="hidden" name="install_prefs_ok" value="1">
	</form>
<?php
			}
			else
			{
?>
	<form action="index.php" method="POST">
		<p><?php echo _T("Parameters couldn't be saved."); ?></p>
		<p><?php echo _T("This can come from the permissions on the file includes/config.inc.php or the impossibility to make an INSERT into the database."); ?></p>
		<p id="submitbutton2">
			<input type="submit" value="<?php echo _T("Retry"); ?>">
		</p>
		<input type="hidden" name="install_lang" value="<?php echo $_POST["install_lang"]; ?>">
		<input type="hidden" name="install_type" value="<?php echo $_POST["install_type"]; ?>">
		<input type="hidden" name="install_permsok" value="1">
		<input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>">
		<input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>">
		<input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>">
		<input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>">
		<input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>">
		<input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>">
		<input type="hidden" name="install_dbconn_ok" value="1">
		<input type="hidden" name="install_dbperms_ok" value="1">
		<input type="hidden" name="install_dbwrite_ok" value="1">
		<input type="hidden" name="install_adminlogin" value="<?php echo $_POST["install_adminlogin"]; ?>">
		<input type="hidden" name="install_adminpass" value="<?php echo $_POST["install_adminpass"]; ?>">
		<input type="hidden" name="install_passwdverified" value="1">
	</form>
<?php
			}
?>
	<br />
	</div>
	<h1 class="footerinstall"><?php echo _T("Step 9 - Saving the parameters"); ?></h1>

<?php
			break;
		case "i10":
		case "u10":
?>

	<h1>
		<?php if ($step=="i10") echo _T("Installation complete !"); ?>
		<?php if ($step=="u10") echo _T("Update complete !"); ?>
	</h1>
	<p>
		<?php if ($step=="i10") echo _T("Galette has been successfully installed!"); ?>
		<?php if ($step=="u10") echo _T("Galette has been successfully updated!"); ?>
	</p>
	<p><?php echo _T("For securing the system, please delete the install directory"); ?></p>
	<form action="../index.php" method="get">
		<p id="submitbutton3">
			<input type="submit" value="<?php echo _T("Homepage"); ?>">
		</p>
	</form>
	<br />
	</div>
	<h1 class="footerinstall">
		<?php if ($step=="i10") echo _T("Step 10 - End of the installation"); ?>
		<?php if ($step=="u10") echo _T("Step 10 - End of the update"); ?>
	</h1>






<?php
			break;
?>


<?php
	}
?>	
	
</body>
</html>
