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

if (!isset($pref_lang)) $pref_lang = 'french';

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

?>