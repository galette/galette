<?php

/**
* Import configuration settings
*/
require_once('config.inc.php');

//we start a php session
session_start();

define('GALETTE_VERSION', 'v0.7alpha');
set_include_path(get_include_path() . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/' . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/PEAR/' . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/MDB2' . PATH_SEPARATOR . WEB_ROOT . 'includes/pear/Log');

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
* - Include PEAR::Log in the sources
* - Set a database logger to replace actual one
*/
require_once('Log.php');
/** FIXME: for stables versions, log level must not be DEBUG, most probably WARNING or NOTICE */
define('_file_log', PEAR_LOG_DEBUG);				// ***** LOG : enregistrement des erreur dans un fichier de log
define('_log_file', WEB_ROOT . '/logs/galette.log');		// ***** LOG : fichier de log 
define('_screen_log', PEAR_LOG_WARNING);			// ***** LOG : affichage des erreurs à l'écran

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
* MDB2 instanciation
*/
require_once(WEB_ROOT . '/classes/mdb2.class.php');
/** TODO: Replace mdb2_db by db once adodb will be removed */
/** FIXME: mdb2 object should be stored into the session. This causes a fatal error on __destruct */
/*if( isset($_SESSION['galette']['db']) ){
	$mdb2_db = unserialize($_SESSION['galette']['db']);
}else{
	$mdb2_db = new GaletteMdb2();
	$_SESSION['galette']['db'] = serialize($mdb2_db);
}*/
$mdb2_db = new GaletteMdb2();

/**
* Load preferences
*/
require_once(WEB_ROOT . 'classes/preferences.class.php');
$p = new Preferences();
$preferences = $p->prefs;

/**
* Language instantiation
*/
require_once(WEB_ROOT . 'classes/i18n.class.php');
/** FIXME: i18n object should be stored into the session.
* Actually, unserialize fails with 'Node no longer exists' ...
*/
/*unset($_SESSION['galette_lang']);

if( isset($_SESSION['galette_lang']) ){
	$i18n = unserialize($_SESSION['galette_lang']);
}else{
	$i18n = new i18n();
	$_SESSION['galette_lang'] = serialize($i18n);
}*/
$i18n = new i18n((isset($_SESSION['pref_lang'])?$_SESSION['pref_lang']:'en_EN'));

if( isset($_POST['pref_lang']) ){ $_GET['pref_lang'] = $_POST['pref_lang']; }
if( isset($_GET['pref_lang']) ){
	$i18n->changeLanguage( $_GET['pref_lang'] );
	$_SESSION['pref_lang'] = $_GET['pref_lang'];
}

require_once(WEB_ROOT . '/classes/adherents.class.php');
if(isset($_SESSION['galette']['login']))
	$login = unserialize($_SESSION['galette']['login']);
else $login = new Adherents();

/**
* Now that all objects are correctly setted,
* we can include files that need it
*/
require_once(WEB_ROOT . 'includes/database.inc.php');
require_once(WEB_ROOT . 'includes/functions.inc.php');
include_once(WEB_ROOT . 'includes/session.inc.php');
require_once(WEB_ROOT . 'includes/i18n.inc.php');
require_once(WEB_ROOT . 'includes/smarty.inc.php');
require_once(WEB_ROOT . 'includes/picture.class.php');

?>