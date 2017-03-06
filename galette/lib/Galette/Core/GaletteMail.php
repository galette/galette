<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Generic mail for Galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: galette_mail.class.php 728 2009-12-03 17:56:30Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-12-10
 */

namespace Galette\Core;

use Analog\Analog;

/** @ignore */
require_once GALETTE_ROOT . 'includes/html2text.php';

/**
 * Generic mail for Galette
 *
 * @category  Core
 * @name      GaletteMail
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-07
 */
class GaletteMail
{
    const MAIL_ERROR = 0;
    const MAIL_SENT = 1;

    const METHOD_DISABLED = 0;
    const METHOD_PHPMAIL = 1;
    const METHOD_SMTP = 2;
    const METHOD_QMAIL = 3;
    const METHOD_GMAIL = 4;
    const METHOD_SENDMAIL = 5;

    private $subject;
    private $message;
    private $html;
    private $word_wrap = 70;

    private $errors = array();
    private $recipients;

    private $mail = null;
    protected $attachments = array();

    /**
     * Initialize PHPMailer
     *
     * @return void
     */
    private function initMailer()
    {
        global $preferences, $i18n;

        $this->mail = new \PHPMailer();

        switch ($preferences->pref_mail_method) {
            case self::METHOD_SMTP:
            case self::METHOD_GMAIL:
                //if we want to send mails using a smtp server
                $this->mail->IsSMTP();
                // enables SMTP debug information (for testing)
                /*$this->mail->SMTPDebug = 2;*/

                if ($preferences->pref_mail_method == self::METHOD_GMAIL) {
                    // sets GMAIL as the SMTP server
                    $this->mail->Host = "smtp.gmail.com";
                    // enable SMTP authentication
                    $this->mail->SMTPAuth   = true;
                    // sets the prefix to the servier
                    $this->mail->SMTPSecure = "tls";
                    // set the SMTP port for the GMAIL server
                    $this->mail->Port = 587;
                } else {
                    $this->mail->Host = $preferences->pref_mail_smtp_host;
                    $this->mail->SMTPAuth   = $preferences->pref_mail_smtp_auth;
                    $this->mail->SMTPSecure = $preferences->pref_mail_smtp_secure;

                    if (!$preferences->pref_mail_smtp_secure || $preferences->pref_mail_allow_unsecure) {
                        //Allow "unsecure" SMTP connections if user has asked fot it or
                        //if user did not request TLS explicitely
                        $this->mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );
                    }

                    if ($preferences->pref_mail_smtp_port
                        && $preferences->pref_mail_smtp_port != ''
                    ) {
                        // set the SMTP port for the SMTP server
                        $this->mail->Port = $preferences->pref_mail_smtp_port;
                    } else {
                        Analog::log(
                            '[' . get_class($this) .
                            ']No SMTP port provided. Switch to default (25).',
                            Analog::INFO
                        );
                        $this->mail->Port = 25;
                    }
                }

                // SMTP account username
                $this->mail->Username   = $preferences->pref_mail_smtp_user;
                // SMTP account password
                $this->mail->Password   = $preferences->pref_mail_smtp_password;
                break;
            case self::METHOD_SENDMAIL:
                // telling the class to use Sendmail transport
                $this->mail->IsSendmail();
                break;
            case self::METHOD_QMAIL:
                // telling the class to use QMail transport
                $this->mail->IsQmail();
                break;
        }

        $this->mail->SetFrom(
            $preferences->pref_email,
            $preferences->pref_email_nom
        );
        // Add a Reply-To field in the mail headers.
        // Fix bug #6654.
        if ($preferences->pref_email_reply_to) {
            $this->mail->AddReplyTo($preferences->pref_email_reply_to);
        } else {
            $this->mail->AddReplyTo($preferences->pref_email);
        }
        $this->mail->CharSet = 'UTF-8';
        $this->mail->SetLanguage($i18n->getAbbrev());

        if ($preferences->pref_bool_wrap_mails) {
            $this->mail->WordWrap = $this->word_wrap;
        } else {
            $this->word_wrap = 0;
        }
    }

    /**
     * Sets the recipients
     * For mailing convenience, all recipients will be added as BCC,
     * regular recipient will be the sender.
     *
     * @param array $recipients Array (mail=>name) of all recipients
     *
     * @return boolean
     */
    public function setRecipients($recipients)
    {
        $res = true;

        if ($this->mail === null) {
            $this->initMailer();
        }

        $this->recipients = array();
        foreach ($recipients as $mail => $name) {
            if (self::isValidEmail($mail)) {
                $this->recipients[$mail] = $name;
                $this->mail->AddBCC($mail, $name);
            } else {
                //one of addresses is not valid :
                //- set $res to false
                //- clear BCCs
                //- log an INFO
                $res = false;
                Analog::log(
                    '[' . get_class($this) .
                    '] One of recipients address is not valid.',
                    Analog::INFO
                );
                $this->mail->ClearBCCs();
                break;
            }
        }
        return $res;
    }

    /**
     * Apply final header to mail and send it :-)
     *
     * @return GaletteMail::MAIL_ERROR|GaletteMail::MAIL_SENT
     */
    public function send()
    {
        global $preferences;

        if ($this->mail === null) {
            $this->initMailer();
        }

        if ($this->html) {
            //the mail is html :(
            $this->mail->AltBody = $this->cleanedHtml();
            $this->mail->IsHTML(true);
        } else {
            //the mail is plaintext :)
            $this->mail->AltBody = null;
            $this->mail->IsHTML(false);
        }

        $this->mail->Subject = $this->subject;
        $this->mail->Body = $this->message;

        //set at least on real recipient (not bcc)
        if (count($this->recipients) === 1) {
            //there is only one recipient, clean bcc and readd as simple recipient
            $this->mail->ClearBCCs();
            $this->mail->AddAddress(
                key($this->recipients),
                current($this->recipients)
            );
        } else {
            //we're sending a mailing. Set main recipient to sender
            $this->mail->AddAddress(
                $preferences->pref_email,
                $preferences->pref_email_nom
            );
        }

        if (trim($preferences->pref_mail_sign) != '') {
            $patterns = array(
                '/{NAME}/',
                '/{WEBSITE}/',
                '/{FACEBOOK}/',
                '/{GOOGLEPLUS}/',
                '/{TWITTER}/',
                '/{LINKEDIN}/',
                '/{VIADEO}/'
            );

            $replaces = array(
                $preferences->pref_nom,
                $preferences->pref_website,
                $preferences->pref_facebook,
                $preferences->pref_googleplus,
                $preferences->pref_twitter,
                $preferences->pref_linkedin,
                $preferences->pref_viadeo
            );

            $sign = preg_replace(
                $patterns,
                $replaces,
                $preferences->pref_mail_sign
            );

            if ($this->html) {
                //we are sending html message
                $tsign = "\r\n-- \r\n" . $sign;
                //apply mail sign to text version
                $this->mail->AltBody .= $tsign;
                //then apply mail sign to html version
                $sign_style = 'color:grey;border-top:1px solid #ccc;margin-top:2em';
                $hsign = '<div style="' . $sign_style. '">' .
                    nl2br($sign) . '</div>';
                $this->mail->Body .= $hsign;
            } else {
                $sign = "\r\n-- \r\n" . $sign;
                $this->mail->Body .= $sign;
            }
        }

        //join attachments
        if (count($this->attachments) > 0) {
            foreach ($this->attachments as $attachment) {
                $this->mail->AddAttachment(
                    $attachment->getDestDir() . $attachment->getFileName()
                );
            }
        }

        try {
            //reinit errors array
            $this->errors = array();
            //let's send the mail
            if (!$this->mail->Send()) {
                $this->errors[] = $this->mail->ErrorInfo;
                Analog::log(
                    'An error occured sending mail to: ' .
                    implode(', ', array_keys($this->recipients)),
                    Analog::INFO
                );
                $this->mail = null;
                return self::MAIL_ERROR;
            } else {
                $txt = '';
                foreach ($this->recipients as $k => $v) {
                    $txt .= $v . ' (' . $k . '), ';
                }
                Analog::log(
                    'A mail has been sent to: ' . $txt,
                    Analog::INFO
                );
                $this->mail = null;
                return self::MAIL_SENT;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Error sending message: ' . $e->getMessage(),
                Analog::ERROR
            );
            $this->errors[] = $e->getMessage();
            $this->mail = null;
            return self::MAIL_ERROR;
        }
    }

    /**
     * Check if a mail address is valid
     *
     * @param string $address the mail address to check
     *
     * @return true if address is valid, false otherwise
     */
    public static function isValidEmail($address)
    {
        $valid = \PHPMailer::ValidateAddress($address);
        if (!$valid) {
            Analog::log(
                '[GaletteMail] Address `' . $address . '` is not valid ',
                Analog::DEBUG
            );
        }
        return $valid;
    }

    /**
     * Check if a string is an url
     *
     * @param string $url the url to check
     *
     * @return true if address is string is an url, false otherwise
     */
    public static function isUrl($url)
    {
        $valid = preg_match(
            '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i',
            $url
        );
        if (!$valid) {
            Analog::log(
                '[GaletteMail] `' . $url . '` is not an url',
                Analog::DEBUG
            );
        }
        return $valid;
    }

    /**
     * Clean a string embedding html, producing AltText for html mails
     *
     * @return current message in plaintext format
     */
    protected function cleanedHtml()
    {
        $html = $this->message;
        $txt = convert_html_to_text($html);
        return $txt;
    }

    /**
     * Retrieve PHPMailer main object
     *
     * @return PHPMailer object
     */
    protected function getPhpMailer()
    {
        return $this->mail;
    }

    /**
     * Is the mail HTML formatted?
     *
     * @param boolean $set The value to set
     *
     * @return boolean
     */
    public function isHTML($set = null)
    {
        if (is_bool($set)) {
            $this->html = $set;
        }
        return $this->html;
    }

    /**
     * Get the subject
     *
     * @return string The subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Retrieve array of errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get the message
     *
     * @return string The message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get the message, wrapped
     *
     * @return string Wrapped message
     */
    public function getWrappedMessage()
    {
        if ($this->word_wrap > 0) {
            if ($this->mail === null) {
                $this->initMailer();
            }

            return $this->mail->wrapText(
                $this->message,
                $this->word_wrap
            );
        } else {
            return $this->message;
        }
    }

    /**
     * Sets the subject
     *
     * @param string $subject The subject
     *
     * @return void
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * Sets the message
     *
     * @param string $message The message
     *
     * @return void
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }
}
