<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Labels translation
 *
 * PHP version 5
 *
 * Copyright © 2004-2013 The Galette Team
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
 * @author    Laurent Pelecq <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
} elseif ( !$login->isAdmin() ) {
    header('location: gestion_adherents.php');
    die();
}

$text_orig = get_form_value('text_orig', '');
if ( isset($_POST['trans']) && isset($text_orig) ) {
    if ( isset($_POST['new']) && $_POST['new'] == 'true' ) {
        //create translation if it does not exists yet
        $res = addDynamicTranslation(
            $_POST['text_orig'],
            $error_detected
        );
    }

    // Validate form
    while ( list($key, $value) = each($_POST) ) {
        if ( substr($key, 0, 11) == 'text_trans_' ) {
            $trans_lang = substr($key, 11);
            $trans_lang = str_replace('_utf8', '.utf8', $trans_lang);
            $res = updateDynamicTranslation(
                $text_orig,
                $trans_lang,
                $value,
                $error_detected
            );
            if ( $res !== true ) {
                $error_detected[] = preg_replace(
                    array(
                        '/%label/',
                        '/%lang/'
                    ),
                    array(
                        $text_orig,
                        $trans_lang
                    ),
                    _T("An error occured saving label `%label` for language `%lang`")
                );
            }
        }
    }
    if ( count($error_detected) == 0 ) {
        $success_detected[] = _T("Labels has been sucessfully translated!");
    }
}

$form_title = '';
if ( !isset($all_forms) ) {
    $all_forms='';
}
$tpl->assign('all_forms', $all_forms);

$nb_fields = 0;
try {
    $select = new Zend_Db_Select($zdb->db);
    $select->from(
        PREFIX_DB . Galette\Core\L10n::TABLE,
        array('nb' => 'COUNT(text_orig)')
    );
    $nb_fields = $select->query()->fetch()->nb;
} catch (Exception $e) {
    /** TODO */
    Analog::log(
        'An error occured counting l10n entries | ' .
        $e->getMessage(),
        Analog::WARNING
    );
    Analog::log(
        'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
        Analog::ERROR
    );
}

if ( is_numeric($nb_fields) && $nb_fields > 0 ) {
    try {
        $select = new Zend_Db_Select($zdb->db);
        $select->distinct()->from(
            PREFIX_DB . Galette\Core\L10n::TABLE,
            'text_orig'
        )->order('text_orig');

        $all_texts = $select->query()->fetchAll();

        $orig = array();
        foreach ( $all_texts as $idx => $row ) {
            $orig[] = $row->text_orig;
        }
        $exists = true;
        if ( $text_orig == '' ) {
            $text_orig = $orig[0];
        } else if ( !in_array($text_orig, $orig) ) {
            $exists = false;
            $error_detected[] = str_replace(
                '%s',
                $text_orig,
                _T("No translation for '%s'!<br/>Please fill and submit above form to create it.")
            );
        }

        $trans = array();
        /**
         * FIXME : it would be faster to get all translations at once
         * for a specific string
         */
        foreach ( $i18n->getList() as $l ) {
            $text_trans = getDynamicTranslation($text_orig, $l->getLongID());
            $lang_name = $l->getName();
            $trans[] = array(
                'key'  => $l->getLongID(),
                'name' => ucwords($lang_name),
                'text' => $text_trans
            );
        }

        $tpl->assign('exists', $exists);
        $tpl->assign('orig', $orig);
        $tpl->assign('trans', $trans);
    } catch (Exception $e) {
        /** TODO */
        Analog::log(
            'An error occured retrieving l10n entries | ' .
            $e->getMessage(),
            Analog::WARNING
        );
        Analog::log(
            'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
            Analog::ERROR
        );
    }
}
$tpl->assign('page_title', _T("Translate labels"));
$tpl->assign('text_orig', $text_orig);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('success_detected', $success_detected);
$content = $tpl->fetch('traduire_libelles.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');

if ( isset($profiler) ) {
    $profiler->stop();
}
