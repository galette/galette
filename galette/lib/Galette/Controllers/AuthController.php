<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette authentication controller
 *
 * PHP version 5
 *
 * Copyright © 2019-2020 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

namespace Galette\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\SysInfos;
use Galette\Core\Login;
use Analog\Analog;

/**
 * Galette authentication controller
 *
 * @category  Controllers
 * @name      AuthController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

class AuthController extends AbstractController
{
    /**
     * Log in
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments ['r']
     *
     * @return void
     */
    public function login(Request $request, Response $response, array $args = [])
    {
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
            return $this->galetteRedirect($request, $response, $args);
        }
    }

    /**
     * Do login
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return void
     */
    public function doLogin(Request $request, Response $response)
    {
        $nick = $request->getParsedBody()['login'];
        $password = $request->getParsedBody()['password'];
        $checkpass = new \Galette\Util\Password($this->preferences);

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
            if (!$checkpass->isValid($password)) {
                //password is no longer valid with current rules, must be changed
                $this->flash->addMessage(
                    'warning_detected',
                    _T("Your password is too weak! Please consider updating it.") .
                    '<br/> -' . implode('<br/>', $checkpass->getErrors())
                );
            }
            $this->session->login = $this->login;
            $this->history->add(_T("Login"));
            return $this->galetteRedirect($request, $response, []);
        } else {
            $this->flash->addMessage('error_detected', _T("Login failed."));
            $this->history->add(_T("Authentication failed"), $nick);
            return $response->withStatus(301)->withHeader('Location', $this->router->pathFor('login'));
        }
    }

    /**
     * Log out
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return void
     */
    public function logout(Request $request, Response $response)
    {
        $this->login->logOut();
        $this->history->add(_T("Log off"));
        \RKA\Session::destroy();
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }

    /**
     * Impersonate
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments ['id']
     *
     * @return void
     */
    public function impersonate(Request $request, Response $response, array $args)
    {
        $success = $this->login->impersonate((int)$args['id']);

        if ($success === true) {
            $this->session->login = $this->login;
            $msg = str_replace(
                '%login',
                $this->login->login,
                _T("Impersonating as %login")
            );

            $this->history->add($msg);
            $this->flash->addMessage(
                'success_detected',
                $msg
            );
        } else {
            $msg = str_replace(
                '%id',
                $id,
                _T("Unable to impersonate as %id")
            );
            $this->flash->addMessage(
                'error_detected',
                $msg
            );
            $this->history->add($msg);
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }

    /**
     * End impersonate
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return void
     */
    public function unimpersonate(Request $request, Response $response)
    {
        $login = new Login($this->zdb, $this->i18n, $this->session);
        $login->logAdmin($this->preferences->pref_admin_login, $this->preferences);
        $this->history->add(_T("Impersonating ended"));
        $this->session->login = $login;
        $this->login = $login;
        $this->flash->addMessage(
            'success_detected',
            _T("Impersonating ended")
        );
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }
}
