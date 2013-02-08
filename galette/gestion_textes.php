<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Texts management
 *
 * PHP version 5
 *
 * Copyright © 2007-2013 The Galette Team
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
 * @author    John Perr <johnperr@abul.org>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Availaible since 0.7dev - 2007-10-16
 */

use Galette\Entity\Texts as Texts;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
} elseif ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
} elseif ( !$login->isAdmin() ) {
    header('location: gestion_adherents.php');
    die();
}

$cur_lang = $preferences->pref_lang;
$cur_ref = Texts::DEFAULT_REF;

$texts = new Texts($texts_fields, $preferences);

//set the language
if ( isset($_POST['sel_lang']) ) {
    $cur_lang = $_POST['sel_lang'];
}
//set the text entry
if ( isset($_POST['sel_ref']) ) {
    $cur_ref = $_POST['sel_ref'];
}

if (isset($_POST['valid']) && $_POST['valid'] == '1') {
    //form was send normally, we try to store new values
    //load actual text for further reference
    $mtxt = $texts->getTexts($cur_ref, $cur_lang);
    $res = $texts->setTexts(
        $cur_ref,
        $cur_lang,
        $_POST['text_subject'],
        $_POST['text_body']
    );

    if ( !$res ) {
        $error_detected[] = preg_replace(
            '(%s)',
            $mtxt->tcomment,
            _T("Email: '%s' has not been modified!")
        );
    } else {
        $success_detected[] = preg_replace(
            '(%s)',
            $mtxt->tcomment,
            _T("Email: '%s' has been successfully modified.")
        );
    }
}

$tpl->assign('page_title', _T("Automatic emails texts edition"));
$mtxt = $texts->getTexts($cur_ref, $cur_lang);
$tpl->assign('reflist', $texts->getRefs($cur_lang));
$tpl->assign('langlist', $i18n->getList());
$tpl->assign('cur_lang', $cur_lang);
$tpl->assign('cur_ref', $cur_ref);
$tpl->assign('mtxt', $mtxt);
$tpl->assign('require_dialog', true);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('success_detected', $success_detected);
$content = $tpl->fetch('gestion_textes.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
