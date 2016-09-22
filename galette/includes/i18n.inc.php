<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n functions
 *
 * PHP version 5
 *
 * Copyright © 2003-2014 The Galette Team
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
 * @copyright 2003-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

if (!defined('GALETTE_ROOT')) {
       die("Sorry. You can't access directly to this file");
}

use Analog\Analog;
use Zend\Db\Sql\Expression;
use Galette\Core\L10n;

$disable_gettext = true;

$language = $i18n->getLongID();
$short_language = $i18n->getAbbrev();

setlocale(LC_ALL, $language, $i18n->getAlternate());

// if (function_exists('putenv')) putenv() can exist, but doesn't work ...
if (@putenv("LANG=$language")
    or @putenv("LANGUAGE=$language")
    or @putenv("LC_ALL=$language")
) {
    $textdomain = dirname(__FILE__) . '/../lang';
    //main translation domain
    $domain = 'galette';
    bindtextdomain($domain, $textdomain);
    //routes translation domain
    $routes_domain = 'routes';
    bindtextdomain($routes_domain, $textdomain);
    //set default translation domain and encoding
    textdomain($domain);
    bind_textdomain_codeset($domain, 'UTF-8');
    bind_textdomain_codeset($routes_domain, 'UTF-8');
} else {
    $loc='';
}

/**
 * Add a translation stored in the database
 *
 * @param string $text_orig Text to translate
 *
 * @return void
 */
function addDynamicTranslation($text_orig)
{
    global $zdb, $i18n;

    try {
        foreach ($i18n->getList() as $lang) {
            //check if translation already exists
            $select = $zdb->select(L10n::TABLE);
            $select->columns(array('text_nref'))
                ->where(
                    array(
                        'text_orig'     => $text_orig,
                        'text_locale'   => $lang->getLongID()
                    )
                );

            $results = $zdb->execute($select);
            $result = $results->current();
            $nref = 0;
            if ($result) {
                $nref = $result->text_nref;
            }

            if (is_numeric($nref) && $nref > 0) {
                //already existing, update
                $values = array(
                    'text_nref' => new Expression('text_nref+1')
                );
                Analog::log(
                    'Entry for `' . $text_orig .
                    '` dynamic translation already exists.',
                    Analog::INFO
                );

                $where = array();
                $owhere = $select->where;

                $update = $zdb->update(L10n::TABLE);
                $update->set($values)->where($owhere);
                $zdb->execute($update);
            } else {
                //add new entry
                // User is supposed to use current language as original text.
                $text_trans = $text_orig;
                if ($lang->getLongID() != $i18n->getLongID()) {
                    $text_trans = '';
                }
                $values = array(
                    'text_orig' => $text_orig,
                    'text_locale' => $lang->getLongID(),
                    'text_trans' => $text_trans
                );

                $insert = $zdb->insert(L10n::TABLE);
                $insert->values($values);
                $zdb->execute($insert);
            }
        }
    } catch (Exception $e) {
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
 * @param string $text_orig Text to translate
 *
 * @return void
 */
function deleteDynamicTranslation($text_orig)
{
    global $zdb, $i18n;

    try {
        $delete = $zdb->delete(L10n::TABLE);
        $delete->where(
            array(
                'text_orig'     => $text_orig,
                'text_locale'   => ':lang_id'
            )
        );
        $stmt = $zdb->sql->prepareStatementForSqlObject($delete);

        foreach ($i18n->getList() as $lang) {
            $stmt->execute(
                array(
                    'where2' => $lang->getLongID()
                )
            );
        }
        return true;
    } catch (Exception $e) {
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
 * @param string $text_orig   Text to translate
 * @param string $text_locale The locale
 * @param string $text_trans  Translated text
 *
 * @return translated string
 */
function updateDynamicTranslation($text_orig, $text_locale, $text_trans)
{
    global $zdb;

    try {
        //check if translation already exists
        $select = $zdb->select(L10n::TABLE);
        $select->columns(array('text_nref'))->where(
            array(
                'text_orig'     => $text_orig,
                'text_locale'   => $text_locale
            )
        );

        $results = $zdb->execute($select);
        $result = $results->current();

        $exists = false;
        if ($result) {
            $nref = $result->text_nref;
            $exists = (is_numeric($nref) && $nref > 0);
        }

        $values = array(
            'text_trans' => $text_trans
        );

        $res = false;
        if ($exists) {
            $where = array();
            $owhere = $select->where;

            $update = $zdb->update(L10n::TABLE);
            $update->set($values)->where($owhere);
            $zdb->execute($update);
        } else {
            $values['text_orig'] = $text_orig;
            $values['text_locale'] = $text_locale;

            $insert = $zdb->insert(L10n::TABLE);
            $insert->values($values);
            $zdb->execute($insert);
        }
        return true;
    } catch (Exception $e) {
        Analog::log(
            'An error occured updating dynamic translation for `' .
            $text_orig . '` | ' . $e->getMessage(),
            Analog::ERROR
        );
        return false;
    }
}

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
        $select = $zdb->select(L10n::TABLE);
        $select->limit(1)->columns(
            array('text_trans')
        )->where(
            array(
                'text_orig'     => $text_orig,
                'text_locale'   => $text_locale
            )
        );
        $results = $zdb->execute($select);
        if ($results->count() > 0) {
            $res = $results->current();
            return $res->text_trans;
        } else {
            return;
        }
    } catch (Exception $e) {
        Analog::log(
            'An error occured retrieving l10n entry. text_orig=' . $text_orig .
            ', text_locale=' . $text_locale . ' | ' . $e->getMessage(),
            Analog::WARNING
        );
        return false;
    }
}

/** FIXME : $loc undefined */
if ((isset($loc) && $loc!=$language) || $disable_gettext) {
    $domains = ['galette', 'routes'];
    foreach ($domains as $domain) {
        include GALETTE_ROOT . 'lang/' . $domain . '_' . $i18n->getLongID() . '.php';
        //check if a local lang file exists and load it
        $locfile = GALETTE_ROOT . 'lang/' . $domain . '_' . $i18n->getLongID() . '_local.php';
        if (file_exists($locfile)) {
            include $locfile;
        }
    }
}

/**
 * Translate a string
 *
 * @param string  $string The string to translate
 * @param string  $domain Translation domain. Default to false (will take default domain)
 * @param boolean $nt     Indicate not translated strings; defaults to true
 *
 * @return Translated string (if available) ; $chaine otherwise
 */
function _T($string, $domain = 'galette', $nt = true)
{
    global $language, $disable_gettext, $installer;
    if ($disable_gettext === true && isset($GLOBALS['lang'])) {
        if (isset($GLOBALS['lang'][$domain][$string])
            && $GLOBALS['lang'][$domain][$string] != ''
        ) {
            $trans = $GLOBALS['lang'][$domain][$string];
        } else {
            $trans = false;
            if (!isset($installer) || $installer !== true) {
                $trans = getDynamicTranslation(
                    $string,
                    $language
                );
            }
            if ($trans) {
                $GLOBALS['lang'][$domain][$string] = $trans;
            } else {
                $trans = $string;
                if ($nt === true) {
                    $trans .= ' (not translated)';
                }
            }
        }
        return $trans;
    } else {
        if ($domain === null) {
            return _($chaine);
        } else {
            return dgettext($string, $domain);
        }
    }
}

/**
 * Translate a string, without displaying not translated
 *
 * @param string $string The string to translate
 * @param string $domain Translation domain. Default to false (will take default domain)
 *
 * @return Translated string (if available), verbatim string otherwise
 */
function __($string, $domain = 'galette')
{
    return _T($string, $domain, false);
}

/**********************************************
* some constant strings found in the database *
**********************************************/
/** TODO: these string should be not be handled here */
$foo = _T("Realization:");
$foo = _T("Graphics:");
$foo = _T("Publisher:");
$foo = _T("President");
$foo = _T("Vice-president");
$foo = _T("Treasurer");
$foo = _T("Vice-treasurer");
$foo = _T("Secretary");
$foo = _T("Vice-secretary");
$foo = _T("Active member");
$foo = _T("Benefactor member");
$foo = _T("Founder member");
$foo = _T("Old-timer");
$foo = _T("Legal entity");
$foo = _T("Non-member");
$foo = _T("Reduced annual contribution");
$foo = _T("Company cotisation");
$foo = _T("Donation in kind");
$foo = _T("Donation in money");
$foo = _T("Partnership");
$foo = _T("french");
$foo = _T("english");
$foo = _T("spanish");
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
