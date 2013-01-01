<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password change
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
 * @author    Stéphane Salès <ssales@tuxz.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2005-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

require_once 'includes/galette.inc.php';

// initialize warnings
$hash = '';
$password_updated = false;
$password = new Galette\Core\Password();

//TODO need to sanityze superglobals, see sanityze_superglobals_arrays
// get hash id, $_GET if passed by url, $_POST if passed by this form
if ( isset($_GET['hash']) && !empty($_GET['hash']) ) {
    $hash = $_GET['hash'];
} else {
    if ( isset($_POST['hash']) && !empty($_POST['hash']) ) {
        $hash=$_POST['hash'];
    }
}
if ( isset($hash) && !empty($hash) ) {
    if ( $id_adh = $password->isHashValid($hash) ) {
        // Validation
        if ( isset($_POST['valid']) && $_POST['valid'] == '1') {
            if ( $_POST['mdp_adh'] == '') {
                $error_detected[] = _T("No password");
            } else if ( isset($_POST['mdp_adh2']) ) {
                if ( strcmp($_POST['mdp_adh'], $_POST['mdp_adh2']) ) {
                    $error_detected[] = _T("- The passwords don't match!");
                } else {
                    if ( strlen($_POST['mdp_adh']) < 4 ) {
                        $error_detected[] = _T("- The password must be of at least 4 characters!");
                    } else {
                        $res = Galette\Entity\Adherent::updatePassword(
                            $id_adh,
                            $_POST['mdp_adh']
                        );
                        if ( $res !== true ) {
                            $error_detected[] = _T("An error occured while updating your password.");
                        } else {
                            $hist->add(
                                str_replace(
                                    '%s',
                                    $id_adh,
                                    _T("Password changed for member '%s'.")
                                )
                            );
                            //once password has been changed, we can remove the
                            //temporary password entry
                            $password->removeHash($hash);
                            $password_updated = true;
                        }
                    }
                }
            }
        }
    } else {
        $warning_detected = _T("This link is no longer valid. You should <a href='lostpasswd.php'>ask to retrieve your password</a> again.");
    }
} else {
    header('location: index.php');
    die();
}

$tpl->assign('page_title', _T("Password recovery"));
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$tpl->assign('password_updated', $password_updated);
$tpl->assign('hash', $hash);

// display page
$content = $tpl->fetch('change_passwd.tpl');
$tpl->assign('content', $content);
$tpl->display('public_page.tpl');
