<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
 *
 * PHP version 5
 *
 * Copyright © 2009 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-07
 */

/** @ignore */
require_once 'members.class.php';

/**
 * Mailing features
 *
 * @category  Classes
 * @name      Mailing
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-07
 */
class Mailing
{
    const STEP_START = 0;
    const STEP_PROGRESS = 1;
    const STEP_SEND = 2;

    const MAIL_ERROR = 0;
    const MAIL_SENT = 1;
    const MAIL_DISABLED = 2;
    const MAIL_BAD_CONFIG = 3;
    const MAIL_SERVER_NOT_REACHABLE = 4;
    const MAIL_BREAK_ATTEMPT = 5;

    const METHOD_DISABLED = 0;
    const METHOD_SENDMAIL = 1;
    const METHOD_SMTP = 2;

    const MIME_HTML = 'text/html';
    const MIME_TEXT = 'text/plain';
    const MIME_DEFAULT = self::MIME_TEXT;

    private $_unreachables;
    private $_recipients;
    private $_current_step;

    private $_subject;
    private $_message;
    private $_html;
    private $_mime_type;

    /**
    * Default constructor
    *
    * @param array $members An array of members
    */
    public function __construct($members)
    {
        $this->_current_step = self::STEP_START;
        $this->_mime_type = self::MIME_DEFAULT;
        /** TODO: add a preference that propose default mime-type to use,
            then init it here */
        //Check which members have a valid email adress and which have not
        foreach ($members as $member) {
            if ( trim($member->email) != '' && self::isValidEmail($member->email) ) {
                $this->_recipients[] = $member;
            } else {
                $this->_unreachables[] = $member;
            }
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
        // is_valid_email(): an e-mail validation utility routine
        // Version 1.1.1 -- September 10, 2000
        //
        // Written by Michael A. Alderete
        // Please send bug reports and improvements to: <michael@aldosoft.com>
        //
        // This function matches a proposed e-mail address against a validating
        // regular expression. It's intended for use in web registration systems
        // and other places where the user is inputting their e-mail address and
        // you want to check that it's OK.
        return (
            preg_match(
                '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+'.   // the user name
                '@'.                                     // the ubiquitous at-sign
                '([-0-9A-Z]+\.)+' .                      // host, sub-, and domain
                '([0-9A-Z]){2,4}$/i',                    // top-level domain (TLD)
                trim($address)
            )
        );
    }

    /**
    * Sanityze fields
    *
    * @param string $field the field to proceed
    *
    * @return string satitized header
    */
    private function _sanityzeMailHeaders($field)
    {
        /** TODO: better handling (replace bad string not just detect it) */
        $result = 0;
        if ( stripos("\r", $field) !== false || stripos("\n", $field) !== false ) {
            $result = 0;
        } else {
            $result = 1;
        }
        return $result;
    }

    /**
    * Send the mail
    *
    * @param string $to recipient
    *
    * @return one of the following:
    *     0 - error mail() -> MAIL_ERROR
    *     1 - mail sent -> MAIL_SENT
    *     2 - mail desactived in preferences -> MAIL_DISABLED
    *     3 - bad configuration ? -> MAIL_BAD_CONFIG
    *     4 - SMTP unreacheable -> MAIL_SERVER_NOT_REACHABLE
    *     5 - breaking attempt -> MAIL_BREAK_ATTEMPT
    */
    public function customMail($to)
    {
        global $preferences;
        /** TODO: keep an history of sent messages */
        $result = self::MAIL_ERROR;

        //Strip slashes if magic_quotes_gpc is enabled
        //Fix bug #9705
        if ( get_magic_quotes_gpc() ) {
            $mail_subject = stripslashes($mail_subject);
            $mail_text = stripslashes($mail_text);
        }

        //sanityze headers
        $params = array(
                $to,
                $this->_subject,
                //mail_text
                $this->_mime_type
        );

        foreach ($params as $param) {
            if ( !$this->_sanityzeMailHeaders($param) ) {
                return self::MAIL_BREAK_ATTEMPT;
                break;
            }
        }

        // Headers :

        // Add a Reply-To field in the mail headers.
        // Fix bug #6654.
        if ( $preferences->pref_email_reply_to ) {
            $reply_to = $preferences->pref_email_reply_to;
        } else {
            $reply_to = $preferences->pref_email;
        }

        $headers = array(
            "From: " . $preferences->pref_email_nom .
                " <" . $preferences->pref_email . ">",
            "Message-ID: <" . makeRandomPassword(16) . "-galette@" .
                $_SERVER['SERVER_NAME'] . ">",
            "Reply-To: <" . $reply_to . ">",
            "X-Sender: <" . $preferences->pref_email . ">",
            "Return-Path: <" . $preferences->pref_email . ">",
            "Errors-To: <" . $preferences->pref_email . ">",
            "X-Mailer: Galette-" . GALETTE_VERSION,
            "X-Priority: 3",
            "Content-Type: " . $this->_mime_type . "; charset=utf-8"
        );

        switch( $preferences->pref_mail_method ) {
        case self::METHOD_DISABLED:
            $result = self::MAIL_DISABLED;
            break;
        case self::METHOD_SENDMAIL:
            $mail_headers = "";
            foreach ($headers as $oneheader) {
                $mail_headers .= $oneheader . "\r\n";
            }
            //-f .PREF_EMAIL is to set Return-Path
            //if (!mail($email_to,$mail_subject,$mail_text, $mail_headers,"-f "
            //    .PREF_EMAIL))
            //set Return-Path
            //seems to does not work
            ini_set('sendmail_from', $preferences->pref_email);
            if (!mail($to, $this->_subject, $this->_message, $mail_headers)) {
                $result = self::MAIL_ERROR;
            } else {
                $result = self::MAIL_SENT;
            }
            break;
        case self::METHOD_SMTP:
            //set Return-Path
            ini_set('sendmail_from', $preferences->pref_email);
            $errno = "";
            $errstr = "";
            $connect = fsockopen(
                $preferences->pref_mail_smtp, 25, $errno, $errstr, 30
            );
            if ( !$connect ) {
                $result = self::MAIL_SERVER_NOT_REACHABLE;
            } else {
                $rcv = fgets($connect, 1024);
                fputs($connect, "HELO {$_SERVER['SERVER_NAME']}\r\n");
                $rcv = fgets($connect, 1024);
                fputs($connect, "MAIL FROM:" . $preferences->pref_email . "\r\n");
                $rcv = fgets($connect, 1024);
                fputs($connect, "RCPT TO:" . $to . "\r\n");
                $rcv = fgets($connect, 1024);
                fputs($connect, "DATA\r\n");
                $rcv = fgets($connect, 1024);
                foreach ( $headers as $oneheader ) {
                    fputs($connect, $oneheader . "\r\n");
                }
                fputs($connect, stripslashes("Subject: " . $this->_subject)."\r\n");
                fputs($connect, "\r\n");
                fputs($connect, stripslashes($this->_message) . " \r\n");
                fputs($connect, ".\r\n");
                $rcv = fgets($connect, 1024);
                fputs($connect, "RSET\r\n");
                $rcv = fgets($connect, 1024);
                fputs($connect, "QUIT\r\n");
                $rcv = fgets($connect, 1024);
                fclose($connect);
                $result = self::MAIL_SENT;
            }
            break;
        default:
            $result = self::MAIL_BAD_CONFIG;
        }
        return $result;
    }

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return false|object the called property
    */
    public function __get($name)
    {
        global $log;
        $forbidden = array('ordered');
        $rname = '_' . $name;
        $log->log('Trying to get ' . $name . ' renamed: ' . $rname, PEAR_LOG_INFO);
        if ( !in_array($name, $forbidden) ) {
            switch($name) {
            case 'message':
                return $this->$rname;
                break;
            case 'step':
                return $this->current_step;
                break;
            default:
                return $this->$rname;
                break;
            }
        } else {
            return false;
        }
    }

    /**
    * Global setter method
    *
    * @param string $name  name of the property we want to assign a value to
    * @param object $value a relevant value for the property
    *
    * @return void
    */
    public function __set($name, $value)
    {
        global $log;
        $rname = '_' . $name;

        switch( $name ) {
        case 'subject':
        case 'message':
            $this->$rname = (get_magic_quotes_gpc())
                ? stripslashes($value)
                : $value;
            break;
        case 'current_step':
            if ( is_int($value)
                && (   $value == self::STEP_START
                || $value == self::STEP_PROGRESS
                || $value == self::STEP_SEND )
            ) {
                $this->_current_step = (int)$value;
            } else {
                $log->log(
                    '[mailing.class.php] Value for field `' . $name .
                    '` should be integer and know - (' . gettype($value) . ')' .
                    $value . ' given',
                    PEAR_LOG_WARNING
                );
            }
            break;
        case 'html':
            $log->log(
                '[mailing.class.php] Setting property `' . $name . '`',
                PEAR_LOG_DEBUG
            );
            if ( is_bool($value) ) {
                $this->$rname = $value;
                $this->_mime_type = ( $this->$rname )
                    ? self::MIME_HTML
                    : self::MIME_TEXT;
            } else {
                $log->log(
                    '[mailing.class.php] Value for field `' . $name .
                    '` should be boolean - (' . gettype($value) . ')' .
                    $value . ' given',
                    PEAR_LOG_WARNING
                );
            }
            break;
        default:
            $log->log(
                '[mailing.class.php] Unable to set proprety `' . $name . '`',
                PEAR_LOG_WARNING
            );
            break;
        }
    }
}
?>