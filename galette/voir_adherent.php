<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Show a member, and possibility to:
 * - change its values
 * - show its contributions
 * - add a new contribution
 * - generate PDF memger card
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
 * @author    Frédéric Jacquot <unknown@unknwown.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.60
 */

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}

$id_adh = get_numeric_form_value('id_adh', '');

if ( !$login->isAdmin() ) {
    $id_adh = $login->id;
}
if ( $id_adh == '' ) {
    header('location: index.php');
    die();
}
if (isset($_SESSION['galette']['pdf_error']) && $_SESSION['galette']['pdf_error']) {
    $error_detected[] = $_SESSION['galette']['pdf_error_msg'];
    unset($_SESSION['galette']['pdf_error_msg']);
    unset($_SESSION['galette']['pdf_error']);
}

require_once WEB_ROOT . 'classes/adherent.class.php';
require_once WEB_ROOT . 'classes/politeness.class.php';
require_once WEB_ROOT . 'includes/dynamic_fields.inc.php';

$member = new Adherent();
$member->load($id_adh);

$navigate = array();
require_once 'classes/varslist.class.php';
if ( isset($_SESSION['galette']['varslist'])  ) {
    $varslist = unserialize($_SESSION['galette']['varslist']);
    require_once 'classes/members.class.php';
    $ids = Members::getList(false, array(Adherent::PK));
    //print_r($ids);
    foreach ( $ids as $k=>$m ) {
        if ( $m->id_adh == $member->id ) {
            $navigate = array(
                'cur'  => $m->id_adh,
                'count' => count($ids),
                'pos' => $k+1
            );
            if ( $k > 0 ) {
                $navigate['prev'] = $ids[$k-1]->id_adh;
            }
            if ( $k < count($ids)-1 ) {
                $navigate['next'] = $ids[$k+1]->id_adh;
            }
            break;
        }
    }
}

// Set caller page ref for cards error reporting
$_SESSION['galette']['caller']='voir_adherent.php?id_adh='.$id_adh;

// declare dynamic field values
$adherent['dyn'] = get_dynamic_fields($DB, 'adh', $id_adh, true);

// - declare dynamic fields for display
$disabled['dyn'] = array();
$dynamic_fields = prepare_dynamic_fields_for_display(
    $DB,
    'adh',
    $adherent['dyn'],
    $disabled['dyn'],
    0
);

if ( isset($error_detected) ) {
    $tpl->assign('error_detected', $error_detected);
}
$tpl->assign('member', $member);
$tpl->assign('navigate', $navigate);
$tpl->assign('pref_lang_img', $i18n->getFlagFromId($member->language));
$tpl->assign('pref_lang', ucfirst($i18n->getNameFromId($member->language)));
$tpl->assign('pref_card_self', $preferences->pref_card_self);
$tpl->assign('dynamic_fields', $dynamic_fields);
$tpl->assign('data', $adherent);
$tpl->assign('time', time());
//if we got a mail warning when adding/editing a member,
//we show it and delete it from session
if ( isset($_SESSION['galette']['mail_warning']) ) {
    $tpl->assign('mail_warning', $_SESSION['galette']['mail_warning']);
    unset($_SESSION['galette']['mail_warning']);
}
$content = $tpl->fetch('voir_adherent.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
