<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Send a new password
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
 * @author    Stéphane Salès <ssales@tuxz.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

require_once 'includes/galette.inc.php';

$from_admin = false;
if ( (($login->isAdmin() || $login->isStaff()) && isset($_GET['id_adh'])) ) {
    $from_admin = true;
}

use Galette\Core;
use Galette\Entity\Adherent;
use Galette\Entity\Texts;

if ( ($login->isLogged()
    || $preferences->pref_mail_method == Core\GaletteMail::METHOD_DISABLED)
    && !$from_admin
) {
    header('location: index.php');
    die();
}

// Validation
if ( isset($_POST['valid']) && $_POST['valid'] == '1'
    || $from_admin
) {
    $adh = null;
    $login_adh = null;
    if ( ($login->isAdmin() || $login->isStaff()) && isset($_GET['id_adh']) ) {
        $adh = new Adherent((int)$_GET['id_adh']);
        $login_adh = $adh->login;
    } else {
        $login_adh = $_POST['login'];
        $adh = new Adherent($login_adh);
    }

    if ( $adh->id != '' ) {
        //account has been found, proceed
        if ( Core\GaletteMail::isValidEmail($adh->email) ) {
            $password = new Core\Password();
            $res = $password->generateNewPassword($adh->id);
            if ( $res == true ) {
                $link_validity = new DateTime();
                $link_validity->add(new DateInterval('PT24H'));

                $df = _T("Y-m-d H:i:s");
                $proto = 'http';
                if ( isset($_SERVER['HTTPS']) ) {
                    $proto = 'https';
                }
                $texts = new Texts(
                    $texts_fields,
                    $preferences,
                    array(
                        'change_pass_uri'   => $proto . '://' . $_SERVER['SERVER_NAME'] .
                                              dirname($_SERVER['REQUEST_URI']) .
                                              '/change_passwd.php?hash=' . urlencode($password->getHash()),
                        'link_validity'     => $link_validity->format(_T("Y-m-d H:i:s")),
                        'login_adh'         => custom_html_entity_decode($adh->login, ENT_QUOTES)
                    )
                );
                $mtxt = $texts->getTexts('pwd', $adh->language);

                $mail = new Core\GaletteMail();
                $mail->setSubject($texts->getSubject());
                $mail->setRecipients(
                    array(
                        $adh->email => $adh->sname
                    )
                );

                $mail->setMessage($texts->getBody());
                $sent = $mail->send();

                if ( $sent == Core\GaletteMail::MAIL_SENT ) {
                    $hist->add(
                        str_replace(
                            '%s',
                            $login_adh,
                            _T("Mail sent to '%s' for password recovery.")
                        )
                    );
                    if ( $from_admin === false ) {
                        $success_detected[] = _T("A mail has been sent to your adress.<br/>Please check your inbox and follow the instructions.");
                        $tpl->assign('success_detected', $success_detected);
                    } else {
                        $success_detected[] = _T("An mail has been sent to the member.");
                    }
                } else {
                    $str = str_replace(
                        '%s',
                        $login_adh,
                        _T("A problem happened while sending password for account '%s'")
                    );
                    $hist->add($str);
                    $error_detected[] = $str;
                }
            } else {
                $str = str_replace(
                    '%s',
                    $login_adh,
                    _T("An error occured storing temporary password for %s. Please inform an admin.")
                );
                $hist->add($str);
                $error_detected[] = $str;
            }
        } else {
            $str = str_replace(
                '%s',
                $login_adh,
                _T("Your account (%s) do not contain any valid mail adress")
            );
            $hist->add($str);
            $error_detected[] = $str;
        }
    } else {
        //account has not been found
        if ( Core\GaletteMail::isValidEmail($login_adh) ) {
            $str = str_replace(
                '%s',
                $login_adh,
                _T("Mails adress %s does not exist")
            );
            $hist->add($str);
            $error_detected[] = $str;
        } else {
            $str = str_replace(
                '%s',
                $login_adh,
                _T("Login %s does not exist")
            );
            $hist->add($str);
            $error_detected[] = $str;
        }
    }
}

if ( $from_admin ) {
    if ( count($error_detected) > 0 ) {
        $session['lostpasswd_errors'] = serialize($error_detected);
    }
    if ( count($success_detected) > 0 ) {
        $session['lostpasswd_success'] = serialize($success_detected);
    }

    if ( isset($profiler) ) {
        $profiler->stop();
    }

    header('location: voir_adherent.php?id_adh=' . $adh->id);
    die();
} else {
    $tpl->assign('page_title', _T("Password recovery"));
    $tpl->assign('error_detected', $error_detected);
    $tpl->assign('warning_detected', $warning_detected);

    // display page
    $content = $tpl->fetch('lostpasswd.tpl');
    $tpl->assign('content', $content);
    $tpl->display('public_page.tpl');

    if ( isset($profiler) ) {
        $profiler->stop();
    }
}
