<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Labels translation
 *
 * PHP version 5
 *
 * Copyright © 2004-2010 The Galette Team
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
 * @copyright 2006-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() ) {
    header('location: voir_adherent.php');
    die();
}

$error_detected = array();

$text_orig = get_form_value('text_orig', '');
if ( isset($_POST['trans']) && isset($text_orig) ) {
    // Validate form
    while ( list($key, $value) = each($_POST) ) {
        if ( substr($key, 0, 11) == 'text_trans_' ) {
            $trans_lang = substr($key, 11);
            updateDynamicTranslation(
                $DB,
                $text_orig,
                $trans_lang,
                $value,
                $error_detected
            );
        }
    }
}

$form_title = '';
if ( !isset($all_forms) ) {
    $all_forms='';
}
$tpl->assign('all_forms', $all_forms);

$l10n_table = PREFIX_DB . 'l10n';
$nb_fields = $DB->GetOne('SELECT COUNT(text_orig) FROM ' . $l10n_table);

if ( is_numeric($nb_fields) && $nb_fields > 0 ) {
    $all_texts = db_get_all(
        $DB,
        'SELECT DISTINCT(text_orig) FROM ' . $l10n_table . ' ORDER BY text_orig',
        $error_detected
    );
    $orig = array();
    foreach ( $all_texts as $idx => $row ) {
        $orig[] = $row['text_orig'];
    }
    if ( $text_orig == '' ) {
        $text_orig = $orig[0];
    }

    $trans = array();
    foreach ( $i18n->getList() as $l ) {
        $text_trans = getDynamicTranslation($DB, $text_orig, $l->getLongID());
        $lang_name = _T($l->getName());
        $trans[] = array(
            'key'  => $l->getID(),
            'name' => $lang_name,
            'text' => $text_trans
        );
    }

    $tpl->assign('orig', $orig);
    $tpl->assign('trans', $trans);
}
$tpl->assign('text_orig', $text_orig);
$tpl->assign('error_detected', $error_detected);
$content = $tpl->fetch('traduire_libelles.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');

?>
