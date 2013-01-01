<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
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
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-07
 */

namespace Galette\Core;

use Analog\Analog as Analog;

/**
 * Mailing features
 *
 * @category  Core
 * @name      Mailing
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-07
 */
class Mailing extends GaletteMail
{
    const STEP_START = 0;
    const STEP_PREVIEW = 1;
    const STEP_SEND = 2;
    const STEP_SENT = 3;

    const MIME_HTML = 'text/html';
    const MIME_TEXT = 'text/plain';
    const MIME_DEFAULT = self::MIME_TEXT;

    private $_unreachables;
    private $_mrecipients;
    private $_current_step;

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
        if ( $members !== null) {
            //Check which members have a valid email adress and which have not
            $this->setRecipients($members);
        }
    }

    /**
    * Apply final header to mail and send it :-)
    *
    * @return GaletteMail::MAIL_ERROR|GaletteMail::MAIL_SENT
    */
    public function send()
    {
        $m = array();
        foreach ( $this->_mrecipients as $member ) {
            $m[$member->email] = $member->sname;
        }
        parent::setRecipients($m);
        return parent::send();
    }

    /**
     * Set mailing recipients
     *
     * @param <type> $members Array of Adherent objects
     *
     * @return void
     */
    public function setRecipients($members)
    {
        $m = array();
        $this->_mrecipients = array();
        $this->_unreachables = array();

        foreach ($members as $member) {
            $email = $member->email;
            if ( trim($email) != '' && self::isValidEmail($email) ) {
                if ( !in_array($member, $this->_mrecipients) ) {
                    $this->_mrecipients[] = $member;
                }
                $m[$email] = $member->sname;
            } else {
                if ( !in_array($member, $this->_unreachables) ) {
                    $this->_unreachables[] = $member;
                }
            }
        }
        parent::setRecipients($m);
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
        $forbidden = array('ordered');
        if ( !in_array($name, $forbidden) ) {
            switch($name) {
            case 'alt_message':
                return $this->cleanedHtml();
                break;
            case 'step':
                return $this->current_step;
                break;
            case 'subject':
                return $this->getSubject();
                break;
            case 'message':
                return $this->getMessage();
                break;
            case 'html':
                return $this->isHTML();
                break;
            case 'mail':
            case '_mail':
                return $this->getPhpMailer();
                break;
            case 'errors':
                return $this->getErrors();
                break;
            case 'recipients':
                return $this->_mrecipients;
                break;
            default:
                $rname = '_' . $name;
                Analog::log(
                    '[' . get_class($this) . 'Trying to get ' . $name .
                    ' renamed: ' . $rname,
                    Analog::DEBUG
                );
                return $this->$rname;
                break;
            }
        } else {
            Analog::log(
                '[' . get_class($this) . 'Unable to get ' . $name .
                ' renamed: ' . $rname,
                Analog::ERROR
            );
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
        $rname = '_' . $name;

        switch( $name ) {
        case 'subject':
            $this->setSubject($value);
            break;
        case 'message':
            $this->setMessage($value);
            break;
        case 'html':
            if ( is_bool($value) ) {
                $this->isHTML($value);
            } else {
                Analog::log(
                    '[' . get_class($this) . '] Value for field `' . $name .
                    '` should be boolean - (' . gettype($value) . ')' .
                    $value . ' given',
                    Analog::WARNING
                );
            }
            break;
        /** FIXME: remove... should no longer exists with phpMailer */
        /*case 'message':
            $this->$rname = (get_magic_quotes_gpc())
                ? stripslashes($value)
                : $value;
            break;*/
        case 'current_step':
            if ( is_int($value)
                && (   $value == self::STEP_START
                || $value == self::STEP_PREVIEW
                || $value == self::STEP_SEND
                || $value == self::STEP_SENT )
            ) {
                $this->_current_step = (int)$value;
            } else {
                Analog::log(
                    '[' . get_class($this) . '] Value for field `' . $name .
                    '` should be integer and know - (' . gettype($value) . ')' .
                    $value . ' given',
                    Analog::WARNING
                );
            }
            break;
        default:
            Analog::log(
                '[' . get_class($this) . '] Unable to set proprety `' . $name . '`',
                Analog::WARNING
            );
            break;
        }
    }
}
