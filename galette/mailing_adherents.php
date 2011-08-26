<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing
 *
 * PHP version 5
 *
 * Copyright © 2005-2011 The Galette Team
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
 * @copyright 2005-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() ) {
    header("location: voir_adherent.php");
    die();
}

//We're done :-)
if ( isset($_POST['mailing_done']) ) {
    $_SESSION['galette']['mailing'] = null;
    unset($_SESSION['galette']['mailing']);
    header('location: gestion_adherents.php');
}

require_once WEB_ROOT . 'classes/members.class.php';
require_once WEB_ROOT . 'classes/varslist.class.php';
require_once WEB_ROOT . 'classes/mailing.class.php';

$error_detected = array();
$warning_detected = array();
$data = array();

if ( $preferences->pref_mail_method == Mailing::METHOD_DISABLED) {
    $hist->add(_T("Trying to load mailing while mail is disabled in preferences."));
} else {
    if ( isset($_SESSION['galette']['varslist']) ) {
        $varslist = unserialize($_SESSION['galette']['varslist']);
    } else {
        $log->log(
            '[mailing_adherents.php] No member selected to generate members cards',
            PEAR_LOG_INFO
        );
        header('location:gestion_adherent.php');
    }

    $members = Members::getArrayList($varslist->selected);

    if ( isset($_SESSION['galette']['mailing']) ) {
        $mailing = unserialize($_SESSION['galette']['mailing']);
    } else {
        $mailing = new Mailing($members);
    }

    if ( isset($_POST['mailing_go'])
        || isset($_POST['mailing_reset'])
        || isset($_POST['mailing_confirm'])
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

        if ( count($error_detected) == 0 && !isset($_POST['mailing_reset']) ) {
            $mailing->current_step = Mailing::STEP_PREVIEW;
        }
    }

    if ( isset($_POST['mailing_confirm']) && count($error_detected) == 0 ) {

        $mailing->current_step = Mailing::STEP_SEND;
        //ok... let's go for fun
        /** FIXME: I guess most of the following mailing code should be handled
         * in GaletteMail so it can be reused; from self_adherent for example */
        include_once 'includes/phpMailer-' . PHP_MAILER_VERSION . '/class.phpmailer.php';
        $mail = new PHPMailer();

        if ( $preferences->pref_mail_method == Mailing::METHOD_SMTP ) {
            /** TODO: put phpMailer in php's path ? */
            $mail->PluginDir = WEB_ROOT . '/includes/phpMailer-' . PHP_MAILER_VERSION . '/';
            $mail->IsSMTP();  // telling the class to use SMTP
            $mail->Host = $preferences->pref_mail_smtp; // SMTP server
        }

        $mail->SetFrom($preferences->pref_email, $preferences->pref_email_nom);
        // Add a Reply-To field in the mail headers.
        // Fix bug #6654.
        if ( $preferences->pref_email_reply_to ) {
            $mail->AddReplyTo($preferences->pref_email_reply_to);
        } else {
            $mail->AddReplyTo($preferences->pref_email);
        }
        $mail->CharSet = 'UTF-8';
        $mail->SetLanguage($i18n->getAbbrev());

        //loop on members...
        foreach ( $mailing->recipients as $recipient ) {
            $mail->AddBCC($recipient->email, $recipient->sname);
        }

        $mail->Subject = $mailing->subject;
        $mail->Body = $mailing->message;
        if ( $mailing->html ) {
            $mail->AltBody = $mailing->alt_message;
            $mail->IsHTML(true);
        }
        $mail->WordWrap = 50;

        if ( !$mail->Send() ) {
            $log->log(
                '[mailing_adherents.php] Message was not sent. Error: ' .
                $mail->ErrorInfo,
                PEAR_LOG_ERR
            );
        } else {
            $log->log(
                '[mailing_adherents.php] Message has been sent.',
                PEAR_LOG_INFO
            );
        }
    }

    $_SESSION['galette']['mailing'] = serialize($mailing);

    /** TODO: replace that... */
    $_SESSION['galette']['labels'] = $mailing->unreachables;

    if ( !isset($_POST['html_editor_active'])
        || trim($_POST['html_editor_active']) == ''
    ) {
        $_POST['html_editor_active'] = $preferences->pref_editor_enabled;
    }

    $tpl->assign('warning_detected', $warning_detected);
    $tpl->assign('error_detected', $error_detected);
    $tpl->assign('mailing', $mailing);
    $tpl->assign('html_editor', true);
    $tpl->assign('html_editor_active', $_POST['html_editor_active']);
}
$content = $tpl->fetch('mailing_adherents.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>