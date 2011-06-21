<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n functions
 *
 * PHP version 5
 *
 * Copyright © 2003-2011 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Main
 * @package   Galette
 *
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Georges Khaznadar (i18n using gettext) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

$disable_gettext=false;

$languages = array(
    'fr_FR@euro' => 'french',
    'en_US'      => 'english',
    'es_ES@euro' => 'spanish'
);

$short_languages = array(
    'french'    =>  'fr',
    'english'   =>  'en',
    'spanish'   =>  'es'
);

$language = $i18n->getLongID();
$short_language = $i18n->getAbbrev();
// $language=$languages[$pref_lang];
// $short_language = $short_languages[$pref_lang];

setlocale(LC_ALL, $language);

// if (function_exists('putenv')) putenv() can exist, but doesn't work ...
if ( @putenv("LANG=$language")
    or @putenv("LANGUAGE=$language")
    or @putenv("LC_ALL=$language")
) {
    /*
    putenv("LANG=$language");
    putenv("LANGUAGE=$language");
    putenv("LC_ALL=$language");
    */
    // PDF Generation fails with this :
    // (I guess this is due to comma conversion in real numbers)
    //$loc=setlocale(LC_ALL, $language);

    $domain = 'galette';

    @define('THIS_BASE_DIR', dirname(__FILE__));
    $textdomain = THIS_BASE_DIR . '/../lang';
    bindtextdomain($domain, $textdomain);
    textdomain($domain);
    bind_textdomain_codeset($domain, 'UTF-8');
} else {
    $loc='';
}

/**
* Add a translation stored in the database
*
* @param AdoDBConnection $DB             AdoDB databasxe connection
* @param string          $text_orig      Text to translate
* @param array           $error_detected Pointer to errors array
*
* @return void
*/
function addDynamicTranslation($DB, $text_orig, $error_detected)
{
    global $languages, $language;
    $l10n_table = PREFIX_DB . 'l10n';
    $quoted_text_orig = $DB->qstr($text_orig, get_magic_quotes_gpc());
    foreach ( array_keys($languages) as $text_locale ) {
        $quoted_locale = $DB->qstr($text_locale, get_magic_quotes_gpc());
        // User is supposed to use his own language as original text.
        $quoted_trans = $DB->qstr($text_locale == $language ? $text_orig : '');
        $where_cond = 'text_orig=' . $quoted_text_orig . ' AND text_locale=' .
            $quoted_locale;
        $nref = $DB->GetOne(
            'SELECT text_nref FROM ' . $l10n_table . ' WHERE ' . $where_cond
        );
        if ( is_numeric($nref) && $nref > 0 ) {
            $query = 'UPDATE ' . $l10n_table .
                ' SET text_nref=text_nref+1 WHERE ' . $where_cond;
            $result = $DB->Execute($query);
        } else {
            $query = 'INSERT INTO ' . $l10n_table .
                ' (text_orig, text_locale, text_trans) VALUES (' .
                $quoted_text_orig . ', ' . $quoted_locale . ', ' .
                $quoted_trans . ')';
            $result = parse_db_result(
                $DB,
                $DB->Execute($query),
                $error_detected,
                $query
            );
        }
    }
}

/**
* Delete a translation stored in the database
*
* @param AdoDBConnction $DB             AdoDB databasxe connection
* @param string         $text_orig      Text to translate
* @param array          $error_detected Pointer to errors array
*
* @return void
*/
function deleteDynamicTranslation($DB, $text_orig, $error_detected)
{
    global $languages;
    $l10n_table = PREFIX_DB . 'l10n';
    $quoted_text_orig = $DB->qstr($text_orig, get_magic_quotes_gpc());
    foreach ( array_keys($languages) as $text_locale ) {
        $quoted_locale = $DB->qstr($text_locale, get_magic_quotes_gpc());
        $query = 'UPDATE ' . $l10n_table .
            ' SET text_nref=text_nref-1 WHERE text_orig=' . $quoted_text_orig .
            ' AND text_locale=' . $quoted_locale;
        $result = parse_db_result(
            $DB,
            $DB->Execute($query),
            $error_detected,
            $query
        );
        if ( $result ) {
            $result->Close();
        }
    }
    $query = 'DELETE FROM ' . $l10n_table . ' WHERE text_nref=0';
    $result = parse_db_result($DB, $DB->Execute($query), $error_detected, $query);
    if ( $result ) {
        $result->Close();
    }
}

/**
* Update a translation stored in the database
*
* @param AdoDBConnction $DB             AdoDB databasxe connection
* @param string         $text_orig      Text to translate
* @param string         $text_locale    The locale
* @param string         $text_trans     Translated text
* @param array          $error_detected Pointer to errors array
*
* @return translated string
*/
function updateDynamicTranslation(
    $DB,
    $text_orig,
    $text_locale,
    $text_trans,
    $error_detected
) {
    $l10n_table = PREFIX_DB . 'l10n';
    $quoted_text_orig = $DB->qstr($text_orig, get_magic_quotes_gpc());
    $quoted_locale = $DB->qstr($text_locale, get_magic_quotes_gpc());
    $quoted_text_trans = $DB->qstr($text_trans, get_magic_quotes_gpc());
    $query = 'UPDATE '. $l10n_table . 'SET text_trans=' . $quoted_text_trans .
        ' WHERE text_orig=' . $quoted_text_orig . ' AND text_locale=' .
        $quoted_locale;
    $result = parse_db_result($DB, $DB->Execute($query), $error_detected, $query);
    if ( $result ) {
        $result->Close();
    }
}

/**
* Get a translation stored in the database
*
* @param AdoDBConnction $DB          AdoDB databasxe connection
* @param string         $text_orig   Text to translate
* @param string         $text_locale The locale
*
* @return translated string
*/
function getDynamicTranslation($DB, $text_orig, $text_locale)
{
    $l10n_table = PREFIX_DB . 'l10n';
    $query = "SELECT text_trans
                FROM $l10n_table WHERE text_orig=" .
                $DB->qstr($text_orig, get_magic_quotes_gpc()) . " AND
                text_locale=".$DB->qstr($text_locale, get_magic_quotes_gpc());
    return $DB->GetOne($query);
}

/** FIXME : $loc undefined */
if ( (isset($loc) && $loc!=$language) || $disable_gettext) {
    include WEB_ROOT . 'lang/lang_' . $languages[$language] . '.php';
}

if ( !function_exists('_T') ) {
    /**
    * Translate a string
    *
    * @param string $chaine The string to translate
    *
    * @return Translated string (if avaialable) ; $chaine otherwise
    */
    function _T($chaine)
    {
        global $language, $disable_gettext;
        if ( $disable_gettext === true && isset($GLOBALS['lang']) ) {
            $trans = $chaine;
            if ( isset($GLOBALS['lang'][$chaine])
                && $GLOBALS['lang'][$chaine] != ''
            ) {
                $trans = $GLOBALS['lang'][$chaine];
            } else {
                $trans = false;
                if (isset($GLOBALS['DB'])) {
                    $trans = getDynamicTranslation(
                        $GLOBALS['DB'],
                        $chaine,
                        $language
                    );
                }
                if ($trans) {
                    $GLOBALS['lang'][$chaine] = $trans;
                } else {
                    $trans = $chaine . ' (not translated)';
                }
            }
            return (I18n::seemsUtf8($trans)  ? $trans : utf8_encode($trans));
        } else {
            return _($chaine);
        }
    }
}

/**********************************************
* some constant strings found in the database *
**********************************************/
/** TODO: these string should be not be handled here */
$foo=_T("Realization:");
$foo=_T("Graphics:");
$foo=_T("Publisher:");
$foo=_T("President");
$foo=_T("Vice-president");
$foo=_T("Treasurer");
$foo=_T("Vice-treasurer");
$foo=_T("Secretary");
$foo=_T("Vice-secretary");
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
$foo = _T("annual fee");
$foo = _T("annual fee (to be paid)");
$foo = _T("company fee");
$foo = _T("donation in kind");
$foo = _T("donation in money");
$foo = _T("partnership");
$foo = _T("reduced annual fee");
?>
