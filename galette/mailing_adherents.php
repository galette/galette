<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing
 *
 * PHP version 5
 *
 * Copyright © 2005-2013 The Galette Team
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
 * @author    Frédéric Jaqcuot <nobody@exemple.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2005-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header("location: voir_adherent.php");
    die();
}

use Galette\Core;
use Analog\Analog as Analog;
use Galette\Repository\Members;
use Galette\Filters\MembersList;

//We're done :-)
if ( isset($_POST['mailing_done'])
    || isset($_POST['mailing_cancel'])
    || isset($_GET['mailing_new'])
    || isset($_GET['reminder'])
) {
    $session['mailing'] = null;
    unset($session['mailing']);
    if ( !isset($_GET['mailing_new']) && !isset($_GET['reminder']) ) {
        header('location: gestion_adherents.php');
        exit(0);
    }
}

$data = array();

if ( $preferences->pref_mail_method == Core\Mailing::METHOD_DISABLED
    && !GALETTE_MODE === 'DEMO'
) {
    $hist->add(_T("Trying to load mailing while mail is disabled in preferences."));
} else {
    if ( isset($session['filters']['members']) ) {
        $filters =  unserialize($session['filters']['members']);
    } else {
        $filters = new MembersList();
    }

    if ( isset($session['mailing'])
        && !isset($_POST['mailing_cancel'])
        && !isset($_GET['from'])
        && !isset($_GET['reset'])
    ) {
        $mailing = unserialize($session['mailing']);
    } else if (isset($_GET['from']) && is_numeric($_GET['from'])) {
        $mailing = new Core\Mailing(null);
        Core\MailingHistory::loadFrom((int)$_GET['from'], $mailing);
    } else if (isset($_GET['reminder'])) {
        //FIXME: use a constant!
        $filters->reinit();
        $filters->membership_filter = Members::MEMBERSHIP_LATE;
        $filters->account_status_filter = Members::ACTIVE_ACCOUNT;
        $m = new Members($filters);
        $members = $m->getList(true);
        $mailing = new Core\Mailing(($members !== false) ? $members : null);
    } else {
        if ( count($filters->selected) == 0
            && !isset($_GET['mailing_new'])
            && !isset($_GET['reminder'])
        ) {
            Analog::log(
                '[mailing_adherents.php] No member selected for mailing',
                Analog::WARNING
            );

            if ( isset($profiler) ) {
                $profiler->stop();
            }

            header('location:gestion_adherents.php');
            die();
        }
        $m = new Members();
        $members = $m->getArrayList($filters->selected);
        $mailing = new Core\Mailing(($members !== false) ? $members : null);
    }

    if ( isset($_POST['mailing_go'])
        || isset($_POST['mailing_reset'])
        || isset($_POST['mailing_confirm'])
        || isset($_POST['mailing_save'])
    ) {
        if ( trim($_POST['mailing_objet']) == '' ) {
            $error_detected[] = _T("Please type an object for the message.");
        } else {
            $mailing->subject = $_POST['mailing_objet'];
        }

        if ( trim($_POST['mailing_corps']) == '') {
            $error_detected[] = _T("Please enter a message.");
        } else {
            $mailing->message = $_POST['mailing_corps'];
        }

        $mailing->html = ( isset($_POST['mailing_html']) ) ? true : false;

        if ( count($error_detected) == 0
            && !isset($_POST['mailing_reset'])
            && !isset($_POST['mailing_save'])
        ) {
            $mailing->current_step = Core\Mailing::STEP_PREVIEW;
        } else {
            $mailing->current_step = Core\Mailing::STEP_START;
        }
    }

    if ( isset($_POST['mailing_confirm']) && count($error_detected) == 0 ) {

        $mailing->current_step = Core\Mailing::STEP_SEND;
        //ok... let's go for fun
        $sent = $mailing->send();
        if ( $sent == Core\Mailing::MAIL_ERROR ) {
            $mailing->current_step = Core\Mailing::STEP_START;
            Analog::log(
                '[mailing_adherents.php] Message was not sent. Errors: ' .
                print_r($mailing->errors, true),
                Analog::ERROR
            );
            foreach ( $mailing->errors as $e ) {
                $error_detected[] = $e;
            }
        } else {
            $mlh = new Core\MailingHistory($mailing);
            $mlh->storeMailing(true);
            Analog::log(
                '[mailing_adherents.php] Message has been sent.',
                Analog::INFO
            );
            $mailing->current_step = Core\Mailing::STEP_SENT;
            //cleanup
            $filters->selected = null;
            $session['filters']['members'] = serialize($filters);
            $session['mailing'] = null;
            unset($session['mailing']);
        }
    }

    if ( $mailing->current_step !== Core\Mailing::STEP_SENT ) {
        $session['mailing'] = serialize($mailing);
    }

    /** TODO: replace that... */
    $session['labels'] = $mailing->unreachables;

    if ( !isset($_POST['html_editor_active'])
        || trim($_POST['html_editor_active']) == ''
    ) {
        $_POST['html_editor_active'] = $preferences->pref_editor_enabled;
    }

    if ( isset($_POST['mailing_save']) ) {
        //user requested to save the mailing
        $histo = new Core\MailingHistory($mailing);
        if ( $histo->storeMailing() !== false ) {
            $success_detected[] = _T("Mailing has been successfully saved.");
            $tpl->assign('mailing_saved', true);
            $session['mailing'] = null;
            unset($session['mailing']);
            $head_redirect = array(
                'timeout'   => 30,
                'url'       => 'gestion_mailings.php'
            );
            $tpl->assign('head_redirect', $head_redirect);
        }
    }

    $tpl->assign('success_detected', $success_detected);
    $tpl->assign('warning_detected', $warning_detected);
    $tpl->assign('error_detected', $error_detected);
    $tpl->assign('mailing', $mailing);
    $tpl->assign('html_editor', true);
    $tpl->assign('html_editor_active', $_POST['html_editor_active']);
}
$tpl->assign('require_dialog', true);
$tpl->assign('page_title', _T("Mailing"));
$content = $tpl->fetch('mailing_adherents.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');

if ( isset($profiler) ) {
    $profiler->stop();
}
