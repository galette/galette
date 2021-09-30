<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Event listener for contributions
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
 * @since     Available since 2020-08-25
 */

namespace Galette\Events;

use Galette\Core\Db;
use Galette\Core\GaletteMail;
use Galette\Core\History;
use Galette\Core\Links;
use Galette\Core\Login;
use Galette\Core\Password;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Texts;
use Analog\Analog;
use League\Event\Event;
use League\Event\ListenerAcceptorInterface;
use League\Event\ListenerProviderInterface;
use Slim\Flash\Messages;
use Slim\Router;

/**
 * Event listener for contributions
 *
 * @category  Events
 * @name      MemberListener
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 2020-08-25
 */
class ContribListener implements ListenerProviderInterface
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
     * Set up contribution listeners
     *
     * @param ListenerAcceptorInterface $acceptor Listener
     *
     * @return void
     */
    public function provideListeners(ListenerAcceptorInterface $acceptor)
    {
        $acceptor->addListener(
            'contribution.add',
            function ($event, $contrib) {
                $this->contributionAdded($event, $contrib);
            }
        );
    }

    /**
     * Contribution added listener
     *
     * @param Event        $event   Raised event
     * @param Contribution $contrib Added contribution
     *
     * @return void
     */
    public function contributionAdded(Event $event, Contribution $contrib)
    {
        Analog::log(
            '[' . get_class($this) . '] Event contribution.add emitted for #' . $contrib->id,
            Analog::DEBUG
        );

        $this->callPostContributionScript($contrib);

        if ($contrib->sendEMail()) {
            $this->sendContribEmail($contrib, true);
        }
        $this->sendAdminEmail($contrib, true);
    }

    /**
     * Send account email to member
     *
     * @param Contribution $contrib Contribution
     * @param boolean      $new     New contribution or editing existing one
     *
     * @return void
     */
    private function sendContribEmail(Contribution $contrib, $new)
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

        // Get member information
        $member = new Adherent($this->zdb);
        $member->load($contrib->member);

        if ($member->getEmail() == '' && !$member->self_adh) {
            $this->flash->addMessage(
                'error_detected',
                _T("- You can't send a confirmation by email if the member hasn't got an address!")
            );
            return;
        }

        $texts = new Texts(
            $this->preferences,
            $this->router,
        );
        $texts
            ->setMember($member)
            ->setContribution($contrib);

        $text = 'contrib';
        if (!$contrib->isFee()) {
            $text = 'donation';
        }
        $mtxt = $texts->getTexts($text, $member->language);

        $mail = new GaletteMail($this->preferences);
        $mail->setSubject($texts->getSubject());
        $mail->setRecipients(
            array(
                $member->getEmail() => $member->sname
            )
        );

        $link_card = '';
        if (strpos($mtxt->tbody, '{LINK_MEMBERCARD}') !== false) {
            //member card link is present in mail model, let's add it
            $links = new Links($this->zdb);
            if ($hash = $links->generateNewLink(Links::TARGET_MEMBERCARD, $contrib->member)) {
                $link_card = $this->preferences->getURL() .
                    $this->router->pathFor('directlink', ['hash' => $hash]);
            }
        }
        $texts->setMemberCardLink($link_card);

        $link_pdf = '';
        if (strpos($mtxt->tbody, '{LINK_CONTRIBPDF}') !== false) {
            //contribution receipt link is present in mail model, let's add it
            $links = new Links($this->zdb);
            $ltype = $contrib->type->isExtension() ? Links::TARGET_INVOICE : Links::TARGET_RECEIPT;
            if ($hash = $links->generateNewLink($ltype, $contrib->id)) {
                $link_pdf = $this->preferences->getURL() .
                    $this->router->pathFor('directlink', ['hash' => $hash]);
            }
        }
        $texts->setContribLink($link_pdf);

        $mail->setMessage($texts->getBody());
        $sent = $mail->send();

        if ($sent) {
            $this->history->add(
                preg_replace(
                    array('/%name/', '/%email/'),
                    array($member->sname, $member->getEmail()),
                    _T("Email sent to user %name (%email)")
                )
            );
        } else {
            $txt = preg_replace(
                array('/%name/', '/%email/'),
                array($member->sname, $member->getEmail()),
                _T("A problem happened while sending contribution receipt to user %name (%email)")
            );
            $this->history->add($txt);
            $this->flash->addMessage(
                'warning_detected',
                $txt
            );
        }
    }

    /**
     * Send new contribution email to admin
     *
     * @param Contribution $contrib Contribution
     * @param boolean      $new     New contribution or editing existing one
     *
     * @return void
     */
    private function sendAdminEmail(Contribution $contrib, $new)
    {
        if (
            $this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED
            || !$this->preferences->pref_bool_mailadh
            || (!$new && $contrib->member != $this->login->id)
        ) {
            return;
        }

        // Get member information
        $member = new Adherent($this->zdb);
        $member->load($contrib->member);

        $texts = new Texts(
            $this->preferences,
            $this->router
        );
        $texts
            ->setMember($member)
            ->setContribution($contrib);

        // Sent email to admin if pref checked
        // Get email text in database
        $text = 'newcont';
        if (!$contrib->isFee()) {
            $text = 'newdonation';
        }
        $texts->getTexts($text, $this->preferences->pref_lang);

        $mail = new GaletteMail($this->preferences);
        $mail->setSubject($texts->getSubject());

        $recipients = [];
        foreach ($this->preferences->vpref_email_newadh as $pref_email) {
            $recipients[$pref_email] = $pref_email;
        }
        $mail->setRecipients($recipients);

        $mail->setMessage($texts->getBody());
        $sent = $mail->send();

        if ($sent) {
            $this->history->add(
                preg_replace(
                    array('/%name/', '/%email/'),
                    array($member->sname, $member->getEmail()),
                    _T("Email sent to admin for user %name (%email)")
                )
            );
        } else {
            $txt = preg_replace(
                array('/%name/', '/%email/'),
                array($member->sname, $member->getEmail()),
                _T("A problem happened while sending to admin notification for user %name (%email) contribution")
            );
            $this->history->add($txt);
            $this->flash->addMessage(
                'warning_detected',
                $txt
            );
        }
    }

    /**
     * Call post contribution script from Preferences
     *
     * @param Contribution $contrib Added contribution
     *
     * @return void
     */
    private function callPostContributionScript($contrib)
    {
        //if an external script has been configured, we call it
        if ($this->preferences->pref_new_contrib_script) {
            $es = new \Galette\IO\ExternalScript($this->preferences);
            $res = $contrib->executePostScript($es);

            if ($res !== true) {
                //send admin an email with all details
                if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                    $mail = new GaletteMail($this->preferences);
                    $mail->setSubject(
                        _T("Post contribution script failed")
                    );

                    $recipients = [];
                    foreach ($this->preferences->vpref_email_newadh as $pref_email) {
                        $recipients[$pref_email] = $pref_email;
                    }
                    $mail->setRecipients($recipients);

                    $message = _T("The configured post contribution script has failed.");
                    $message .= "\n" . _T("You can find contribution information and script output below.");
                    $message .= "\n\n";
                    $message .= $res;

                    $mail->setMessage($message);
                    $sent = $mail->send();

                    if (!$sent) {
                        $txt = _T('Post contribution script has failed.');
                        $this->history->add($txt, $message);
                        $warning_detected[] = $txt;
                        //Mails are disabled... We log (not safe, but)...
                        Analog::log(
                            'Email to admin has not been sent. Here was the data: ' .
                            "\n" . print_r($res, true),
                            Analog::ERROR
                        );
                    }
                } else {
                    //Mails are disabled... We log (not safe, but)...
                    Analog::log(
                        'Post contribution script has failed. Here was the data: ' .
                        "\n" . print_r($res, true),
                        Analog::ERROR
                    );
                }
            }
        }
    }
}
