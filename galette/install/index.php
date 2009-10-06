<?php

// Copyright © 2004 Frédéric Jacquot
// Copyright © 2007 Johan Cwiklinski
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
 * index.php, 07 octobre 2007
 *
 * @package Galette
 * 
 * @author     Frédéric Jacquot
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2004 Frédéric Jaqcuot
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 */

/** FIXME: Most of these parts should *not* be present here... */

// test if galette is already installed and redirect to index page if so
$configfile = dirname( __FILE__).'/../config/config.inc.php';
$installed = file_exists($configfile);
if ($installed) {
	header("location: ../index.php");
}

define('WEB_ROOT', realpath(dirname(__FILE__) . '/../') . '/');

//we start a php session
session_start();

/** TODO: GALETTE_VERSION must be defined once and for all */
define('GALETTE_VERSION', 'v0.7alpha');
/**
* Import configuration settings
*/
require_once( WEB_ROOT . 'config/versions.inc.php');

/** TODO: include_path should be defined once also */
set_include_path(get_include_path() . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/' . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/PEAR-' . PEAR_VERSION . '/' . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/MDB2-' . MDB2_VERSION . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/Log-' . LOG_VERSION);

/*--------------------------------------------------------------------------------------
LOG and DEBUG
_file_log and _screen_log should take PEAR::LOG verbosity modes :
PEAR_LOG_EMERG		=>	System is unusable
PEAR_LOG_ALERT		=>	Immediate action required
PEAR_LOG_CRIT		=>	Critical conditions
PEAR_LOG_ERR		=>	Error conditions
PEAR_LOG_WARNING	=>	Warning conditions
PEAR_LOG_NOTICE		=>	Normal but significant
PEAR_LOG_INFO		=>	Informational
PEAR_LOG_DEBUG		=>	Debug-level messages

--------------------------------------------------------------------------------------*/
/** TODO
* - Set a database logger to replace actual one
*/
require_once('Log.php');
/** FIXME: for stables versions, log level must not be DEBUG, most probably WARNING or NOTICE */
define('_file_log', PEAR_LOG_DEBUG);				// ***** LOG : enregistrement des erreur dans un fichier de log
define('_log_file', WEB_ROOT . '/logs/galette.log');		// ***** LOG : fichier de log 
define('_screen_log', PEAR_LOG_ERR);				// ***** LOG : affichage des erreurs à l'écran

$conf = array(
	'error_prepend'	=>	'<div id="error" class="error">',
	'error_append'	=>	'</div>'
);
$display = &Log::singleton('display', '', 'galette', $conf, _screen_log);
$file = &Log::singleton('file', _log_file, 'galette', '', _file_log);

$log = &Log::singleton('composite');
$log->addChild($display);
$log->addChild($file);

/**
* Language instantiation
*/
require_once(WEB_ROOT . 'classes/i18n.class.php');

if(isset($_POST['install_lang'])) $_SESSION['pref_lang'] = $_POST['install_lang'];
$i18n = new i18n((isset($_SESSION['pref_lang']) && $_SESSION['pref_lang']!='')?$_SESSION['pref_lang']:i18n::DEFAULT_LANG);

/** FIXME: ... end fixme :-) */

/**
* Now that all objects are correctly setted,
* we can include files that need it
*/
require_once(WEB_ROOT . 'includes/functions.inc.php');
require_once(WEB_ROOT . 'includes/i18n.inc.php');

$step="1";
$error_detected = false;

	// traitement page 1 - language
	if (isset($_POST['install_lang']))
	{
		$step = '2';
	}

	if ($error_detected == '' && isset($_POST['install_type']) )
	{
		if ($_POST['install_type'] == 'install')
			$step = 'i3';
		elseif (substr($_POST['install_type'],0,7) == 'upgrade')
			$step = 'u3';
		else
	  		$error_detected .= '<li>' . _T("Installation mode unknown") . '</li>';
	 }

	if ($error_detected == '' && isset($_POST['install_permsok']))
	{
		if ($_POST['install_type'] == 'install')
			$step = 'i4';
		elseif (substr($_POST['install_type'],0,7) == 'upgrade')
			$step = 'u4';
		else
	  		$error_detected .= '<li>' . _T("Installation mode unknown") . '</li>';
	 }

	if (
		$error_detected == ''
		&& isset($_POST['install_dbtype'])
		&& isset($_POST['install_dbhost'])
		&& isset($_POST['install_dbuser'])
		&& isset($_POST['install_dbpass'])
		&& isset($_POST['install_dbname'])
		&& isset($_POST['install_dbprefix']))
	{
		if ($_POST['install_dbtype'] != 'mysql' && $_POST['install_dbtype'] != 'pgsql')
	  		$error_detected .= '<li>' . _T("Database type unknown") . '</li>';
		if($_POST['install_dbhost'] == '')
			$error_detected .= '<li>' . _T("No host") . '</li>';
		if ($_POST['install_dbuser'] == '')
	  		$error_detected .= '<li>' . _T("No user name") . '</li>';
		if ($_POST['install_dbpass'] == '')
	  		$error_detected .= '<li>' . _T("No password") . '</li>';
		if ($_POST['install_dbname'] == '')
	  		$error_detected .= '<li>' . _T("No database name") . '</li>';
		if ($error_detected == '')
		{
			if (isset($_POST['install_dbconn_ok']))
			{
				require_once('../classes/mdb2.class.php');

				define('TYPE_DB', $_POST['install_dbtype']);
				define('USER_DB', $_POST['install_dbuser']);
				define('PWD_DB', $_POST['install_dbpass']);
				define('HOST_DB', $_POST['install_dbhost']);
				define('NAME_DB', $_POST['install_dbname']);

				$mdb = new GaletteMdb2();

				include(WEB_ROOT."/includes/adodb" . ADODB_VERSION . "/adodb.inc.php");
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
			elseif(isset($_POST['install_dbko']) && $_POST['install_dbko'] == 1 )
				$step = ($_POST['install_type'] == 'install')?'i4':'u4';
			else
				$step="i5";
		}
	 }

//we set current step title
switch( $step ){
	case '1':
 		$step_title = _T("Language");
		break;
	case '2':
		$step_title = _T("Installation mode");
		break;
	case 'i3':
	case 'u3':
		$step_title = _T("Permissions");
		break;
	case 'i4':
	case 'u4':
		$step_title = _T("Database");
		break;
	case 'i5':
	case 'u5':
		$step_title = _T("Access to the database");
		break;
	case 'i6':
	case 'u6':
		$step_title = _T("Access permissions to database");
		break;
	case 'i7':
	case 'u7':
		$step_title = _T("Tables Creation/Update");
		break;
	case 'i8':
	case 'u8':
		$step_title = _T("Admin parameters");
		break;
	case 'i9':
	case 'u9':
		$step_title = _T("Saving the parameters");
		break;
	case 'i10':
	case 'u10':
		$step_title = _T("End!");
		break;
}
header('Content-Type: text/html; charset=UTF-8');	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo $i18n->getAbbrev(); ?>">
	<head>
		<title><?php echo _T("Galette Installation") . ' - ' . $step_title; ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
		<link rel="stylesheet" type="text/css" href="../templates/default/galette.css"/>

		<script type="text/javascript" src="../includes/jquery/jquery-<?php echo JQUERY_VERSION; ?>.min.js"></script>
		<script type="text/javascript" src="../includes/jquery/jquery.bgiframe.pack.js"></script>
		<script type="text/javascript" src="../includes/jquery/jquery.bgFade.js"></script>
		<script type="text/javascript" src="../includes/jquery/jquery.corner.js"></script>
		<script type="text/javascript" src="../includes/jquery/chili-1.7.pack.js"></script>
		<script type="text/javascript" src="../includes/jquery/jquery.tooltip.pack.js"></script>
		<script type="text/javascript" src="../includes/common.js"></script>
		<script type="text/javascript">
			$(function() {
				$('#footerinstall').corner();
				$('#install_lang').change(function() {
					this.form.submit();
				});

			});
		</script>
	</head>
	<body>
		<h1 id="titre"><?php echo _T("Galette installation") . ' - ' . $step_title ?></h1>
		<div id="installpage">
	
<?php
	switch ($step)
	{
		case '1':
?>
			<h2><?php echo _T("Welcome to the Galette Install!"); ?></h2>
			<p><label for="install_lang"><?php echo _T("Please select your administration language"); ?></label></p>
			<form action="index.php" method="post">
				<p>
					<select name="install_lang" id="install_lang">
<?php
			foreach($i18n->getList() as $langue){
				echo "\t\t\t\t\t<option value=\"" . $langue->getID() . "\" style=\"background:url(../" . $langue->getFlag() . ") no-repeat;padding-left:30px;\"" . ((i18n::DEFAULT_LANG == $langue->getID())?" selected=\"selected\"":"") . ">" . ucwords($langue->getName()) . "</option>\n";
			}
?>
					</select>
				</p>
				<p id="submit_btn"><input type="submit" value="<?php echo _T("Next Page"); ?>"/></p>
			</form>
		</div>
<?php
			break; //ends first step
		case '2':
?>
			<h2><?php echo _T("Installation mode"); ?></h2>
			<p><?php echo _T("Select installation mode to launch"); ?></p>
			<form action="index.php" method="post">
				<p>
					<input type="radio" name="install_type" value="install" checked="checked" id="install"> <label for="install"><?php echo _T("New installation:"); ?></label><br />
					<?php echo _T("You're installing Galette for the first time, or you wish to erase an older version of Galette without keeping your data"); ?>
				</p>
<?php
			$dh = opendir("sql");
			$update_scripts = array();
			while (($file = readdir($dh)) !== false)
			{
				if (preg_match("/upgrade-to-(.*)-mysql.sql/",$file,$ver))
					$update_scripts[] = $ver[1];
			}
			closedir($dh);
			asort($update_scripts);
			$last = "0.00";
			if( count($update_scripts) > 0 )
				echo "<p>" . _T("Update") . '<br/><span id="warningbox">' . _T("Warning: Don't forget to backup your current database.") . "</span></p>\n\t\t\t\t<ul class=\"list\">";
			while (list ($key, $val) = each ($update_scripts))
			{
				echo "\t\t\t\t\t<li>";
?>
					<input type="radio" name="install_type" value="upgrade-<?php echo $val; ?>" id="upgrade-<?php echo $val; ?>"> <label for="upgrade-<?php echo $val; ?>">
<?php
				if ($last!=number_format($val-0.01,2))
					echo _T("Your current Galette version is comprised between") . " " . $last . " " . _T("and") . " " . number_format($val-0.01,2) . "</label><br />";
				else
					echo _T("Your current Galette version is") . " " . number_format($val-0.01,2) . "</label>";
				$last = $val;
?>
<?php
				echo "\t\t\t\t\t</li>";
			}
			if( count($update_scripts) > 0 )
				echo "\t\t\t\t</ul>";

?>
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
				</p>
			</form>
		</div>
<?php
			break; //ends second step
		case 'i3':
		case 'u3':
?>

			<h2><?php echo _T("Files permissions"); ?></h2>
			<ul class="list" id="paths">
<?php
			$perms_ok = true;
			$files_need_rw = array ('/templates_c',
						'/photos',
						'/cache',
						'/tempimages',
						'/config',
						'/exports');
			foreach ($files_need_rw as $file)
			{
				if (!is_writable(dirname(__FILE__) . '/..' . $file))
				{
					$perms_ok = false;
					echo "<li class=\"install-bad\">" . $file . "</li>";
				}
				else
					echo "<li class=\"install-ok\">" . $file . "</li>";
			}
			echo '</ul>';
			if (!$perms_ok)
			{
?>
			<p><?php 
if ($step == 'i3') echo _T("For a correct functioning, Galette needs the Write permission on these files.");
if ($step == 'u3') echo _T("In order to be updated, Galette needs the Write permission on these files.");
			?></p>
			<p id="errorbox"><?php echo _T("Files permissions are not OK!"); ?></p>
			<p><?php echo _T("Under UNIX/Linux, you can give the permissions using those commands"); ?><br />
				<code>chown <em><?php echo _T("apache_user"); ?></em> <em><?php echo _T("file_name"); ?></em><br />
				chmod 600 <em><?php echo _T("file_name"); ?></em> <?php echo _T("(for a file)"); ?><br />
				chmod 700 <em><?php echo _T("direcory_name"); ?></em> <?php echo _T("(for a directory)"); ?></code>
			</p>
			<p><?php echo _T("Under Windows, check these files are not in Read-Only mode in their property panel."); ?></p>
			<form action="index.php" method="post">
				<p id="retry_btn">
					<input type="submit" value="<?php echo _T("Retry"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
				</p>
			</form>
<?php
			}
			else
			{
?>
			<p id="infobox"><?php echo _T("Files permissions are OK!"); ?></p>
			<form action="index.php" method="POST">
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
				</p>
			</form>
<?php
			}
?>
		</div>
<?php
			break; //ends third step
		case 'i4':
		case 'u4':
?>
			<h2><?php echo _T("Database"); ?></h2>
<?php
				if ($error_detected!=""){
?>
			<div id="errorbox">
				<h1><?php echo _T("- ERROR -"); ?></h1>
				<ul><?php echo $error_detected; ?></ul>
			</div>
<?php
				}
?>
			<p><?php if ($step == 'i4') echo _T("If it hadn't been made, create a database and a user for Galette."); ?>
			<?php if ($step == 'u4') echo _T("Enter connection data for the existing database."); ?><br />
			<?php echo _T("The needed permissions are CREATE, DROP, DELETE, UPDATE, SELECT and INSERT."); ?></p>
			<form action="index.php" method="post">
				<fieldset class="cssform">
					<legend><?php echo _T("Database"); ?></legend>
					<p>
						<label class="bline" for="install_dbtype"><?php echo _T("Database type:"); ?></label>
						<select name="install_dbtype" id="install_dbtype">
							<option value="mysql"<?php if(isset($_POST['install_dbtype']) && $_POST['install_dbtype'] == 'mysql'){echo ' selected="selected"';} ?>>Mysql</option>
							<option value="pgsql"<?php if(isset($_POST['install_dbtype']) && $_POST['install_dbtype'] == 'pgsql'){echo ' selected="selected"';} ?>>Postgresql</option>
						</select>
					</p>
					<p>
						<label class="bline" for="install_dbhost"><?php echo _T("Host:"); ?></label>
						<input type="text" name="install_dbhost" id="install_dbhost" value="<?php echo (isset($_POST['install_dbhost']))?$_POST['install_dbhost']:'localhost'; ?>">
					</p>
					<p>
						<label class="bline" for="install_dbuser"><?php echo _T("User:"); ?></label>
						<input type="text" name="install_dbuser" id="install_dbuser" value="<?php if(isset($_POST['install_dbuser'])) echo $_POST['install_dbuser']; ?>">
					</p>
					<p>
						<label class="bline" for="install_dbpass"><?php echo _T("Password:"); ?></label>
						<input type="password" name="install_dbpass" id="install_dbpass" value="<?php if(isset($_POST['install_dbpass'])) echo $_POST['install_dbpass']; ?>">
					</p>
					<p>
						<label class="bline" for="install_dbname"><?php echo _T("Database:"); ?></label>
						<input type="text" name="install_dbname" id="install_dbname" value="<?php if(isset($_POST['install_dbname'])) echo $_POST['install_dbname']; ?>">
					</p>
					<p>
<?php
if (substr($_POST['install_type'],0,8) == 'upgrade-'){echo '<span class="required">' .  _T("(Indicate the CURRENT prefix of your Galette tables)") . '</span><br/>';}
?>
						<label class="bline" for="install_dbprefix"><?php echo _T("Table prefix:"); ?></label>
						<input type="text" name="install_dbprefix" id="install_dbprefix" value="<?php echo (isset($_POST['install_dbprefix']))?$_POST['install_dbprefix']:'galette_'; ?>">
					</p>
				</fieldset>
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
				</p>
			</form>
		</div>
<?php
			break; //ends fourth step
		case 'i5':
		case 'u5':
?>
			<h2><?php echo _T("Check of the database"); ?></h2>
			<p><?php echo _T("Check the parameters and the existence of the database"); ?></p>
<?php
				require_once('../classes/mdb2.class.php');
				$permsdb_ok = true;

				if($test = GaletteMdb2::testConnectivity(
					$_POST['install_dbtype'],
					$_POST['install_dbuser'],
					$_POST['install_dbpass'],
					$_POST['install_dbhost'],
					$_POST['install_dbname'])
				){
					$permsdb_ok = false;
					echo '<div id="errorbox">';
					echo '<h1>' . _T("Unable to connect to the database") . '</h1>';
					echo '<p class="debuginfos">' . $test['main'] . '<span>(' . $test['debug'] . ')</span>' . '</p>';
					echo '</div>';
				}else{
					echo '<p id="infobox">' . _T("Connection to database successfull") . '</p>';
				}

				if (!$permsdb_ok)
				{
?>
			<p><?php echo _T("Database can't be reached. Please go back to enter the connection parameters again."); ?></p>
			<form action="index.php" method="POST">
				<p id="retry_btn">
					<input type="submit" value="<?php echo _T("Go back"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbko" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
				</p>
			</form>
<?php
				}
				else
				{
?>
			<p><?php echo _T("Database exists and connection parameters are OK."); ?></p>
			<form action="index.php" method="POST">
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
					<input type="hidden" name="install_dbconn_ok" value="1">
				</p>
			</form>
<?php
				}
?>
		</div>
<?php
			break; //ends 5th step
		case 'i6':
		case 'u6':
?>
			<h2><?php echo _T("Permissions on the base"); ?></h2>
			<p><?php
if ($step == 'i6') echo _T("To run, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)");
if ($step == 'u6') echo _T("In order to be updated, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)");
			?></p>
<?php
				/** FIXME: when tables already exists and DROP not allowed at this time
				the showd error is about CREATE, whenever CREATE is allowed */
				//We delete the table if exists, no error at this time
				$mdb->testDropTable();

				$results = $mdb->grantCheck();

				$result = '';
				$error = false;
				//test returned values
				if( MDB2::isError($results['create']) ){
					$result .= '<li class="install-bad debuginfos">' . _T("CREATE operation not allowed") . '<span>' . $results['create']->getMessage() . '</span></li>';
					$error = true;
				}elseif( $results['create'] != '' )
					$result .= '<li class="install-ok">' . _T("CREATE operation allowed") . '</li>';

				if( MDB2::isError($results['insert']) ){
					$result .= '<li class="install-bad debuginfos">' . _T("INSERT operation not allowed") . '<span>' . $results['insert']->getMessage() . '<br/>(' . $results['insert']->getDebugInfo() . ')</span></li>';
					$error = true;
				}elseif( $results['insert'] != '' )
					$result .= '<li class="install-ok">' . _T("INSERT operation allowed") . '</li>';

				if( MDB2::isError($results['update']) ){
					$result .= '<li class="install-bad debuginfos">' . _T("UPDATE operation not allowed") . '<span>' . $results['update']->getMessage() . '<br/>(' . $results['update']->getDebugInfo() . ')</span></li>';
					$error = true;
				}elseif( $results['update'] != '' )
					$result .= '<li class="install-ok">' . _T("UPDATE operation allowed") . '</li>';

				if( MDB2::isError($results['select']) ){
					$result .= '<li class="install-bad debuginfos">' . _T("SELECT operation not allowed") . '<span>' . $results['select']->getMessage() . '<br/>(' . $results['select']->getDebugInfo() . ')</span></li>';
					$error = true;
				}elseif( $results['select'] != '' )
					$result .= '<li class="install-ok">' . _T("SELECT operation allowed") . '</li>';

				/** TODO: check at upgrade time if Galette can ALTER */

				if( MDB2::isError($results['delete']) ){
					$result .= '<li class="install-bad debuginfos">' . _T("DELETE operation not allowed") . '<span>' . $results['delete']->getMessage() . '<br/>(' . $results['delete']->getDebugInfo() . ')</span></li>';
					$error = true;
				}elseif( $results['delete'] != '' )
					$result .= '<li class="install-ok">' . _T("DELETE operation allowed") . '</li>';

				if( MDB2::isError($results['drop']) ){
					$result .= '<li class="install-bad debuginfos">' . _T("DROP operation not allowed") . '<span>' . $results['drop']->getMessage() . '<br/>(' . $results['drop']->getDebugInfo() . ')</span></li>';
					$error = true;
				}elseif( $results['drop'] != '' )
					$result .= '<li class="install-ok">' . _T("DROP operation allowed") . '</li>';

				if( MDB2::isError($results['alter']) ){
					$result .= '<li class="install-bad debuginfos">' . _T("ALTER Operation not allowed") . '<span>' . $results['alter']->getMessage() . '</span></li>';
					$error = true;
				}elseif( $results['alter'] != '' )
					$result .= '<li class="install-ok">' . _T("ALTER Operation allowed") . '</li>';


				if ($error){
					echo "<ul>" . $result . "</ul>\n";
					echo '<div id="errorbox">';
					echo '<h1>';
					if( $step == 'i6' ) echo _T("GALETTE hasn't got enough permissions on the database to continue the installation.");
					if ($step == 'u6') echo _T("GALETTE hasn't got enough permissions on the database to continue the update.");
					echo '</h1>';
					echo '</div>';
?>
			<form action="index.php" method="post">
				<p id="retry_btn">
					<input type="submit" value="<?php echo _T("Retry"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
					<input type="hidden" name="install_dbconn_ok" value="1">
				</p>
			</form>
<?php
				}
				else
				{
?>
			<ul><?php echo $result; ?></ul>
			<p id="infobox"><?php echo _T("Permissions to database are OK."); ?></p>
			<form action="index.php" method="POST">
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
					<input type="hidden" name="install_dbconn_ok" value="1">
					<input type="hidden" name="install_dbperms_ok" value="1">
				</p>
			</form>
<?php
				}
?>
		</div>
<?php
			break; //ends 6th step
		case 'i7':
		case 'u7':
?>

			<h2><?php
if ($step == 'i7') echo _T("Creation of the tables");
if ($step == 'u7') echo _T("Update of the tables"); ?></h2>
			<p><?php
if ($step == 'i7') echo _T("Installation Report");
if ($step == 'u7') echo _T("Update Report"); ?></p>
<ul>
<?php
			// begin : copyright (2002) the phpbb group (support@phpbb.com)	
			// load in the sql parser
			include('sql_parse.php');

			$prefix = '';
			$table_prefix = $_POST['install_dbprefix'];
			if ($step == 'u7')
			{
				$prefix='upgrade-to-';

				$dh = opendir('sql');
				$update_scripts = array();
				$first_file_found = false;
				while (($filesql = readdir($dh)) !== false)
				{
					if (preg_match('/upgrade-to-(.*)-' . $_POST['install_dbtype'] . '.sql/', $filesql, $ver))
					{
						if (substr($_POST['install_type'], 8)<=$ver[1])
							$update_scripts[$ver[1]] = $filesql;
					}
				}
				ksort($update_scripts);
			}
			else
				$update_scripts['current'] = $_POST['install_dbtype'] . '.sql';

			ksort($update_scripts);
			$sql_query = '';
			while(list($key,$val)=each($update_scripts))
				$sql_query .= @fread(@fopen('sql/' . $val, 'r'), @filesize('sql/' . $val)) . "\n";

			$sql_query = preg_replace('/galette_/', $table_prefix, $sql_query);
			$sql_query = remove_remarks($sql_query);

			$sql_query = split_sql_file($sql_query, ';');

			for ($i = 0; $i < sizeof($sql_query); $i++)
			{
				$query = trim($sql_query[$i]);
				if ($query != '' && $query[0] != '-')
				{
					$result = $mdb->query($query);
					@list($w1, $w2, $w3, $extra) = explode(' ', $query, 4);
					if ($extra != '') $extra = '...';
					if ( MDB2::isError($result) ) {
						echo '<li class="install-bad debuginfos">' . $w1 . ' ' . $w2 . ' ' . $w3 . ' ' . $extra . '<span>' . $mdb->getErrorMessage() . '<br/>(' . $mdb->getErrorDetails() . ')</span></li>';

						//if error are not on drop, DROP, rename or RENAME we can continue
						if ( (strcasecmp(trim($w1),"drop") != 0) && (strcasecmp(trim($w1),"rename") != 0) ) $error = true;
					}
					else
						echo "<li class=\"install-ok\">".$w1." ".$w2." ".$w3." ".$extra."</li>";
				}
			}
echo "</ul>\n";
			/**
			* FIXME: is this code util ?
			* shouldn't overlapping fess catched when stored ?
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
						list($by, $bm, $bd) = explode("-", $c['date_debut_cotis']);
						list($ey, $em, $ed) = explode("-", $c['date_fin_cotis']);
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
			<p><?php echo _T("(Errors on DROP and RENAME operations can be ignored)"); ?></p>
<?php
			if (isset($error))
			{
?>
			<p id="errorbox"><?php
if ($step == 'i7') echo _T("The tables are not totally created, it may be a permission problem.");
if ($step == 'u7'){
	echo _T("The tables have not been totally created, it may be a permission problem.");
	echo '<br/>';
	echo _T("Your database is maybe not usable, try to restore the older version.");
}
?>
			</p>
			<form action="index.php" method="POST">
				<p id="retry_btn">
					<input type="submit" value="<?php echo _T("Retry"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
					<input type="hidden" name="install_dbconn_ok" value="1">
					<input type="hidden" name="install_dbperms_ok" value="1">
				</p>
			</form>
<?php
			}
			else
			{
?>
			<p id="infobox"><?php
if ($step=="i7") echo _T("The tables has been correctly created.");
if ($step=="u7") echo _T("The tables has been correctly updated."); ?></p>
			<form action="index.php" method="POST">
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
					<input type="hidden" name="install_dbconn_ok" value="1">
					<input type="hidden" name="install_dbperms_ok" value="1">
					<input type="hidden" name="install_dbwrite_ok" value="1">
				</p>
			</form>
	<?php
			}
?>	
		</div>
<?php
			break; //ends 7th step
		case 'i8':
		case 'u8':
?>
			<h2><?php echo _T("Admin settings"); ?></h2>
<?php
				if ($error_detected != '')
					echo '<div id="errorbox"><h1>' . _T("- ERROR -") . '</h1><ul>' . $error_detected . '</ul></div>';
?>	
			<form action="index.php" method="post">
				<fieldset class="cssform">
					<legend><?php echo _T("Please chose the parameters of the admin account on Galette"); ?></legend>
					<p>
						<label for="install_adminlogin" class="bline"><?php echo _T("Username:"); ?></label>
						<input type="text" name="install_adminlogin" id="install_adminlogin" value="<?php if(isset($_POST['install_adminlogin'])) echo $_POST['install_adminlogin']; ?>">
					</p>
					<p>
						<label for="install_adminpass" class="bline"><?php echo _T("Password:"); ?></label>
						<input type="password" name="install_adminpass" id="install_adminpass" value="">
					</p>
					<p>
						<label for="install_adminpass_verif" class="bline"><?php echo _T("Retype password:"); ?></label>
						<input type="password" name="install_adminpass_verif" id="install_adminpass_verif" value="">
					</p>
				</fieldset>
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
					<input type="hidden" name="install_dbconn_ok" value="1">
					<input type="hidden" name="install_dbperms_ok" value="1">
					<input type="hidden" name="install_dbwrite_ok" value="1">
				</p>
			</form>
		</div>
<?php
			break; //ends 8th step
		case "i9";
		case "u9";
			define('PREFIX_DB', $_POST['install_dbprefix']);
			require_once('../classes/preferences.class.php');
			require_once('../classes/contributions_types.class.php');
			require_once('../classes/status.class.php');
			require_once('../classes/texts.class.php');

			$oks = array();
			$errs = array();
?>
			<h2><?php echo _T("Save the parameters"); ?></h2>
<?php
			// création du fichier de configuration

			if($fd = @fopen (WEB_ROOT . 'config/config.inc.php', 'w'))
			{
				$data = '<?php
define("TYPE_DB", "' . $_POST['install_dbtype'] . '");
define("HOST_DB", "' . $_POST['install_dbhost'] . '");
define("USER_DB", "' . $_POST['install_dbuser'] . '");
define("PWD_DB", "' . $_POST['install_dbpass'] . '");
define("NAME_DB", "' . $_POST['install_dbname'] . '");
define("WEB_ROOT", \'' . WEB_ROOT . '\');
define("PREFIX_DB", "' . $_POST['install_dbprefix'] . '");
define("STOCK_FILES", "tempimages");
?>';
				fwrite($fd,$data);
				fclose($fd);
				$oks[] =  '<li class="install-ok">' . _T("Configuration file created (config/config.inc.php)") . '</li>';
			}
			else
			{
				$errs[] =  '<li class="install-bad">' . _T("Unable to create configuration file (config/config.inc.php)") . '</li>';
				$error = true;
			}

			if ($step=='i9') {
				$prefs = new Preferences();
				$ct = new ContributionsTypes();
				$status = new Status();
				$texts = new Texts();

				//init default values
				$prefs->installInit(
					$i18n->getID(),
					$_POST["install_adminlogin"],
					md5($_POST["install_adminpass"])
				);
				if( $prefs->inError() )
					$errs[] = '<li class="install-bad">' . _T("Default preferences cannot be initialized.") . '<span>' . $prefs->getErrorMessage() . '(' . $prefs->getErrorDetails() . ')</span></li>';
				else
					$oks[] = '<li class="install-ok">' . _T("Default preferences were successfully stored.") . '</li>';

				$ct->installInit();
				if( $ct->inError() )
					$errs[] = '<li class="install-bad">' . _T("Default contributions types cannot be initialized.") . '<span>' . $ct->getErrorMessage() . '(' . $ct->getErrorDetails() . ')</span></li>';
				else
					$oks[] = '<li class="install-ok">' . _T("Default contributions types were successfully stored.") . '</li>';

				$status->installInit();
				if( $status->inError() )
					$errs[] = '<li class="install-bad">' . _T("Default status cannot be initialized.") . '<span>' . $status->getErrorMessage() . '(' . $status->getErrorDetails() . ')</span></li>';
				else
					$oks[] = '<li class="install-ok">' . _T("Default status were successfully stored.") . '</li>';

				$texts->installInit();
				if( $texts->inError() )
					$errs[] = '<li class="install-bad">' . _T("Default texts cannot be initialized.") . '<span>' . $texts->getErrorMessage() . '(' . $texts->getErrorDetails() . ')</span></li>';
				else
					$oks[] = '<li class="install-ok">' . _T("Default texts were successfully stored.") . '</li>';

			} else if ($step=='u9') {
				$prefs->pref_admin_login = $_POST['install_adminlogin'];
				$prefs->pref_admin_pass = $_POST['install_adminpass'];
			}
?>
			<div<?php if( count($errs) == 0) echo ' id="infobox"'; ?>>
				<ul>
<?php
foreach($oks as $o)
	echo "\t\t\t\t\t" . $o . "\n";
?>
				</ul>
			</div>
<?php
			if ( count($errs) ==0 )
			{
?>
			<form action="index.php" method="POST">
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Next step"); ?>">
					<input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>">
					<input type="hidden" name="install_permsok" value="1">
					<input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>">
					<input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>">
					<input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>">
					<input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>">
					<input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>">
					<input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>">
					<input type="hidden" name="install_dbconn_ok" value="1">
					<input type="hidden" name="install_dbperms_ok" value="1">
					<input type="hidden" name="install_dbwrite_ok" value="1">
					<input type="hidden" name="install_adminlogin" value="<?php echo $_POST['install_adminlogin']; ?>">
					<input type="hidden" name="install_adminpass" value="<?php echo $_POST['install_adminpass']; ?>">
					<input type="hidden" name="install_passwdverified" value="1">
					<input type="hidden" name="install_prefs_ok" value="1">
				</p>
			</form>
<?php
			}
			else
			{
?>
			<div id="errorbox">
				<h1><?php echo _T("- ERROR -"); ?></h1>
				<p><?php echo _T("Parameters couldn't be saved."); ?><br/><?php echo _T("This can come from the permissions on the file config/config.inc.php or the impossibility to make an INSERT into the database."); ?></p>
				<p><?php echo _T("Check above errors to know what went wrong."); ?></p>
				<ul><?php echo implode("\n", $errs); ?></ul>
			</div>
			<form action="index.php" method="POST">
				<p id="retry_btn">
					<input type="submit" value="<?php echo _T("Retry"); ?>">
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
				</p>
			</form>
<?php
			}
?>
		</div>
<?php
			break; //ends 9th step
		case "i10":
		case "u10":
?>
			<h2><?php 
if ($step=="i10") echo _T("Installation complete !");
if ($step=="u10") echo _T("Update complete !"); ?></h2>
			<p><?php
if ($step=="i10") echo _T("Galette has been successfully installed!");
if ($step=="u10") echo _T("Galette has been successfully updated!"); ?></p>
			<p><?php echo _T("To secure the system, please delete the install directory"); ?></p>
			<form action="../index.php" method="get">
				<p id="submit_btn">
					<input type="submit" value="<?php echo _T("Homepage"); ?>">
				</p>
			</form>
		</div>
<?php
			break; //ends 10th step and finish install
	} // switch
?>
		<div id="footerinstall">
			<p><?php echo _T("Steps:"); ?></p>
			<ol>
				<li<?php if( $step == '1') echo ' class="current"'; ?>><?php echo _T("Language"); ?> - </li>
				<li<?php if( $step == '2') echo ' class="current"'; ?>><?php echo _T("Installation mode"); ?> - </li>
				<li<?php if( $step == 'i3' || $step == 'u3' ) echo ' class="current"'; ?>><?php echo _T("Permissions"); ?> - </li>
				<li<?php if( $step == 'i4' || $step == 'u4') echo ' class="current"'; ?>><?php echo _T("Database"); ?> - </li>
				<li<?php if( $step == 'i5' || $step == 'u5' ) echo ' class="current"'; ?>><?php echo _T("Access to the database"); ?> - </li>
				<li<?php if( $step == 'i6' || $step == 'u6' ) echo ' class="current"'; ?>><?php echo _T("Access permissions to database"); ?> - </li>
				<li<?php if( $step == 'i7' || $step == 'u7' ) echo ' class="current"'; ?>><?php echo _T("Tables Creation/Update"); ?> - </li>
				<li<?php if( $step == 'i8' || $step == 'u8' ) echo ' class="current"'; ?>><?php echo _T("Admin parameters"); ?> - </li>
				<li<?php if( $step == 'i9' || $step == 'u9' ) echo ' class="current"'; ?>><?php echo _T("Saving the parameters"); ?> - </li>
				<li<?php if( $step == 'i10' || $step == 'u10' ) echo ' class="current"'; ?>><?php echo _T("End!"); ?></li>
			</ol>
		</div>
	</body>
</html>
