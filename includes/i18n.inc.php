<? // -*- Mode: PHP; tab-width: 2; indent-tabs-mode: nil; c-basic-offset: 2 -*- 
 
/* functions.inc.php
 * - Fonctions utilitaires
 * Copyright (c) 2003 Frédéric Jaqcuot
 * Copyright (c) 2004 Georges Khaznadar (i18n using gettext)
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

// I18N support information here

if (isset($_POST['pref_lang'])) $pref_lang=$_POST['pref_lang'];
if (isset($_GET['pref_lang'])) $pref_lang=$_GET['pref_lang'];
if (isset($HTTP_COOKIE_VARS['pref_lang'])) $pref_lang=$HTTP_COOKIE_VARS['pref_lang'];
if (!isset($pref_lang)) $pref_lang=PREF_LANG;

$languages = array (
		    "french"  => "fr_FR@euro",
		    "english"   => "en_US",
		    "spanish"   => "es_ES@euro"
		    );
$language=$languages[$pref_lang];

putenv("LANG=$language");
putenv("LANGUAGE=$language");
putenv("LC_ALL=$language");
$loc=setlocale(LC_ALL, $language);

$domain = 'galette';

/**
 * Base directory of application
 */
@define('THIS_BASE_DIR', dirname(__FILE__) );
$textdomain= THIS_BASE_DIR . "/../lang";
bindtextdomain($domain, $textdomain); 
textdomain($domain);
if ($loc!=$language){
  echo "<font color='red'>Warning:</font> locale $language is probably not intalled on the server.<br>";
}

/***********************************
 * some constant strings found in the database
 ***********************************
 */

$foo=_("Realization:");
$foo=_("Graphics:");
$foo=_("Publisher:");
$foo=_("President");
$foo=_("Vice-president");
$foo=_("Treasurer");
$foo=_("Secretary");
$foo=_("Active member");
$foo=_("Benefactor member");
$foo=_("Founder member");
$foo=_("Old-timer");
$foo=_("Legal entity");
$foo=_("Non-member");
$foo=_("Reduced annual contribution");
$foo=_("Company cotisation");
$foo=_("Donation in kind");
$foo=_("Donation in money");
$foo=_("Partnership");
$foo=_("french");
$foo=_("english");
$foo=_("spanish");

?>
