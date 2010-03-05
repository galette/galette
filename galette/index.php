<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main page and login
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
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

/** @ignore */
require_once 'includes/galette.inc.php';
//fo default, there is no login error
$loginfault = false;

if ( isset($_GET['logout']) ) {
    $login->logOut();
    $_SESSION['galette']['login'] = null;
    unset($_SESSION['galette']['login']);
    $_SESSION['galette']['history'] = null;
    unset($_SESSION['galette']['history']);
}

// Authentication procedure
if (isset($_POST["ident"])) {
    if ( $_POST['login'] == $preferences->pref_admin_login
        && md5($_POST['password']) == $preferences->pref_admin_pass
    ) {
        $login->logAdmin($_POST['login']);
        $_SESSION['galette']['login'] = serialize($login);
        //for pre 0.7 compat while under devel
        $_SESSION['logged_status'] = 1;
        $_SESSION['logged_username'] = $_POST['login'];
        //end backward compat
        $hist->add('Login');
        header('location: gestion_adherents.php');
    } else {
        $login->logIn($_POST['login'], md5($_POST['password']));

        if ( $login->isLogged() ) {
            $_SESSION['galette']['login'] = serialize($login);
            //for pre 0.7 compat while under devel
            /** FIXME: be sure that session parameters are no
            longer in use before deleting */
            $_SESSION['logged_status'] = 1;
            $pref_lang = $login->lang;
            setcookie('pref_lang', $pref_lang);
            //end backward compat
            $hist->add('Login');
            /** FIXME: users should no try to go to admin interface */
            header('location: gestion_adherents.php');
        } else {
            $loginfault = true;
            $hist->add('Authentication failed', $_POST['login']);
        }
    }
}

if ( !$login->isLogged() ) {
    // display page
    $tpl->assign('loginfault', $loginfault);
    $tpl->display("index.tpl");
} else {
    if ( $login->isAdmin() ) {
        header('location: gestion_adherents.php');
    } else {
        header('location: voir_adherent.php');
    }
}
?>
