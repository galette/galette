<?php

// Copyright © 2009 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * mailing.class.php, 7 mars 2009
 *
 * @package Galette
 * 
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.7alpha
 */

/**
 * Mailing class for galette
 *
 * @name Mailing
 * @package Galette
 *
 */

require_once('members.class.php');

class Mailing{
	const STEP_START = 0;
	const STEP_PROGRESS = 1;
	const STEP_SEND = 2;

	const MAIL_ERROR = 0;
	const MAIL_SENT = 1;
	const MAIL_DISABLED = 2;
	const MAIL_BAD_CONFIG = 3;
	const MAIL_SERVER_NOT_REACHABLE = 4;
	const MAIL_BREAK_ATTEMPT = 5;

	const MIME_HTML = 'text/html';
	const MIME_TEXT = 'text/plain';
	const MIME_DEFAULT = self::MIME_TEXT;

	private $unreachables;
	private $recipients;
	private $current_step;

	private $subject;
	private $message;
	private $html;
	private $mime_type;

	/**
	* Default constructor
	* @param members An array of members
	*/
	public function __construct($members){
		$this->current_step = self::STEP_START;
		$this->mime_type = self::MIME_DEFAULT;
		/** TODO: add a preference that propose default mime-type to use, then init it here */
		//Check which members have a valid email adress and which have not
		foreach($members as $member){
			if( trim($member->email) != '' && self::isValidEmail($member->email) ){
				$this->recipients[] = $member;
			} else {
				$this->unreachables[] = $member;
			}
		}
	}

	/**
	* Check if a mail adress is valid
	* @param address the mail adress to check
	* @return true if address is valid, false otherwise
	*/
	public static function isValidEmail( $address ) {
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
				'([-0-9A-Z]+\.)+' .                      // host, sub-, and domain names
				'([0-9A-Z]){2,4}$/i',                    // top-level domain (TLD)
				trim($address)
			)
		);
	}

	/**
	* Sanityze fields
	* @param field the fild to proceed
	*/
	private function sanityzeMailHeaders($field) {
		/** TODO: better handling (replace bad string not just detect it) */
		$result = 0;
		if ( stripos("\r",$field)!==false || stripos("\n",$field)!==false ) {
			$result = 0;
		} else {
			$result = 1;
		}
		return $result;
	}

	/**
	* Send the mail
	* @param to recipient
	*
	* @return one of the following:
	* 	0 - error mail() -> MAIL_ERROR
	* 	1 - mail sent -> MAIL_SENT
	* 	2 - mail desactived in preferences -> MAIL_DISABLED
	* 	3 - bad configuration ? -> MAIL_BAD_CONFIG
	* 	4 - SMTP unreacheable -> MAIL_SERVER_NOT_REACHABLE
	* 	5 - breaking attempt -> MAIL_BREAK_ATTEMPT
	*/
	public function customMail($to){
		/** TODO: keep an history of sent messages */
		$result = self::MAIL_ERROR;
	
		//Strip slashes if magic_quotes_gpc is enabled
		//Fix bug #9705
		if(get_magic_quotes_gpc()){
			$mail_subject = stripslashes($mail_subject);
			$mail_text = stripslashes($mail_text);
		}
	
		//sanityze headers
		$params = array(
				$to,
				$this->subject,
				//mail_text
				$this->mime_type
		);
		
		foreach ($params as $param) {
			if( !$this->sanityzeMailHeaders($param) ) {
				return self::MAIL_BREAK_ATTEMPT;
				break;
			}
		}
	
		// Headers :
	
		// Add a Reply-To field in the mail headers.
		// Fix bug #6654.
		if ( PREF_EMAIL_REPLY_TO )
			$reply_to = PREF_EMAIL_REPLY_TO;
		else
			$reply_to = PREF_EMAIL;
	
		$headers = array(
				"From: ".PREF_EMAIL_NOM." <".PREF_EMAIL.">",
				"Message-ID: <".makeRandomPassword(16)."-galette@".$_SERVER['SERVER_NAME'].">",
				"Reply-To: <".$reply_to.">",
				"X-Sender: <".PREF_EMAIL.">",
				"Return-Path: <".PREF_EMAIL.">",
				"Errors-To: <".PREF_EMAIL.">",
				"X-Mailer: Galette-".GALETTE_VERSION,
				"X-Priority: 3",
				"Content-Type: " . $this->mime_type . "; charset=utf-8"
		);
	
		switch (PREF_MAIL_METHOD){
			case 0:
				$result = self::MAIL_DISABLED;
				break;
			case 1:
				$mail_headers = "";
				foreach($headers as $oneheader)
					$mail_headers .= $oneheader . "\r\n";
				//-f .PREF_EMAIL is to set Return-Path
				//if (!mail($email_to,$mail_subject,$mail_text, $mail_headers,"-f ".PREF_EMAIL))
				//set Return-Path
				//seems to does not work
				ini_set('sendmail_from', PREF_EMAIL);
				if (!mail($to, $this->subject, $this->message, $mail_headers)) {
					$result = self::MAIL_ERROR;
				} else {
					$result = self::MAIL_SENT;
				}
				break;
			case 2:
				//set Return-Path
				ini_set('sendmail_from', PREF_EMAIL);
				$errno = "";
				$errstr = "";
				if (!$connect = fsockopen (PREF_MAIL_SMTP, 25, $errno, $errstr, 30))
					$result = self::MAIL_SERVER_NOT_REACHABLE;
				else{
					$rcv = fgets($connect, 1024);
					fputs($connect, "HELO {$_SERVER['SERVER_NAME']}\r\n");
					$rcv = fgets($connect, 1024);
					fputs($connect, "MAIL FROM:" . PREF_EMAIL . "\r\n");
					$rcv = fgets($connect, 1024);
					fputs($connect, "RCPT TO:" . $to . "\r\n");
					$rcv = fgets($connect, 1024);
					fputs($connect, "DATA\r\n");
					$rcv = fgets($connect, 1024);
					foreach($headers as $oneheader)
						fputs($connect, $oneheader . "\r\n");
					fputs($connect, stripslashes("Subject: " . $$this->subject)."\r\n");
					fputs($connect, "\r\n");
					fputs($connect, stripslashes($this-message) . " \r\n");
					fputs($connect, ".\r\n");
					$rcv = fgets($connect, 1024);
					fputs($connect, "RSET\r\n");
					$rcv = fgets($connect, 1024);
					fputs ($connect, "QUIT\r\n");
					$rcv = fgets ($connect, 1024);
					fclose($connect);
					$result = self::MAIL_SENT;
				}
				break;
			default:
				$result = self::MAIL_BAD_CONFIG;
		}
		return $result;
	}

	/* GETTERS */
	public function __get($name){
		$forbidden = array('ordered');
		if( !in_array($name, $forbidden) ){
			switch($name){
				case 'message':
					return ( !$this->html ) ? htmlspecialchars( $this->$name, ENT_QUOTES ) : $this->$name;
					break;
				default:
					return $this->$name;
					break;
			}
		}
		else return false;
	}
	/* SETTERS */
	public function __set($name, $value){
		global $log;

		switch( $name ){
			case 'subject':
			case 'message':
				$this->$name = (get_magic_quotes_gpc()) ? stripslashes($value) : $value;
				break;
			case 'step':
				if( is_int($value) && ( $value == self::STEP_START || $value == self::STEP_PROGRESS || $value == self::STEP_SEND ) ){
					$this->$name = (int)$value;
				} else {
					$log->log('[mailing.class.php] Value for field `' . $name . '` should be integer and know - (' . gettype($value) . ')' . $value . ' given', PEAR_LOG_WARNING);
				}
				break;
			case 'html':
				$log->log('[varslist.class.php] Setting property `' . $name . '`', PEAR_LOG_DEBUG);
				if( is_bool($value) ){
					$this->$name = $value;
					$this->mime_type = ( $this->$name ) ? self::MIME_HTML : self::MIME_TEXT;
				} else {
					$log->log('[mailing.class.php] Value for field `' . $name . '` should be boolean - (' . gettype($value) . ')' . $value . ' given', PEAR_LOG_WARNING);
				}
				break;
			default:
				$log->log('[varslist.class.php] Unable to set proprety `' . $name . '`', PEAR_LOG_WARNING);
				break;
		}
	}
}
?>