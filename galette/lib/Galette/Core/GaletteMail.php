<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Generic mail for Galette
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: galette_mail.class.php 728 2009-12-03 17:56:30Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-12-10
 */

namespace Galette\Core;

use Analog\Analog as Analog;

/** @ignore */
require_once 'class.phpmailer.php';
require_once GALETTE_ROOT . 'includes/html2text.php';

/**
 * Generic mail for Galette
 *
 * @category  Core
 * @name      GaletteMail
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
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

    private $_subject;
    private $_message;
    private $_alt_message;
    private $_html;

    private $_result;
    private $_errors = array();
    private $_recipients;

    private $_mail = null;

    /**
     * Initialize PHPMailer
     *
     * @return void
     */
    private function _initMailer()
    {
        global $preferences, $i18n;

        $this->_mail = new \PHPMailer();

        switch ( $preferences->pref_mail_method ) {
        case self::METHOD_SMTP:
        case self::METHOD_GMAIL:
            //if we want to send mails using a smtp server
            $this->_mail->IsSMTP();
            // enables SMTP debug information (for testing)
            /*$this->_mail->SMTPDebug = 2;*/

            if ( $preferences->pref_mail_method == self::METHOD_GMAIL ) {
                // sets GMAIL as the SMTP server
                $this->_mail->Host = "smtp.gmail.com";
                // enable SMTP authentication
                $this->_mail->SMTPAuth   = true;
                // sets the prefix to the servier
                $this->_mail->SMTPSecure = "tls";
                // set the SMTP port for the GMAIL server
                $this->_mail->Port = 587;
            } else {
                $this->_mail->Host = $preferences->pref_mail_smtp_host;
                $this->_mail->SMTPAuth   = $preferences->pref_mail_smtp_auth;
                $this->_mail->SMTPSecure = $preferences->pref_mail_smtp_secure;
                if ( $preferences->pref_mail_smtp_port
                    && $preferences->pref_mail_smtp_port != ''
                ) {
                    // set the SMTP port for the SMTP server
                    $this->_mail->Port = $preferences->pref_mail_smtp_port;
                } else {
                    Analog::log(
                        '[' . get_class($this) .
                        ']No SMTP port provided. Switch to default (25).',
                        Analog::INFO
                    );
                    $this->_mail->Port = 25;
                }
            }

            // SMTP account username
            $this->_mail->Username   = $preferences->pref_mail_smtp_user;
            // SMTP account password
            $this->_mail->Password   = $preferences->pref_mail_smtp_password;
            break;
        case self::METHOD_SENDMAIL:
            // telling the class to use Sendmail transport
            $this->_mail->IsSendmail();
            break;
        case self::METHOD_QMAIL:
            // telling the class to use QMail transport
            $this->_mail->IsQmail();
            break;
        }

        $this->_mail->SetFrom(
            $preferences->pref_email,
            $preferences->pref_email_nom
        );
        // Add a Reply-To field in the mail headers.
        // Fix bug #6654.
        if ( $preferences->pref_email_reply_to ) {
            $this->_mail->AddReplyTo($preferences->pref_email_reply_to);
        } else {
            $this->_mail->AddReplyTo($preferences->pref_email);
        }
        $this->_mail->CharSet = 'UTF-8';
        $this->_mail->SetLanguage($i18n->getAbbrev());

        $this->_mail->WordWrap = 70;
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

        if ( $this->_mail === null ) {
            $this->_initMailer();
        }

        $this->_recipients = array();
        foreach ( $recipients as $mail => $name ) {
            if ( self::isValidEmail($mail) ) {
                $this->_recipients[$mail] = $name;
                $this->_mail->AddBCC($mail, $name);
            } else {
                //one of adresses is not valid :
                //- set $res to false
                //- clear BCCs
                //- log an INFO
                $res = false;
                Analog::log(
                    '[' . get_class($this) .
                    '] One of recipients adress is not valid.',
                    Analog::INFO
                );
                $this->_mail->ClearBCCs();
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

        if ( $this->_mail === null ) {
            $this->_initMailer();
        }

        if ( $this->_html ) {
            //the mail is html :(
            $this->_mail->AltBody = $this->cleanedHtml();
            $this->_mail->IsHTML(true);
        } else {
            //the mail is plaintext :)
            $this->_mail->AltBody = null;
            $t = $this->_mail;
            $this->_mail->IsHTML(false);
        }

        $this->_mail->Subject = $this->_subject;
        $this->_mail->Body = $this->_message;

        //set at least on real recipient (not bcc)
        if ( count($this->_recipients) === 1 ) {
            //there is only one recipient, clean bcc and readd as simple recipient
            $this->_mail->ClearBCCs();
            $this->_mail->AddAddress(
                key($this->_recipients),
                current($this->_recipients)
            );
        } else {
            //we're sending a mailing. Set main recipient to sender
            $this->_mail->AddAddress(
                $preferences->pref_email,
                $preferences->pref_email_nom
            );
        }

        if ( trim($preferences->pref_mail_sign) != '' ) {

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

            if ( $this->_html ) {
                //we are sending html message
                $tsign = "\r\n-- \r\n" . $sign;
                //apply mail sign to text version
                $this->_mail->AltBody .= $tsign;
                //then apply mail sign to html version
                $sign_style = 'color:grey;border-top:1px solid #ccc;margin-top:2em';
                $hsign = '<div style="' . $sign_style. '">' . nl2br($sign) . '</div>';
                $this->_mail->Body .= $hsign;
            } else {
                $sign = "\r\n-- \r\n" . $sign;
                $this->_mail->Body .= $sign;
            }
        }

        try {
            //reinit errors array
            $this->_errors = array();
            //let's send the mail
            if ( !$this->_mail->Send() ) {
                $m = $this->_mail;
                $this->_errors[] = $this->_mail->ErrorInfo;
                Analog::log(
                    'An error occured sending mail to: ' .
                    implode(', ', array_keys($this->_recipients)),
                    Analog::INFO
                );
                $this->_mail = null;
                return self::MAIL_ERROR;
            } else {
                $txt = '';
                foreach ( $this->_recipients as $k=>$v ) {
                    $txt .= $v . ' (' . $k . '), ';
                }
                Analog::log(
                    'A mail has been sent to: ' . $txt,
                    Analog::INFO
                );
                $this->_mail = null;
                return self::MAIL_SENT;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Error sending message: ' . $e.getMessage(),
                Analog::ERROR
            );
            $this->errors[] = $e->getMessage();
            $this->_mail = null;
            return self::MAIL_ERROR;
        }
    }

    /**
    * Check if a mail adress is valid
    *
    * @param string $address the mail adress to check
    *
    * @return true if address is valid, false otherwise
    */
    public static function isValidEmail( $address )
    {
        $valid = \PHPMailer::ValidateAddress($address);
        if ( !$valid ) {
            Analog::log(
                '[GaletteMail] Adresss `' . $address . '` is not valid ',
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
    public static function isUrl( $url )
    {
        $valid = preg_match(
            '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i',
            $url
        );
        if ( !$valid ) {
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
        return $this->_mail;
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
        if ( is_bool($set) ) {
            $this->_html = $set;
        }
        return $this->_html;
    }

    /**
    * Get the subject
    *
    * @return string The subject
    */
    public function getSubject()
    {
        return $this->_subject;
    }

    /**
     * Retrieve array of errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
    * Get the message
    *
    * @return string The message
    */
    public function getMessage()
    {
        return $this->_message;
    }

    /**
    * Sets the subject
    *
    * @param string $s The subject
    *
    * @return void
    */
    public function setSubject($s)
    {
        $this->_subject = $s;
    }

    /**
    * Sets the message
    *
    * @param string $m The message
    *
    * @return void
    */
    public function setMessage($m)
    {
        $this->_message = $m;
    }
}
