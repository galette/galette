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
 * @author    Frédéric Jacquot <unknown@unknwown.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.60
 */

use Galette\Entity\DynamicFields;
use Galette\Entity\Adherent;
use Galette\Entity\FieldsConfig;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Galette\Filters\MembersList;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}

$id_adh = get_numeric_form_value('id_adh', '');

if ( !$login->isSuperAdmin() ) {
    if ( !$login->isAdmin() && !$login->isStaff() && !$login->isGroupManager()
        || $login->isAdmin() && $id_adh == ''
        || $login->isStaff() && $id_adh == ''
        || $login->isGroupManager() && $id_adh == ''
    ) {
        $id_adh = $login->id;
    }
}
if ( $id_adh == '' ) {
    header('location: index.php');
    die();
}

if ( isset($session['pdf_error']) && $session['pdf_error']
) {
    $error_detected[] = $session['pdf_error_msg'];
    unset($session['pdf_error_msg']);
    unset($session['pdf_error']);
}

if ( isset($session['lostpasswd_errors']) ) {
    $error_detected = unserialize(
        $session['lostpasswd_errors']
    );
    unset($session['lostpasswd_errors']);
}

if ( isset($session['lostpasswd_success']) ) {
    $success_detected = unserialize(
        $session['lostpasswd_success']
    );
    unset($session['lostpasswd_success']);
}

$dyn_fields = new DynamicFields();

$deps = array(
    'picture'   => true,
    'groups'    => true,
    'dues'      => true,
    'parent'    => true,
    'children'  => true
);
$member = new Adherent($zdb, (int)$id_adh, $deps);

// flagging fields visibility
$fc = new FieldsConfig($zdb, Adherent::TABLE, $members_fields, $members_fields_cats);
$visibles = $fc->getVisibilities();

if ( $login->id != $id_adh && !$login->isAdmin() && !$login->isStaff() ) {
    //check if requested member is part of managed groups
    $groups = $member->groups;
    $is_managed = false;
    foreach ( $groups as $g ) {
        if ( $login->isGroupManager($g->getId()) ) {
            $is_managed = true;
            break;
        }
    }
    if ( $is_managed !== true ) {
        //requested member is not part of managed groups, fall back to logged
        //in member
        $member->load($login->id);
    }
}

$navigate = array();

if ( isset($session['filters']['members']) ) {
    $filters =  unserialize($session['filters']['members']);
} else {
    $filters = new MembersList();
}

if ( ($login->isAdmin() || $login->isStaff()) ) {
    $m = new Members($filters);
    $ids = $m->getList(false, array(Adherent::PK, 'nom_adh', 'prenom_adh'));
    $ids = $ids->toArray();
    foreach ( $ids as $k=>$m ) {
        if ( $m['id_adh'] == $member->id ) {
            $navigate = array(
                'cur'  => $m['id_adh'],
                'count' => count($ids),
                'pos' => $k+1
            );
            if ( $k > 0 ) {
                $navigate['prev'] = $ids[$k-1]['id_adh'];
            }
            if ( $k < count($ids)-1 ) {
                $navigate['next'] = $ids[$k+1]['id_adh'];
            }
            break;
        }
    }
}

$children = array();
if ($member->hasChildren()) {
    foreach ($member->children as $child) {
        $children[$child] = Adherent::getSName($zdb, $child);
    }
}

// Set caller page ref for cards error reporting
$session['caller'] = 'voir_adherent.php?id_adh='.$id_adh;

// declare dynamic field values
$adherent['dyn'] = $dyn_fields->getFields('adh', $id_adh, true);

// - declare dynamic fields for display
$disabled['dyn'] = array();
$dynamic_fields = $dyn_fields->prepareForDisplay(
    'adh',
    $adherent['dyn'],
    $disabled['dyn'],
    0
);

if ( isset($error_detected) ) {
    $tpl->assign('error_detected', $error_detected);
}
$tpl->assign('page_title', _T("Member Profile"));
$tpl->assign('require_dialog', true);
$tpl->assign('member', $member);
$tpl->assign('children', $children);
$tpl->assign('data', $adherent);
$tpl->assign('navigate', $navigate);
$tpl->assign('pref_lang_img', $i18n->getFlagFromId($member->language));
$tpl->assign('pref_lang', ucfirst($i18n->getNameFromId($member->language)));
$tpl->assign('pref_card_self', $preferences->pref_card_self);
$tpl->assign('dynamic_fields', $dynamic_fields);
$tpl->assign('groups', Groups::getSimpleList());
$tpl->assign('visibles', $visibles);
$tpl->assign('time', time());

$display_elements = $fc->getDisplayElements();
$tpl->assign('display_elements', $display_elements);

//if we got a mail warning when adding/editing a member,
//we show it and delete it from session
if ( isset($session['mail_warning']) ) {
    $warning_detected[] = $session['mail_warning'];
    unset($session['mail_warning']);
}
$tpl->assign('warning_detected', $warning_detected);
if ( isset($session['account_success']) ) {
    $success_detected = unserialize($session['account_success']);
    unset($session['account_success']);
}
$tpl->assign('success_detected', $success_detected);
$content = $tpl->fetch('voir_adherent.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');

if ( isset($profiler) ) {
    $profiler->stop();
}
