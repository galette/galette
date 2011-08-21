<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add a transaction
 *
 * PHP version 5
 *
 * Copyright © 2004-2011 The Galette Team
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
 * @author    Laurent Pelecq <laurent.pelecq@soleil.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() ) {
    header('location: voir_adherent.php');
    die();
}

require_once 'classes/transaction.class.php';
require_once 'includes/dynamic_fields.inc.php';
require_once 'classes/varslist.class.php';
require_once 'classes/contributions.class.php';

$trans = new Transaction();

// new or edit
$trans_id = get_numeric_form_value("trans_id", '');
$transaction['trans_id'] = get_numeric_form_value("trans_id", '');
$transaction['trans_amount'] = get_numeric_form_value("trans_amount", '');
$transaction['trans_date'] = get_form_value("trans_date", '');
$transaction['trans_desc'] = get_form_value("trans_desc", '');
$transaction['id_adh'] = get_numeric_form_value("id_adh", '');

// initialize warning
$error_detected = array();

// flagging required fields
$required = array(
    'trans_amount'  =>  1,
    'trans_date'    =>  1,
    'trans_desc'    =>  1,
    'id_adh'        =>  1
);
$disabled = array();

if ( $trans_id != '' ) {
    // initialize transactions structure with database values
    $trans->load($trans_id);
    if ( $trans->id == '' ) {
        //not possible to load transaction, exit
        header('location: index.php');
    }
}

// Validation
$transaction['dyn'] = array();

if ( isset($_POST['valid']) ) {
    $transaction['dyn'] = extract_posted_dynamic_fields($_POST, array());

    $valid = $trans->check($_POST, $required, $disabled);
    if ( $valid === true ) {
        //all goes well, we can proceed
        $new = false;
        if ( $trans->id == '' ) {
            $new = true;
        }

        $store = $trans->store();
        if ( $store === true ) {
            //transaction has been stored :)
            if ( $new ) {
                /** FIXME: do something !! */
            }
        } else {
            //something went wrong :'(
            $error_detected[] = _T("An error occured while storing the transaction.");
        }
    } else {
        //hum... there are errors :'(
        $error_detected = $valid;
    }

    if ( count($error_detected) == 0 ) {
        // dynamic fields
        set_all_dynamic_fields(
            'trans',
            $transaction['trans_id'],
            $transaction['dyn']
        );

        if ( $trans->getMissingAmount() > 0 ) {
            $url = 'ajouter_contribution.php?trans_id='.$trans->id;
            if ( isset($trans->memebr) ) {
                $url .= '&id_adh=' . $trans->member;
            }
        } else {
            $url = 'gestion_transactions.php';
        }
        header('location: '.$url);
    }
} else { //$_POST['valid']
    if ( $trans->id != '' ) {
        // dynamic fields
        $transaction['dyn'] = get_dynamic_fields(
            'trans',
            $transaction["trans_id"],
            false
        );
    }
}

// template variable declaration
$tpl->assign('required', $required);
$tpl->assign('transaction', $trans);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('require_calendar', true);

if ( $trans->id != '' ) {
    $contribs = new Contributions();
    $tpl->assign('contribs', $contribs->getListFromTransaction($trans->id));
}

// members
$varslist = new VarsList();
$members = Members::getList();
if ( count($members) == 0 ) {
    $adh_options = array('' => _T("You must first register a member"));
} else {
    foreach ( $members as $member ) {
        $adh_options[$member->id_adh] = stripslashes(
            strtoupper($member->nom_adh) . ' ' . $member->prenom_adh
        );
    }
}

$tpl->assign('adh_options', $adh_options);

// - declare dynamic fields for display
$dynamic_fields = prepare_dynamic_fields_for_display(
    'trans', $transaction['dyn'],
    array(),
    1
);
$tpl->assign('dynamic_fields', $dynamic_fields);

// page generation
$content = $tpl->fetch("ajouter_transaction.tpl");
$tpl->assign("content", $content);
$tpl->display("page.tpl");
?>
