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
use Galette\Core\Password;
use Galette\Entity\Adherent;
use Galette\Entity\Texts;

//login page
$app->get(
    __('/login', 'routes'),
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
    __('/login', 'routes'),
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
    __('/logout', 'routes'),
    function ($request, $response) {
        $this->login->logOut();
        $this->history->add(_("Log off"));
        \RKA\Session::destroy();
        //FIXME: should not be required on 0.9x; kept for transition
        unset($_SESSION['galette'][$this->session_name]);
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('slash'));
    }
)->setName('logout');

//password lost page
$app->get(
    __('/password-lost', 'routes'),
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
$app->map(
    ['GET', 'POST'],
    __('/retrieve-pass', 'routes') . '[/{' . Adherent::PK . ':\d+}]',
    function ($request, $response, $args) {
        $from_admin = false;
        $redirect_url = $this->router->pathFor('slash');
        if ((($this->login->isAdmin() || $this->login->isStaff()) && isset($args[Adherent::PK]))) {
            $from_admin = true;
            $redirect_url = $this->router->pathFor('member', ['id' => $args[Adherent::PK]]);
        }

        if (($this->login->isLogged()
            || $this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED)
            && !$from_admin
        ) {
            if ($this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("Email sent is disabled in the preferences. Ask galette admin")
                );
            }
            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        }

        $adh = null;
        $login_adh = null;
        if (($this->login->isAdmin() || $this->login->isStaff()) && isset($args[Adherent::PK])) {
            $adh = new Adherent($this->zdb, (int)$args[Adherent::PK]);
            $login_adh = $adh->login;
        } else {
            $post = $request->getParsedBody();
            $login_adh = $post['login'];
            $adh = new Adherent($this->zdb, $login_adh);
        }

        if ($adh->id != '') {
            //account has been found, proceed
            if (GaletteMail::isValidEmail($adh->email)) {
                $password = new Password($this->zdb);
                $res = $password->generateNewPassword($adh->id);
                if ($res == true) {
                    $link_validity = new DateTime();
                    $link_validity->add(new DateInterval('PT24H'));

                    $df = _T("Y-m-d H:i:s");

                    $texts = new Texts(
                        $this->texts_fields,
                        $this->preferences,
                        $this->router,
                        array(
                            'change_pass_uri'   => $this->preferences->getURL() .
                                                    $this->router->pathFor(
                                                        'password-recovery',
                                                        ['hash' => base64_encode($password->getHash())]
                                                    ),
                            'link_validity'     => $link_validity->format(_T("Y-m-d H:i:s")),
                            'login_adh'         => custom_html_entity_decode($adh->login, ENT_QUOTES)
                        )
                    );
                    $mtxt = $texts->getTexts('pwd', $adh->language);

                    $mail = new GaletteMail();
                    $mail->setSubject($texts->getSubject());
                    $mail->setRecipients(
                        array(
                            $adh->email => $adh->sname
                        )
                    );

                    $mail->setMessage($texts->getBody());
                    $sent = $mail->send();

                    if ($sent == GaletteMail::MAIL_SENT) {
                        $this->history->add(
                            str_replace(
                                '%s',
                                $login_adh,
                                _T("Mail sent to '%s' for password recovery.")
                            )
                        );
                        if ($from_admin === false) {
                            $message = _T("A mail has been sent to your address.<br/>Please check your inbox and follow the instructions.");
                            $done = true;
                        } else {
                            $message = _T("An mail has been sent to the member.");
                        }

                        $this->flash->addMessage(
                            'success_detected',
                            $message
                        );
                    } else {
                        $str = str_replace(
                            '%s',
                            $login_adh,
                            _T("A problem happened while sending password for account '%s'")
                        );
                        $this->history->add($str);
                        $this->flash->addMessage(
                            'error_detected',
                            $str
                        );

                        $error_detected[] = $str;
                    }
                } else {
                    $str = str_replace(
                        '%s',
                        $login_adh,
                        _T("An error occured storing temporary password for %s. Please inform an admin.")
                    );
                    $this->history->add($str);
                    $this->flash->addMessage(
                        'error_detected',
                        $str
                    );
                }
            } else {
                $str = str_replace(
                    '%s',
                    $login_adh,
                    _T("Your account (%s) do not contain any valid mail address")
                );
                $this->history->add($str);
                $this->flash->addMessage(
                    'error_detected',
                    $str
                );
            }
        } else {
            //account has not been found
            if (GaletteMail::isValidEmail($login_adh)) {
                $str = str_replace(
                    '%s',
                    $login_adh,
                    _T("Mails address %s does not exist")
                );
            } else {
                $str = str_replace(
                    '%s',
                    $login_adh,
                    _T("Login %s does not exist")
                );
            }

            $this->history->add($str);
            $this->flash->addMessage(
                'error_detected',
                $str
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }
)->setName('retrieve-pass');

//password recovery page
$app->get(
    __('/password-recovery', 'routes') . '/{hash}',
    function ($request, $response, $args) {
        $password = new Password($this->zdb);
        if (!$id_adh = $password->isHashValid(base64_decode($args['hash']))) {
            $this->flash->addMessage(
                'warning_detected',
                _T("This link is no longer valid. You should ask to retrieve your password again.")
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor('password-lost')
                );
        }

        // display page
        $this->view->render(
            $response,
            'change_passwd.tpl',
            array(
                'hash'          => $args['hash'],
                'page_title'    => _T("Password recovery")
            )
        );
        return $response;
    }
)->setName('password-recovery');

//password recovery page
$app->post(
    __('/password-recovery', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        $password = new Password($this->zdb);
        $hash_ok = true;

        if (!$id_adh = $password->isHashValid(base64_decode($post['hash']))) {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->router->pathFor('password-recovery', ['hash' => $post['hash']])
                );
        }

        $error = null;
        if ($post['mdp_adh'] == '') {
            $error = _T("No password");
        } elseif (isset($post['mdp_adh2'])) {
            if (strcmp($post['mdp_adh'], $post['mdp_adh2'])) {
                $error = _T("- The passwords don't match!");
            } else {
                if (strlen($post['mdp_adh']) < 4) {
                    $error = _T("- The password must be of at least 4 characters!");
                } else {
                    $res = Galette\Entity\Adherent::updatePassword(
                        $this->zdb,
                        $id_adh,
                        $post['mdp_adh']
                    );
                    if ($res !== true) {
                        $error = _T("An error occured while updating your password.");
                    } else {
                        $this->history->add(
                            str_replace(
                                '%s',
                                $id_adh,
                                _T("Password changed for member '%s'.")
                            )
                        );
                        //once password has been changed, we can remove the
                        //temporary password entry
                        $password->removeHash(base64_decode($post['hash']));
                        $password_updated = true;
                        $this->flash->addMessage(
                            'success_detected',
                            _T("Your password has been changed!")
                        );
                        return $response
                            ->withStatus(301)
                            ->withHeader(
                                'Location',
                                $this->router->pathFor('slash')
                            );
                    }
                }
            }
        }

        if ($error !== null) {
            $this->flash->addMessage(
                'error_detected',
                $error
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->router->pathFor('password-recovery', ['hash' => $post['hash']])
            );
    }
)->setName('do-password-recovery');
