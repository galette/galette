<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Analog\Analog;
use Galette\Controllers\Attributes\Route;
use Galette\Core\AuthThrottle;
use Galette\Core\TwoFactorAuth;
use Galette\Entity\Adherent;
use Galette\Core\TwoFactorSecret;
use Galette\Core\TwoFactorSuperAdmin;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Second authentication factor
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorController extends AbstractController
{
    /**
     * Ask for the second factor.
     *
     * Reachable without being logged in, by design: the session holds accepted
     * credentials but isLogged() is false until the factor is produced.
     */
    #[Route(
        name: 'two-factor',
        pattern: '/two-factor',
        methods: ['GET'],
        requiresAuth: false
    )]
    public function challenge(Response $response): Response
    {
        if (!$this->login->isTwoFactorPending()) {
            return $this->redirectHome($response);
        }

        $this->view->render(
            $response,
            'pages/two_factor_challenge.html.twig',
            [
                'page_title' => _T("Two-factor authentication")
            ]
        );
        return $response;
    }

    /**
     * Check the submitted second factor
     */
    #[Route(
        name: 'do-two-factor',
        pattern: '/two-factor',
        methods: ['POST'],
        requiresAuth: false
    )]
    public function doChallenge(
        Request $request,
        Response $response,
        TwoFactorAuth $tfa,
        AuthThrottle $throttle
    ): Response {
        if (!$this->login->isTwoFactorPending()) {
            return $this->redirectHome($response);
        }

        $login = (string)$this->login->login;

        //evaluated before any verification: reaching this stage means holding
        //the password, so an attacker gets no free tries at the code
        $delay = $throttle->getSecondFactorDelay($login);
        if ($delay > 0) {
            //no countdown on the page: how long is left measures what has been
            //tried, and it belongs in the log
            Analog::log(
                'Second factor throttled for `' . $login . '`, ' . $delay . ' seconds left.',
                Analog::INFO
            );
            return $this->rejected(
                $response,
                _T("Too many failed attempts. Please try again later.")
            );
        }

        $code = self::postedCode($request);

        $secret = $tfa->storeFor($this->login);
        if (!$secret->isEnabled()) {
            //nothing to check against any more: whoever removed the factor did
            //so while this session was mid-challenge. Counted and closed, or
            //this page would keep offering itself for ever.
            Analog::log(
                'No enabled second factor for ' . $secret->getOwner() . ' during challenge',
                Analog::WARNING
            );
            $throttle->recordSecondFactorFailure($login);
            $this->login->logOut();
            $this->flash->addMessage('error_detected', _T("Two-factor authentication failed."));
            return $this->redirectHome($response);
        }

        //a recovery code is accepted here too, so that losing the phone does
        //not mean losing the account. The super administrator has none.
        $accepted = $tfa->verify($secret, $code)
            || ($secret instanceof TwoFactorSecret && $secret->consumeRecoveryCode($code));

        if (!$accepted) {
            $throttle->recordSecondFactorFailure($login);
            $this->history->add(_T("Second factor failed"), $login);
            return $this->rejected($response, _T("Two-factor authentication failed."));
        }

        //the authentication is only complete now, so this is where the counters
        //of both stages are cleared
        $throttle->recordSuccess($login);
        $this->login->validateTwoFactor();
        //privileges just changed, as they do on a plain login
        \RKA\Session::regenerate();
        $this->session->login = $this->login;
        $this->history->add(_T("Login"));

        return $this->galetteRedirect($request, $response);
    }


    /**
     * Where a member sees and changes their own second factor
     */
    #[Route(
        name: 'two-factor-manage',
        pattern: '/two-factor/manage',
        methods: ['GET']
    )]
    public function manage(Response $response, TwoFactorAuth $tfa): Response
    {
        if (!$tfa->isEnabled()) {
            return $this->unavailable($response);
        }

        $secret = $tfa->storeFor($this->login);

        $this->view->render(
            $response,
            'pages/two_factor_manage.html.twig',
            [
                'page_title' => _T("Two-factor authentication"),
                'tfa_enabled' => $secret->isEnabled(),
                //the super administrator gets none: they live in the
                //preferences, and its documented way back in is clearing them
                'has_recovery_codes' => $secret instanceof TwoFactorSecret,
                'remaining_codes' => $secret instanceof TwoFactorSecret ? $secret->countRemainingCodes() : 0,
                'tfa_required' => $tfa->isRequiredFor($this->login)
            ]
        );
        return $response;
    }

    /**
     * Show the secret to register in an authenticator application.
     *
     * Re-enrolling has to go through disabling first: generating a new secret
     * here would silently break the one already in the member's application.
     */
    #[Route(
        name: 'two-factor-enrol',
        pattern: '/two-factor/enrol',
        methods: ['GET']
    )]
    public function enrol(Response $response, TwoFactorAuth $tfa): Response
    {
        if (!$tfa->isEnabled()) {
            return $this->unavailable($response);
        }

        $secret = $tfa->storeFor($this->login);

        if ($secret->isEnabled()) {
            $this->flash->addMessage(
                'warning_detected',
                _T("Two-factor authentication is already enabled. Disable it first to enrol again.")
            );
            return $this->redirectTo($response, 'two-factor-manage');
        }

        //a pending secret is reused, so reloading the page does not invalidate
        //a QR code the member has just scanned
        if (!$secret->isLoaded()) {
            if ($secret instanceof TwoFactorSuperAdmin) {
                $secret->create($tfa->createSecret());
            } else {
                $secret->create((int)$this->login->id, $tfa->createSecret());
            }
        }

        $this->view->render(
            $response,
            'pages/two_factor_enrol.html.twig',
            [
                'page_title' => _T("Enable two-factor authentication"),
                'secret' => $secret->getSecret(),
                'qrcode' => $tfa->getQrCode($secret->getSecret(), (string)$this->login->login)
            ]
        );
        return $response;
    }

    /**
     * Turn the second factor on, the member having produced a valid code
     */
    #[Route(
        name: 'do-two-factor-enrol',
        pattern: '/two-factor/enrol',
        methods: ['POST']
    )]
    public function doEnrol(Request $request, Response $response, TwoFactorAuth $tfa): Response
    {
        if (!$tfa->isEnabled()) {
            return $this->unavailable($response);
        }

        $secret = $tfa->storeFor($this->login);
        if (!$secret->isLoaded() || $secret->isEnabled()) {
            return $this->redirectTo($response, 'two-factor-manage');
        }

        if (!$tfa->verify($secret, self::postedCode($request))) {
            $this->flash->addMessage(
                'error_detected',
                _T("That code does not match. Check your application clock, then try again.")
            );
            return $this->redirectTo($response, 'two-factor-enrol');
        }

        $secret->enable();
        $this->history->add(_T("Two-factor authentication enabled"));

        if (!$secret instanceof TwoFactorSecret) {
            //no recovery codes for the super administrator: they would live in
            //the very preferences one has to reach to recover that account
            $this->flash->addMessage(
                'success_detected',
                _T("Two-factor authentication is now enabled for the super administrator account.")
            );
            return $this->redirectTo($response, 'two-factor-manage');
        }

        return $this->showRecoveryCodes($response, $secret->generateRecoveryCodes());
    }

    /**
     * Turn the second factor off.
     *
     * A code is required: a hijacked session must not be able to remove the
     * very thing standing in its way.
     */
    #[Route(
        name: 'do-two-factor-disable',
        pattern: '/two-factor/disable',
        methods: ['POST']
    )]
    public function doDisable(
        Request $request,
        Response $response,
        TwoFactorAuth $tfa,
        AuthThrottle $throttle
    ): Response {
        $secret = $this->loadProvenSecret($request, $tfa, $throttle);
        if ($secret === null) {
            return $this->redirectTo($response, 'two-factor-manage');
        }

        $secret->remove();
        $this->history->add(_T("Two-factor authentication disabled"));
        $this->flash->addMessage('success_detected', _T("Two-factor authentication has been disabled."));

        return $this->redirectTo($response, 'two-factor-manage');
    }

    /**
     * Hand out a fresh set of recovery codes, the previous ones becoming void
     */
    #[Route(
        name: 'do-two-factor-codes',
        pattern: '/two-factor/recovery-codes',
        methods: ['POST']
    )]
    public function doRenewCodes(
        Request $request,
        Response $response,
        TwoFactorAuth $tfa,
        AuthThrottle $throttle
    ): Response {
        $secret = $this->loadProvenSecret($request, $tfa, $throttle);
        //recovery codes only exist for members
        if (!$secret instanceof TwoFactorSecret) {
            return $this->redirectTo($response, 'two-factor-manage');
        }

        $this->history->add(_T("Two-factor recovery codes renewed"));
        return $this->showRecoveryCodes($response, $secret->generateRecoveryCodes());
    }

    /**
     * Clear a member's second factor.
     *
     * The way back in for a member who lost both their device and their
     * recovery codes. Traced, since it removes a protection from an account
     * other than one's own.
     */
    #[Route(
        name: 'do-two-factor-reset',
        pattern: '/two-factor/reset/{id:\\d+}',
        methods: ['POST']
    )]
    public function doReset(Request $request, Response $response, int $id): Response
    {
        $member = new Adherent($this->zdb, $id, deps: false);
        if ($member->id === null) {
            throw new HttpNotFoundException($request);
        }

        //removing one's own is doDisable, which asks for a code first: a
        //hijacked session must not have a second, unproven way out
        if ($id === (int)$this->login->id) {
            return $this->refuseReset(
                $response,
                $id,
                _T("Use your own two-factor authentication page to disable it.")
            );
        }

        //staff may edit an administrator, so resetting one from here would hand
        //an administrator account over to whoever holds a staff one. Same rank
        //is refused too: this is a way past somebody else's second factor, and
        //it only goes downwards.
        if ($member->isAdmin() && !$this->login->isSuperAdmin()) {
            return $this->refuseReset(
                $response,
                $id,
                _T("Only the super administrator may reset an administrator's two-factor authentication.")
            );
        }

        if ($member->isStaff() && !$this->login->isAdmin()) {
            return $this->refuseReset(
                $response,
                $id,
                _T("Only an administrator may reset a staff member's two-factor authentication.")
            );
        }

        $secret = new TwoFactorSecret($this->zdb);
        if (!$secret->load($id)) {
            $this->flash->addMessage(
                'warning_detected',
                _T("This member has no two-factor authentication to reset.")
            );
        } else {
            $secret->remove();
            $this->history->add(
                sprintf(
                    //TRANS: %1$s is the member name
                    _T('Two-factor authentication reset for %1$s'),
                    $member->sname
                )
            );
            $this->flash->addMessage(
                'success_detected',
                _T("Two-factor authentication has been reset for this member.")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('member', ['id' => (string)$id]));
    }

    /**
     * Code submitted on a form, as a string whatever was posted.
     *
     * An array would otherwise be cast to a string, which the error handler
     * turns into an exception: a 500 that counts as no attempt at all.
     *
     * @param Request $request Request
     */
    private static function postedCode(Request $request): string
    {
        $post = $request->getParsedBody();
        $code = is_array($post) ? ($post['code'] ?? '') : '';

        return is_scalar($code) ? trim((string)$code) : '';
    }

    /**
     * Refuse a reset, back to the member page
     *
     * @param Response $response Response
     * @param int      $id       Member identifier
     * @param string   $message  Why it is refused
     */
    private function refuseReset(Response $response, int $id, string $message): Response
    {
        Analog::log(
            'Refused a second factor reset of member ' . $id . ' asked by `'
            . $this->login->login . '`.',
            Analog::WARNING
        );
        $this->flash->addMessage('error_detected', $message);

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('member', ['id' => (string)$id]));
    }

    /**
     * Send back a session asking for a second factor the instance does not use.
     *
     * Enrolling under a disabled policy would store a secret that is never
     * asked for, and would come back to life the day the policy is turned on.
     *
     * @param Response $response Response
     */
    private function unavailable(Response $response): Response
    {
        $this->flash->addMessage(
            'warning_detected',
            _T("Two-factor authentication is not enabled on this instance.")
        );

        return $this->redirectHome($response);
    }

    /**
     * Load the second factor of the current member, only if the request proves
     * they hold it
     *
     * @param Request       $request Request
     * @param TwoFactorAuth $tfa     Second factor service
     */
    private function loadProvenSecret(
        Request $request,
        TwoFactorAuth $tfa,
        AuthThrottle $throttle
    ): TwoFactorSecret|TwoFactorSuperAdmin|null {
        $code = self::postedCode($request);
        $login = (string)$this->login->login;

        //behind authentication, but a session someone else holds must not get
        //unlimited tries at the code that stands in its way
        $delay = $throttle->getSecondFactorDelay($login);
        if ($delay > 0) {
            Analog::log(
                'Second factor throttled for `' . $login . '`, ' . $delay . ' seconds left.',
                Analog::INFO
            );
            $this->flash->addMessage(
                'error_detected',
                _T("Too many failed attempts. Please try again later.")
            );
            return null;
        }

        $secret = $tfa->storeFor($this->login);
        if (!$secret->isEnabled()) {
            $this->flash->addMessage(
                'warning_detected',
                _T("Two-factor authentication is not enabled.")
            );
            return null;
        }

        //a recovery code is accepted too, where there are any
        $accepted = $tfa->verify($secret, $code)
            || ($secret instanceof TwoFactorSecret && $secret->consumeRecoveryCode($code));

        if (!$accepted) {
            $throttle->recordSecondFactorFailure($login);
            $this->history->add(_T("Second factor failed"), $login);
            $this->flash->addMessage('error_detected', _T("Two-factor authentication failed."));
            return null;
        }

        $throttle->recordSuccess($login);
        return $secret;
    }

    /**
     * Show recovery codes.
     *
     * Rendered straight away rather than through a redirect: the codes are only
     * ever held in this response, never in the session nor readable again.
     *
     * @param Response $response Response
     * @param string[] $codes    Codes to show
     */
    private function showRecoveryCodes(Response $response, array $codes): Response
    {
        $this->view->render(
            $response,
            'pages/two_factor_codes.html.twig',
            [
                'page_title' => _T("Recovery codes"),
                'codes' => $codes
            ]
        );
        return $response;
    }

    /**
     * Redirect to a named route
     *
     * @param Response $response Response
     * @param string   $route    Route name
     */
    private function redirectTo(Response $response, string $route): Response
    {
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor($route));
    }

    /**
     * Send a failed attempt back to the challenge
     *
     * @param Response $response Response
     * @param string   $message  Message to display
     */
    private function rejected(Response $response, string $message): Response
    {
        $this->flash->addMessage('error_detected', $message);
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('two-factor'));
    }

    /**
     * Send a caller with nothing pending back where it belongs
     *
     * @param Response $response Response
     */
    private function redirectHome(Response $response): Response
    {
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('slash'));
    }
}
