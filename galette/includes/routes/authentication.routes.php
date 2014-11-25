<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Authentication related routes
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Core\GaletteMail;

//login page
$app->get(
    '/login',
    function () use ($app, $login, $baseRedirect, &$session) {
        //store redirect path if any
        if ($app->request()->get('r')
            && $app->request()->get('r') != '/logout'
            && $app->request()->get('r') != '/login'
        ) {
            $session['urlRedirect'] = $app->request()->get('r');
        }

        if ( !$login->isLogged() ) {
            // display page
            $app->render(
                'index.tpl',
                array(
                    'page_title'    => _T("Login"),
                )
            );
        } else {
            $baseRedirect($app);
        }
    }
)->name('login');

//Authentication procedure
$app->post(
    '/login',
    function () use ($app, &$session, $hist, $preferences, $login, $baseRedirect) {
        $nick = $app->request()->post('login');
        $password = $app->request()->post('password');

        if ( trim($nick) == '' || trim($password) == '' ) {
            $app->flash(
                'loginfault',
                _T("You must provide both login and password.")
            );
            $app->redirect($app->urlFor('login'));
        }

        if ( $nick === $preferences->pref_admin_login ) {
            $pw_superadmin = password_verify(
                $password,
                $preferences->pref_admin_pass
            );
            if ( !$pw_superadmin ) {
                $pw_superadmin = (
                    md5($password) === $preferences->pref_admin_pass
                );
            }
            if ( $pw_superadmin ) {
                $login->logAdmin($nick);
            }
        } else {
            $login->logIn($nick, $password);
        }

        if ( $login->isLogged() ) {
            $session['login'] = serialize($login);
            $hist->add(_T("Login"));
            $baseRedirect($app);
        } else {
            $app->flash('loginfault', _T("Login failed."));
            $hist->add(_T("Authentication failed"), $nick);
            $app->redirect($app->urlFor('login'));
        }
    }
)->name('dologin');

//logout procedure
$app->get(
    '/logout',
    function () use ($app, $login, &$session) {
        $login->logOut();
        $session['login'] = null;
        unset($session['login']);
        $session['history'] = null;
        unset($session['history']);
        $app->redirect($app->urlFor('slash'));
    }
)->name('logout');

//password lost page
$app->get(
    '/password-lost',
    function () use ($app) {
        $app->render(
            'lostpasswd.tpl',
            array(
                'page_title' => _T("Password recovery")
            )
        );
    }
)->name('password-lost');

//retrieve password procedure
$app->post(
    '/retrieve-pass',
    function () use ($app, $login, $preferences) {
        if ( ($login->isLogged()
            || $preferences->pref_mail_method == GaletteMail::METHOD_DISABLED)
            && !$from_admin
        ) {
            $app->redirect($app->urlFor('slash'));
        }
        $app->redirect($app->urlFor('slash'));
    }
)->name('retrieve-pass');

