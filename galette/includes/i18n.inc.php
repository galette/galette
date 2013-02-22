<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n functions
 *
 * PHP version 5
 *
 * Copyright © 2003-2013 The Galette Team
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
 * @copyright 2003-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

if (!defined('GALETTE_ROOT')) {
       die("Sorry. You can't access directly to this file");
}

use Analog\Analog as Analog;

$disable_gettext=true;

$language = $i18n->getLongID();
$short_language = $i18n->getAbbrev();

setlocale(LC_ALL, $language, $i18n->getAlternate());

// if (function_exists('putenv')) putenv() can exist, but doesn't work ...
if ( @putenv("LANG=$language")
    or @putenv("LANGUAGE=$language")
    or @putenv("LC_ALL=$language")
) {
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
* @param string $text_orig      Text to translate
* @param array  $error_detected Pointer to errors array
*
* @return void
*/
function addDynamicTranslation($text_orig, $error_detected)
{
    global $zdb, $i18n;
    $l10n_table = PREFIX_DB . 'l10n';

    try {
        foreach (  $i18n->getList() as $lang ) {
            //check if translation already exists
            $select = new Zend_Db_Select($zdb->db);
            $select->from($l10n_table, 'text_nref')
                ->where('text_orig = ?', $text_orig)
                ->where('text_locale = ?', $lang->getLongID());
            $nref = $select->query()->fetch()->text_nref;

            if ( is_numeric($nref) && $nref > 0 ) {
                //already existing, update
                $values = array(
                    'text_nref' => new Zend_Db_Expr('text_nref+1')
                );
                Analog::log(
                    'Entry for `' . $text_orig .
                    '` dynamic translation already exists.',
                    Analog::INFO
                );

                $where = array();
                $owhere = $select->getPart(Zend_Db_Select::WHERE);
                foreach ( $owhere as $c ) {
                    $where[] = preg_replace('/^AND /', '', $c);
                }
                $zdb->db->update(
                    $l10n_table,
                    $values,
                    $where
                );
            } else {
                //add new entry
                // User is supposed to use current language as original text.
                if ( $lang->getLongID() != $i18n->getLongID() ) {
                    $text_orig = '';
                }
                $values = array(
                    'text_orig' => $text_orig,
                    'text_locale' => $lang->getLongID(),
                    'text_trans' => $text_orig
                );
                $zdb->db->insert($l10n_table, $values);
            }
        }
    } catch (Exception $e) {
        /** FIXME */
        Analog::log(
            'An error occured adding dynamic translation for `' .
            $text_orig . '` | ' . $e->getMessage(),
            Analog::ERROR
        );
        return false;
    }
}

/**
* Delete a translation stored in the database
*
* @param string $text_orig      Text to translate
* @param array  $error_detected Pointer to errors array
*
* @return void
*/
function deleteDynamicTranslation($text_orig, $error_detected)
{
    global $zdb, $i18n;
    $l10n_table = PREFIX_DB . 'l10n';

    try {
        foreach ( $i18n->getList() as $lang ) {
            $zdb->db->delete(
                $l10n_table,
                array(
                    $zdb->db->quoteInto('text_orig = ?', $text_orig),
                    $zdb->db->quoteInto('text_locale = ?', $lang->getLongID())
                )
            );
        }
        return true;
    } catch (Exception $e) {
        /** FIXME */
        Analog::log(
            'An error occured deleting dynamic translation for `' .
            $text_orig . '` (lang `' . $lang->getLongID() . '`) | ' .
            $e->getMessage(),
            Analog::ERROR
        );
        return false;
    }
}

/**
* Update a translation stored in the database
*
* @param string $text_orig      Text to translate
* @param string $text_locale    The locale
* @param string $text_trans     Translated text
* @param array  $error_detected Pointer to errors array
*
* @return translated string
*/
function updateDynamicTranslation(
    $text_orig,
    $text_locale,
    $text_trans,
    $error_detected
) {
    global $zdb;
    $l10n_table = PREFIX_DB . 'l10n';

    try {
        //check if translation already exists
        $select = new Zend_Db_Select($zdb->db);
        $select->from($l10n_table, 'text_nref')
            ->where('text_orig = ?', $text_orig)
            ->where('text_locale = ?', $text_locale);
        $nref = $select->query()->fetch()->text_nref;

        $exists = (is_numeric($nref) && $nref > 0);

        $values = array(
            'text_trans' => $text_trans
        );

        $res = false;
        if ( $exists ) {
            $where = array();
            $owhere = $select->getPart(Zend_Db_Select::WHERE);
            foreach ( $owhere as $c ) {
                $where[] = preg_replace('/^AND /', '', $c);
            }
            $res = $zdb->db->update(
                $l10n_table,
                $values,
                $where
            );
        } else {
            $values['text_orig'] = $text_orig;
            $values['text_locale'] = $text_locale;
            $res = $zdb->db->insert(
                $l10n_table,
                $values
            );
        }
        return true;
    } catch (Exception $e) {
        /** FIXME */
        Analog::log(
            'An error occured updating dynamic translation for `' .
            $text_orig . '` | ' . $e->getMessage(),
            Analog::ERROR
        );
        return false;
    }
}

/** FIXME: should be a method in L10n class */
/**
* Get a translation stored in the database
*
* @param string $text_orig   Text to translate
* @param string $text_locale The locale
*
* @return translated string
*/
function getDynamicTranslation($text_orig, $text_locale)
{
    global $zdb;
    try {
        $select = new Zend_Db_Select($zdb->db);
        $select->limit(1)->from(
            PREFIX_DB . Galette\Core\L10n::TABLE,
            'text_trans'
        )->where('text_orig = ?', $text_orig)
            ->where('text_locale = ?', $text_locale);
        $res = $select->query()->fetch();
        if ( $res !== false ) {
            return $res->text_trans;
        } else {
            return;
        }
    } catch (Exception $e) {
        /** TODO */
        Analog::log(
            'An error occured retrieving l10n entry. text_orig=' . $text_orig .
            ', text_locale=' . $text_locale . ' | ' . $e->getMessage(),
            Analog::WARNING
        );
        Analog::log(
            'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
            Analog::ERROR
        );
        return false;
    }
}

/** FIXME : $loc undefined */
if ( (isset($loc) && $loc!=$language) || $disable_gettext) {
    include GALETTE_ROOT . 'lang/lang_' . $i18n->getFileName() . '.php';
    //check if a local lang file exists and load it
    $locfile = GALETTE_ROOT . 'lang/lang_' . $i18n->getFileName() . '_local.php';
    if ( file_exists($locfile) ) {
        include $locfile;
    }
}

if ( !function_exists('_T') ) {
    /**
    * Translate a string
    *
    * @param string $chaine The string to translate
    *
    * @return Translated string (if available) ; $chaine otherwise
    */
    function _T($chaine)
    {
        global $language, $disable_gettext, $installer;
        if ( $disable_gettext === true && isset($GLOBALS['lang']) ) {
            $trans = $chaine;
            if ( isset($GLOBALS['lang'][$chaine])
                && $GLOBALS['lang'][$chaine] != ''
            ) {
                $trans = $GLOBALS['lang'][$chaine];
            } else {
                $trans = false;
                if ( !isset($installer) || $installer !== true ) {
                    $trans = getDynamicTranslation(
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
            return $trans;
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
$foo = _T("Identity");
$foo = _T("Galette-related data");
$foo = _T("Contact information");

