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

$disable_gettext=false;

// I18N support information here
if (isset($_POST['pref_lang']))
        $_SESSION["pref_lang"]=$_POST['pref_lang'];
if (isset($_GET['pref_lang']))
        $_SESSION["pref_lang"]=$_GET['pref_lang'];
$pref_lang=$_SESSION["pref_lang"];

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
if ($loc!=$language || $disable_gettext)
{
        include(WEB_ROOT."lang/lang_".$pref_lang.".php");
        //echo "<font color='red'>Warning:</font> locale $language is probably not intalled on the server.<br>";
}

        if (!function_exists("_T"))
        {
                function _T($chaine)
                {
                        if (isset($GLOBALS["lang"]))
                        {
                                if (!isset($GLOBALS["lang"][$chaine]))
                                        return $chaine." (not translated)";
                                elseif ($GLOBALS["lang"][$chaine]=="")
                                        return $chaine." (not translated)";
                                else
                                        return $GLOBALS["lang"][$chaine];
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
