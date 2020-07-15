<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Event listener for members
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Events
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 2020-07-14
 */

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
use League\Event\Event;
use League\Event\ListenerAcceptorInterface;
use League\Event\ListenerProviderInterface;
use Slim\Flash\Messages;
use Slim\Router;

/**
 * Event listener for members
 *
 * @category  Events
 * @name      MemberListener
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 2020-07-14
 */
class MemberListener implements ListenerProviderInterface
{
    /** @var Preferences */
    private $preferences;
    /** @var Router */
    private $router;
    /** @var History */
    private $history;
    /** @var Messages */
    private $flash;
    /** @var Login */
    private $login;
    /** @var Db */
    private $zdb;

    /**
     * Constructor
     *
     * @param Preferences $preferences Preferences instance
     * @param Router      $router      Router instance
     * @param History     $history     History instance
     * @param Messages    $flash       Messages instance
     * @param Login       $login       Login instance
     * @param Db          $zdb         Db instance
     */
    public function __construct(
        Preferences $preferences,
        Router $router,
        History $history,
        Messages $flash,
        Login $login,
        Db $zdb
    ) {
        $this->preferences = $preferences;
        $this->router = $router;
        $this->history = $history;
        $this->flash = $flash;
        $this->login = $login;
        $this->zdb = $zdb;
    }

    /**
     * Set up member listeners
     *
     * @param ListenerAcceptorInterface $acceptor Listener
     *
     * @return void
     */
    public function provideListeners(ListenerAcceptorInterface $acceptor)
    {
        $acceptor->addListener(
            'member.add',
            function ($event, $member) {
                $this->memberAdded($event, $member);
            }
        );

        $acceptor->addListener(
            'member.edit',
            function ($event, $member) {
                $this->memberEdited($event, $member);
            }
        );
    }

    /**
     * Memebr added listener
     *
     * @param Event    $event  Raised event
     * @param Adherent $member Added member
     *
     * @return void
     */
    public function memberAdded(Event $event, Adherent $member)
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
     * Memebr edited listener
     *
     * @param Event    $event  Raised event
     * @param Adherent $member Added member
     *
     * @return void
     */
    public function memberEdited(Event $event, Adherent $member)
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
     * Get texts replacements array for member
     *
     * @param Adherent $member Member instance
     *
     * @return array
     */
    private function getReplacements(Adherent $member): array
    {
        $mreplaces = [
            'name_adh'      => custom_html_entity_decode(
                $member->sname
            ),
            'firstname_adh' => custom_html_entity_decode(
                $member->surname
            ),
            'lastname_adh'  => custom_html_entity_decode(
                $member->name
            ),
            'mail_adh'      => custom_html_entity_decode(
                $member->getEmail()
            ),
            'login_adh'     => custom_html_entity_decode(
                $member->login
            )
        ];
        return $mreplaces;
    }

    /**
     * Send account email to member
     *
     * @param Adherent $member Member
     * @param boolean  $new    New member or editing existing one
     *
     * @return void
     */
    private function sendMemberEmail(Adherent $member, $new)
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

        $mreplaces = $this->getReplacements($member);
        if ($new) {
            $password = new Password($this->zdb);
            $res = $password->generateNewPassword($member->id);
            if ($res == true) {
                $link_validity = new \DateTime();
                $link_validity->add(new \DateInterval('PT24H'));
                $mreplaces['change_pass_uri'] = $this->preferences->getURL() .
                    $this->router->pathFor(
                        'password-recovery',
                        ['hash' => base64_encode($password->getHash())]
                    );
                $mreplaces['link_validity'] = $link_validity->format(_T("Y-m-d H:i:s"));
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

        // Get email text in database
        $texts = new Texts(
            $this->preferences,
            $this->router,
            $mreplaces
        );

        $mlang = $member->language;
        $mtxt = $texts->getTexts(
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
    private function sendAdminEmail(Adherent $member, $new)
    {
        if (
            $this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED
            || !$this->preferences->pref_bool_mailadh
            || (!$new && $member->id != $this->login->id)
        ) {
            return;
        }


        $mreplaces = $this->getReplacements($member);
        $texts = new Texts(
            $this->preferences,
            $this->router,
            $mreplaces
        );

        $txt_id = null;
        if ($new) {
            $txt_id = ($member->self_adh ? 'newselfadh' : 'newadh');
        } else {
            $txt_id = 'admaccountedited';
        }

        $mlang = $this->preferences->pref_lang;
        $mtxt = $texts->getTexts(
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
