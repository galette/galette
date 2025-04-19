<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Analog\Analog;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\Login;
use Galette\Core\Password;
use Galette\Core\GaletteMail;
use Galette\Entity\Adherent;
use Galette\Entity\Texts;
use Galette\Util\Release;

/**
 * Galette authentication controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class AuthController extends AbstractController
{
    /**
     * Log in
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?string  $r        Redirect after login
     *
     * @return Response
     */
    public function login(Request $request, Response $response, ?string $r = null): Response
    {
        //store redirect path if any
        if (
            $r !== null
            && $r != '/logout'
            && $r != '/login'
        ) {
            $this->session->urlRedirect = $r;
        }

        if (!$this->login->isLogged()) {
            // display page
            $this->view->render(
                $response,
                'pages/index.html.twig',
                array(
                    'page_title'    => _T("Login"),
                )
            );
            return $response;
        } else {
            return $this->galetteRedirect($request, $response);
        }
    }

    /**
     * Do login
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doLogin(Request $request, Response $response): Response
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
                ->withHeader('Location', $this->routeparser->urlFor('login'));
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
            if (
                $this->login->isSuperAdmin()
                || $this->login->isAdmin()
                || $this->login->isStaff()
            ) {
                $deprecated_constants = [
                    'NON_UTF_DBCONNECT',
                    'GALETTE_CARD_WIDTH',
                    'GALETTE_CARD_HEIGHT',
                    'GALETTE_CARD_COLS',
                    'GALETTE_CARD_ROWS'
                ];

                foreach ($deprecated_constants as $deprecated_constant) {
                    if (defined($deprecated_constant)) {
                        $this->flash->addMessage(
                            'warning',
                            sprintf(
                                'It appears you are using %1$s constant, that has been removed in current release.',
                                $deprecated_constant
                            )
                        );
                    }
                }

                //check for new release
                try {
                    $release = new Release();
                    if ($release->checkNewRelease()) {
                        Analog::log(
                            sprintf(
                                'A new Galette release is available: %s (current %s)',
                                $release->getLatestRelease(),
                                GALETTE_VERSION
                            ),
                            Analog::INFO
                        );
                        $this->flash->addMessage(
                            'info',
                            [
                                'title' => _T('A new Galette release is available.'),
                                'message' => sprintf(
                                    _T('You currently use Galette %1$s, and %2$s is available.'),
                                    GALETTE_VERSION,
                                    $release->getLatestRelease()
                                )
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    Analog::log(
                        'Error looking for new release: ' . $e->getMessage(),
                        Analog::ERROR
                    );
                }
            }

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
            return $this->galetteRedirect($request, $response);
        } else {
            $this->flash->addMessage('error_detected', _T("Login failed."));
            $this->history->add(_T("Authentication failed"), $nick);
            return $response->withStatus(301)->withHeader('Location', $this->routeparser->urlFor('login'));
        }
    }

    /**
     * Log out
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function logout(Request $request, Response $response): Response
    {
        $this->login->logOut();
        $this->history->add(_T("Log off"));
        \RKA\Session::destroy();
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('slash'));
    }

    /**
     * Impersonate
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Member to impersonate
     *
     * @return Response
     */
    public function impersonate(Request $request, Response $response, int $id): Response
    {
        $success = $this->login->impersonate($id);

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
                (string)$id,
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
            ->withHeader('Location', $this->routeparser->urlFor('slash'));
    }

    /**
     * End impersonate
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function unimpersonate(Request $request, Response $response): Response
    {
        $login = new Login($this->zdb, $this->i18n);
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
            ->withHeader('Location', $this->routeparser->urlFor('slash'));
    }

    /**
     * Lost password page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function lostPassword(Request $request, Response $response): Response
    {
        if ($this->preferences->pref_mail_method === GaletteMail::METHOD_DISABLED) {
            throw new \RuntimeException('Mailing disabled.');
        }
        // display page
        $this->view->render(
            $response,
            'pages/password_lost.html.twig',
            array(
                'page_title'    => _T("Password recovery")
            )
        );
        return $response;
    }

    /**
     * Retrieve password procedure
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?integer $id_adh   Member id
     *
     * @return Response
     */
    public function retrievePassword(Request $request, Response $response, ?int $id_adh = null): Response
    {
        $from_admin = false;
        $redirect_url = $this->routeparser->urlFor('slash');
        if (($this->login->isAdmin() || $this->login->isStaff()) && $id_adh !== null) {
            $from_admin = true;
            $redirect_url = $this->routeparser->urlFor('member', ['id' => (string)$id_adh]);
        }

        if (
            ($this->login->isLogged()
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
        if (($this->login->isAdmin() || $this->login->isStaff()) && $id_adh !== null) {
            $adh = new Adherent($this->zdb, $id_adh);
            $login_adh = $adh->login;
        } else {
            $post = $request->getParsedBody();
            $login_adh = htmlspecialchars($post['login'], ENT_QUOTES);
            $adh = new Adherent($this->zdb, $login_adh);
        }

        if ($adh->id != '') {
            //account has been found, proceed
            if (GaletteMail::isValidEmail($adh->email)) {
                $texts = new Texts($this->preferences, $this->routeparser);
                $texts
                    ->setMember($adh)
                    ->setNoContribution();

                //check if account is active
                if (!$adh->isActive()) { //https://bugs.galette.eu/issues/1529
                    $res = true;
                    $text_id = 'pwddisabled';
                } else {
                    $password = new Password($this->zdb);
                    $res = $password->generateNewPassword($adh->id);
                    $text_id = 'pwd';
                    $texts
                        ->setLinkValidity()
                        ->setChangePasswordURI($password);
                }

                if ($res === true) {
                    $texts->getTexts($text_id, $adh->language);

                    $mail = new GaletteMail($this->preferences);
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
                                _T("Email sent to '%s' for password recovery.")
                            )
                        );
                        if ($from_admin === false) {
                            $message = _T("An email has been sent to your address.<br/>Please check your inbox and follow the instructions.");
                        } else {
                            $message = _T("An email has been sent to the member.");
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
                        _T("An error occurred storing temporary password for %s. Please inform an admin.")
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
                    _T("Your account (%s) do not contain any valid email address")
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

    /**
     * Password recovery page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $hash     Hash
     *
     * @return Response
     */
    public function recoverPassword(Request $request, Response $response, string $hash): Response
    {
        $password = new Password($this->zdb);
        if (!$password->isHashValid(base64_decode($hash))) {
            $this->flash->addMessage(
                'warning_detected',
                _T("This link is no longer valid. You should ask to retrieve your password again.")
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('password-lost')
                );
        }

        // display page
        $this->view->render(
            $response,
            'pages/password_recover.html.twig',
            array(
                'hash'          => $hash,
                'page_title'    => _T("Password recovery")
            )
        );
        return $response;
    }

    /**
     * Password recovery
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doRecoverPassword(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $password = new Password($this->zdb);

        if (!$id_adh = $password->isHashValid(base64_decode($post['hash']))) {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('password-recovery', ['hash' => $post['hash']])
                );
        }

        $error = null;
        if ($post['mdp_adh'] == '') {
            $error = _T("No password");
        } elseif (isset($post['mdp_adh2'])) {
            if (strcmp($post['mdp_adh'], $post['mdp_adh2'])) {
                $error = _T("- The passwords don't match!");
            } else {
                $checkpass = new \Galette\Util\Password($this->preferences);

                if (!$checkpass->isValid($post['mdp_adh'])) {
                    //password is not valid with current rules
                    $error = _T("Your password is too weak!") .
                        '<br/> -' . implode('<br/>', $checkpass->getErrors());
                } else {
                    $res = Adherent::updatePassword(
                        $this->zdb,
                        $id_adh,
                        $post['mdp_adh']
                    );
                    if ($res !== true) {
                        $error = _T("An error occurred while updating your password.");
                    } else {
                        $this->history->add(
                            str_replace(
                                '%s',
                                (string)$id_adh,
                                _T("Password changed for member '%s'.")
                            )
                        );
                        //once password has been changed, we can remove the
                        //temporary password entry
                        $password->removeHash(base64_decode($post['hash']));
                        $this->flash->addMessage(
                            'success_detected',
                            _T("Your password has been changed!")
                        );
                        return $response
                            ->withStatus(301)
                            ->withHeader(
                                'Location',
                                $this->routeparser->urlFor('slash')
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
                $this->routeparser->urlFor('password-recovery', ['hash' => $post['hash']])
            );
    }
}
