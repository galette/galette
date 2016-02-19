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
    function ($request, $response, $args = []) use ($baseRedirect) {
        //store redirect path if any
        if (isset($args['r'])
            && $args['r'] != '/logout'
            && $args['r'] != '/login'
        ) {
            $this->session->urlRedirect = $args['r'];
        }

        if (!$this->login->isLogged()) {
            // display page
            $this->view->render(
                $response,
                'index.tpl',
                array(
                    'page_title'    => _T("Login"),
                )
            );
            return $response;
        } else {
            return $baseRedirect($request, $response, $args);
        }
    }
)->setName('login');

//Authentication procedure
$app->post(
    '/login',
    function ($request, $response) use ($app, $baseRedirect) {
        $nick = $request->getParsedBody()['login'];
        $password = $request->getParsedBody()['password'];

        if (trim($nick) == '' || trim($password) == '') {
            $this->flash->addMessage(
                'loginfault',
                _T("You must provide both login and password.")
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('login'));
        }

        if ($nick === $this->preferences->pref_admin_login) {
            $pw_superadmin = password_verify(
                $password,
                $this->preferences->pref_admin_pass
            );
            if (!$pw_superadmin) {
                $pw_superadmin = (
                    md5($password) === $this->preferences->pref_admin_pass
                );
            }
            if ($pw_superadmin) {
                $this->login->logAdmin($nick, $this->preferences);
            }
        } else {
            $this->login->logIn($nick, $password);
        }

        if ($this->login->isLogged()) {
        	$this->session->login = $this->login;
            $this->history->add(_T("Login"));
            return $baseRedirect($request, $response, []);
        } else {
            $this->flash->addMessage('error_detected', _T("Login failed."));
            $this->history->add(_T("Authentication failed"), $nick);
            return $response->withStatus(301)->withHeader('Location', $this->router->pathFor('login'));
        }
    }
)->setName('dologin');

//logout procedure
$app->get(
    '/logout',
    function ($request, $response) use ($app, $login) {
        $this->login->logOut();
        \RKA\Session::destroy();
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }
)->setName('logout');

//password lost page
$app->get(
    '/password-lost',
    function ($request, $response) {
        // display page
        $this->view->render(
            $response,
            'lostpasswd.tpl',
            array(
                'page_title'    => _T("Password recovery")
            )
        );
        return $response;
    }
)->setName('password-lost');

//retrieve password procedure
$app->post(
    '/retrieve-pass',
    function ($request, $response) {
        if (($this->login->isLogged()
            || $this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED)
            && !$from_admin
        ) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }
        $app->redirect($app->pathFor('slash'));
    }
)->setName('retrieve-pass');
