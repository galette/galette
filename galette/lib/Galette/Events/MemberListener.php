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

namespace Galette\Events;

use Galette\Core\Db;
use Galette\Core\GaletteMail;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Password;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\Texts;
use Analog\Analog;
use League\Event\ListenerRegistry;
use League\Event\ListenerSubscriber;
use Slim\Flash\Messages;
use Slim\Routing\RouteParser;

/**
 * Event listener for members
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberListener implements ListenerSubscriber
{
    private Preferences $preferences;
    private RouteParser $routeparser;
    private History $history;
    private Messages $flash;
    private Login $login;
    private Db $zdb;

    /**
     * Constructor
     *
     * @param Preferences $preferences Preferences instance
     * @param RouteParser $routeparser RouteParser instance
     * @param History     $history     History instance
     * @param Messages    $flash       Messages instance
     * @param Login       $login       Login instance
     * @param Db          $zdb         Db instance
     */
    public function __construct(
        Preferences $preferences,
        RouteParser $routeparser,
        History $history,
        Messages $flash,
        Login $login,
        Db $zdb
    ) {
        $this->preferences = $preferences;
        $this->routeparser = $routeparser;
        $this->history = $history;
        $this->flash = $flash;
        $this->login = $login;
        $this->zdb = $zdb;
    }

    /**
     * Set up member listeners
     *
     * @param ListenerRegistry $acceptor Listener
     *
     * @return void
     */
    public function subscribeListeners(ListenerRegistry $acceptor): void
    {
        $acceptor->subscribeTo(
            'member.add',
            function (GaletteEvent $event): void {
                $this->memberAdded($event->getObject());
            }
        );

        $acceptor->subscribeTo(
            'member.edit',
            function (GaletteEvent $event): void {
                $this->memberEdited($event->getObject());
            }
        );
    }

    /**
     * Member added listener
     *
     * @param Adherent $member Added member
     *
     * @return void
     */
    public function memberAdded(Adherent $member): void
    {
        Analog::log(
            '[' . get_class($this) . '] Event member.add emitted for ' . $member->sfullname,
            Analog::DEBUG
        );

        if ($member->sendEMail()) {
            $this->sendMemberEmail($member, true);
        }

        $this->sendAdminEmail($member, true);
    }

    /**
     * Member edited listener
     *
     * @param Adherent $member Added member
     *
     * @return void
     */
    public function memberEdited(Adherent $member): void
    {
        Analog::log(
            '[' . get_class($this) . '] Event member.edit emitted for ' . $member->sfullname,
            Analog::DEBUG
        );

        if ($member->sendEMail()) {
            $this->sendMemberEmail($member, false);
        }

        $this->sendAdminEmail($member, false);
    }

    /**
     * Send account email to member
     *
     * @param Adherent $member Member
     * @param boolean  $new    New member or editing existing one
     *
     * @return void
     */
    private function sendMemberEmail(Adherent $member, bool $new): void
    {
        if ($this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
            //if email has been disabled in the preferences, we should not be here ;
            //we do not throw an error, just a simple warning that will be show later
            $msg = _T("You asked Galette to send a confirmation email to the member, but email has been disabled in the preferences.");
            $this->flash->addMessage(
                'warning_detected',
                $msg
            );
            return;
        }

        if ($member->getEmail() == '' && !$member->self_adh) {
            $this->flash->addMessage(
                'error_detected',
                _T("- You can't send a confirmation by email if the member hasn't got an address!")
            );
            return;
        }

        // Get email text in database
        $texts = new Texts(
            $this->preferences,
            $this->routeparser
        );

        $texts->setMember($member)->setNoContribution();

        if ($new) {
            $password = new Password($this->zdb);
            $res = $password->generateNewPassword($member->id);
            if ($res === true) {
                $texts
                    ->setLinkValidity()
                    ->setChangePasswordURI($password);
            } else {
                $str = str_replace(
                    '%s',
                    $member->sfullname,
                    _T("An error occurred storing temporary password for %s. Please inform an admin.")
                );
                $this->history->add($str);
                $this->flash->addMessage(
                    'error_detected',
                    $str
                );
            }
        }

        $mlang = $member->language;
        $texts->getTexts(
            (($new) ? 'sub' : 'accountedited'),
            $mlang
        );

        $mail = new GaletteMail($this->preferences);
        $mail->setSubject($texts->getSubject());
        $mail->setRecipients(
            array(
                $member->getEmail() => $member->sname
            )
        );
        $mail->setMessage($texts->getBody());
        $sent = $mail->send();

        if ($sent == GaletteMail::MAIL_SENT) {
            $msg = str_replace(
                '%s',
                $member->sname . ' (' . $member->getEmail() . ')',
                ($new) ?
                _T("New account email sent to '%s'.") : _T("Account modification email sent to '%s'.")
            );
            $this->history->add($msg);
            $success_detected[] = $msg;
        } else {
            $str = str_replace(
                '%s',
                $member->sname . ' (' . $member->getEmail() . ')',
                _T("A problem happened while sending account email to '%s'")
            );
            $this->history->add($str);
            $error_detected[] = $str;
        }
    }

    /**
     * Send account email to admin
     *
     * @param Adherent $member Member
     * @param boolean  $new    New member or editing existing one
     *
     * @return void
     */
    private function sendAdminEmail(Adherent $member, bool $new): void
    {
        if (
            $this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED
            || !$this->preferences->pref_bool_mailadh
            || (!$new && $member->id != $this->login->id)
        ) {
            return;
        }


        $texts = new Texts(
            $this->preferences,
            $this->routeparser
        );
        $texts->setMember($member)->setNoContribution();

        $txt_id = null;
        if ($new) {
            $txt_id = ($member->self_adh ? 'newselfadh' : 'newadh');
        } else {
            $txt_id = 'admaccountedited';
        }

        $mlang = $this->preferences->pref_lang;
        $texts->getTexts(
            $txt_id,
            $mlang
        );

        $mail = new GaletteMail($this->preferences);
        $mail->setSubject($texts->getSubject());
        $recipients = [];
        foreach ($this->preferences->vpref_email_newadh as $pref_email) {
            $recipients[$pref_email] = $pref_email;
        }
        $mail->setRecipients($recipients);
        $mail->setMessage($texts->getBody());
        $sent = $mail->send();

        if ($sent == GaletteMail::MAIL_SENT) {
            $msg = $new ?
                str_replace(
                    '%s',
                    $member->sname . ' (' . $member->email . ')',
                    _T("New account email sent to admin for '%s'.")
                ) : _T("Account modification email sent to admin.");

            $this->history->add($msg);
            $this->flash->addMessage(
                'success_detected',
                $msg
            );
        } else {
            $msg = $new ?
                str_replace(
                    '%s',
                    $member->sname . ' (' . $member->email . ')',
                    _T("A problem happened while sending email to admin for account '%s'.")
                ) : _T("A problem happened while sending account email to admin");

            $this->history->add($msg);
            $this->flash->addMessage(
                'warning_detected',
                $msg
            );
        }
    }
}
