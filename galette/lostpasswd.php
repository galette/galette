<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Send a new password
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
 * @author    Stéphane Salès <ssales@tuxz.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

require_once 'includes/galette.inc.php';
require_once 'classes/adherent.class.php';
require_once 'classes/galette_password.class.php';
require_once 'classes/texts.class.php';

// initialize warnings
$error_detected = array();
$warning_detected = array();

// Validation
if ( isset($_POST['valid']) && $_POST['valid'] == '1' ) {
    $login_adh = $_POST['login'];
    $adh = new Adherent($login_adh);

    if ( $adh->id != '' ) {
        //account has been found, proceed
        if ( GaletteMail::isValidEmail($adh->email) ) {
            $password = new GalettePassword();
            $res = $password->generateNewPassword($adh->id);
            if ( $res == true ) {
                $texts = new texts();
                $mtxt = $texts->getTexts('pwd', $preferences->pref_lang);

                // Replace Tokens
                $regs = array(
                    '/{CHG_PWD_URI}/',
                    '/{LOGIN}/',
                    '/{LINK_VALIDITY}/'
                );

                $link_validity = new DateTime();
                $link_validity->add(new DateInterval('PT24H'));

                $df = _T("Y-m-d H:i:s");
                $replacements = array(
                    'http://' . $_SERVER['SERVER_NAME'] .
                    dirname($_SERVER['REQUEST_URI']) .
                    '/change_passwd.php?hash=' . $password->getHash(),
                    custom_html_entity_decode($adh->login, ENT_QUOTES),
                    $link_validity->format(_T("Y-m-d H:i:s"))
                );

                $body = preg_replace(
                    $regs,
                    $replacements,
                    $mtxt->tbody
                );

                $mail = new GaletteMail();
                $mail->setSubject($mtxt->tsubject);
                $mail->setRecipients(
                    array(
                        $adh->email => $adh->sname
                    )
                );

                $mail->setMessage($body);
                $sent = $mail->send();

                if ( $sent == GaletteMail::MAIL_SENT ) {
                    $hist->add(
                        str_replace(
                            '%s',
                            $login_adh,
                            _T("Mail sent to '%s' for password recovery.")
                        )
                    );
                    $tpl->assign('password_sent', true);
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
        if ( GaletteMail::isValidEmail($login_adh) ) {
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

$tpl->assign('page_title', _T("Password recovery"));
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);

// display page
$content = $tpl->fetch('lostpasswd.tpl');
$tpl->assign('content', $content);
$tpl->display('public_page.tpl');

?>
