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

$disable_gettext=true;

// I18N support information here
if (isset($_POST['pref_lang']))
        $_SESSION["pref_lang"]=$_POST['pref_lang'];
else if (isset($_GET['pref_lang']))
        $_SESSION["pref_lang"]=$_GET['pref_lang'];
if (isset($_SESSION["pref_lang"]))
	$pref_lang=$_SESSION["pref_lang"];
else
	$pref_lang="english";

$languages = array (
                    "french"  => "fr_FR@euro",
                    "english"   => "en_US",
                    "spanish"   => "es_ES@euro"
                    );
$language=$languages[$pref_lang];

if (function_exists('putenv'))
{
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
}
else
$loc='';

function add_dynamic_translation($DB, $text_orig, $error_detected)
{
	global $languages, $language;
	$l10n_table = PREFIX_DB."l10n";
	$quoted_text_orig = $DB->qstr($text_orig);
	foreach (array_values($languages) as $text_locale) {
		$quoted_locale = $DB->qstr($text_locale);
		// User is supposed to use his own language as original text.
		$quoted_trans = $DB->qstr($text_locale == $language ? $text_orig : "");
		$where_cond = "text_orig=$quoted_text_orig AND text_locale=$quoted_locale";
		$nref = $DB->GetOne("SELECT text_nref FROM $l10n_table where $where_cond");
		if (is_numeric($nref) && $nref > 0) {
			$query = "UPDATE $l10n_table
				  SET text_nref=text_nref+1
				  WHERE $where_cond";
			$result = $DB->Execute($query);
		} else {
			$query = "INSERT INTO $l10n_table
					(text_orig, text_locale, text_trans)
				  VALUES ($quoted_text_orig, $quoted_locale, $quoted_trans)";
			
			$result = parse_db_result(&$DB, $DB->Execute($query), &$error_detected, $query);
		}
	}
}

function delete_dynamic_translation($DB, $text_orig, $error_detected)
{
	global $languages;
	$l10n_table = PREFIX_DB."l10n";
	$quoted_text_orig = $DB->qstr($text_orig);
	foreach (array_values($languages) as $text_locale) {
		$quoted_locale = $DB->qstr($text_locale);
		$query = "UPDATE $l10n_table
			  SET text_nref=text_nref-1
			  WHERE text_orig=$quoted_text_orig AND text_locale=$quoted_locale";
		$result = parse_db_result(&$DB, $DB->Execute($query), &$error_detected, $query);
		if ($result)
			$result->Close();
	}
	$query = "DELETE FROM $l10n_table WHERE text_nref=0";
	$result = parse_db_result(&$DB, $DB->Execute($query), &$error_detected, $query);
	if ($result)
		$result->Close();
}

function update_dynamic_translation($DB, $text_orig, $text_locale, $text_trans, $error_detected)
{
	$l10n_table = PREFIX_DB."l10n";
	$quoted_text_orig = $DB->qstr($text_orig);
	$quoted_locale = $DB->qstr($text_locale);
	$quoted_text_trans = $DB->qstr($text_trans);
	$query = "UPDATE $l10n_table
		  SET text_trans=$quoted_text_trans
		  WHERE text_orig=$quoted_text_orig AND text_locale=$quoted_locale";
	$result = parse_db_result(&$DB, $DB->Execute($query), &$error_detected, $query);
	if ($result)
		$result->Close();
}

function get_dynamic_translation($DB, $text_orig, $text_locale)
{
	$l10n_table = PREFIX_DB."l10n";
	$query = "SELECT text_trans
		  FROM $l10n_table
		  WHERE text_orig=".$DB->qstr($text_orig). " AND 
		  	text_locale=".$DB->qstr($text_locale);
	return $DB->GetOne($query);
}

if ($loc!=$language || $disable_gettext)
{
        include(WEB_ROOT."lang/lang_".$pref_lang.".php");
        //echo "<font color='red'>Warning:</font> locale $language is probably not intalled on the server.<br>";
}

        if (!function_exists("_T"))
        {
                function _T($chaine)
                {
			global $language;
                        if (isset($GLOBALS["lang"]))
                        {
				$trans = $chaine;
                                if (isset($GLOBALS["lang"][$chaine]) && $GLOBALS["lang"][$chaine]!="")
                                        $trans = $GLOBALS["lang"][$chaine];
				else {
					$trans = false;
					if (isset($GLOBALS["DB"]))
						$trans = get_dynamic_translation($GLOBALS["DB"], $chaine, $language);
                                	if ($trans)
						$GLOBALS["lang"][$chaine] = $trans;
					else
						$trans = $chaine." (not translated)";
				}
				return $trans;
                        }
                        else
                                return _($chaine);
                }
        }

        function drapeaux()
	{
		$path = "lang";
		$dir_handle = @opendir($path);
		$languages = array();
		while ($file = readdir($dir_handle))
		{
			if (substr($file,0,5)=="lang_" && substr($file,-4)==".php")
			{
				$file = substr(substr($file,5),0,-4);
				$languages[$file]=_T($file);
			}
		}
		return $languages;
	}

/**********************************************
* some constant strings found in the database *
**********************************************/

$foo=_T("Realization:");
$foo=_T("Graphics:");
$foo=_T("Publisher:");
$foo=_T("President");
$foo=_T("Vice-president");
$foo=_T("Treasurer");
$foo=_T("Secretary");
$foo=_T("Active member");
$foo=_T("Benefactor member");
$foo=_T("Founder member");
$foo=_T("Old-timer");
$foo=_T("Legal entity");
$foo=_T("Non-member");
$foo=_T("Reduced annual contribution");
$foo=_T("Company cotisation");
$foo=_T("Donation in kind");
$foo=_T("Donation in money");
$foo=_T("Partnership");
$foo=_T("french");
$foo=_T("english");
$foo=_T("spanish");

?>
