<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Reminders
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-11
 */

namespace Galette\Entity;

use Analog\Analog;
use \Galette\Core\GaletteMail;

/**
 * Reminders
 *
 * @category  Entity
 * @name      Reminder
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-11
 */

class Reminder
{
    const TABLE = 'reminders';
    const PK = 'reminder_id';

    private $_id;
    private $_type;
    private $_dest;
    private $_date;
    private $_success;
    private $_nomail;
    private $_comment;
    private $_replaces;
    private $_msg;

    const IMPENDING = 1;
    const LATE = 2;

    /**
     * Main constructor
     *
     * @param mixed $args Arguments
     */
    public function __construct($args = null)
    {
        if ( $args !== null ) {
            if ( is_int($args) ) {
                $this->_load($args);
            } else if ( is_object($args) ) {
                $this->_loadFromRs($args);
            } else {
                Analog::log(
                    __METHOD__ . ': unknonw arg',
                    Analog::WARNING
                );
            }
        }
    }

    /**
     * Load a reminder from its id
     *
     * @param int $id Identifier
     *
     * @return void
     */
    private function _load($id)
    {
        global $zdb;
        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->limit(1)->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $id);

            $res = $select->query()->fetchAll();
            $this->_loadFromRs($res[0]);
        } catch ( \Exception $e ) {
            Analog::log(
                'An error occured loading reminder #' . $id . "Message:\n" .
                $e->getMessage() . "\nQuery was:\n" . $select->__toString(),
                Analog::ERROR
            );
        }
    }

    /**
     * Load reminder from a db ResultSet
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function _loadFromRs($rs)
    {
        try {
            $pk = self::PK;
            $this->_id = $rs->$pk;
            $this->_type = $rs->reminder_type;
            $this->_dest = new Adherent((int)$rs->reminder_dest);
            $this->_date = $rs->reminder_date;
            $this->_success = $rs->reminder_success;
            $this->_nomail = $rs->reminder_nomail;
            $this->_comment = $rs->reminder_comment;
        } catch ( \Exception $e ) {
            Analog::log(
                __METHOD__ . ': incorrect ResultSet. Error: ' .$e->getMessage(),
                Analog::ERROR
            );
            Analog::log(
                print_r($rs, true),
                Analog::INFO
            );
        }
    }

    /**
     * Store reminder in database and history
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    private function _store($zdb)
    {
        $data = array(
            'reminder_type'     => $this->_type,
            'reminder_dest'     => $this->_dest->id,
            'reminder_date'     => new \Zend_Db_Expr('NOW()'),
            'reminder_success'  => ($this->_success) ? true : 'false',
            'reminder_nomail'   => ($this->_nomail) ? true : 'false'
        );
        try {
            $add = $zdb->db->insert(
                PREFIX_DB . self::TABLE,
                $data
            );
            if ( !$add > 0 ) {
                Analog::log('Reminder not stored!', Analog::ERROR);
                return false;
            }
            return true;
        } catch ( \Exception $e ) {
            Analog::log(
                'An error occured storing reminder: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Was reminder sent successfully?
     *
     * @return boolean
     */
    public function isSuccess()
    {
        return $this->_success;
    }

    /**
     * Did member had a mail when reminder was sent?
     *
     * @return boolean
     */
    public function hasMail()
    {
        return !$this->_nomail;
    }

    /**
     * Send the reminder
     *
     * @param Texts   $texts Text object
     * @param History $hist  History
     * @param Db      $zdb   Database instance
     *
     * @return boolean
     */
    public function send($texts, $hist, $zdb)
    {
        $type_name = 'late';
        if ( $this->_type === self::IMPENDING ) {
            $type_name = 'impending';
        }

        if ( $this->hasMail() ) {
            $texts->setReplaces($this->_replaces);

            $texts->getTexts(
                $type_name . 'duedate',
                $this->_dest->language
            );

            $mail = new GaletteMail();
            $mail->setSubject($texts->getSubject());
            $mail->setRecipients(
                array(
                    $this->_dest->email => $this->_dest->sname
                )
            );
            $mail->setMessage($texts->getBody());
            $sent = $mail->send();

            $details = str_replace(
                array(
                    '%name',
                    '%mail',
                    '%days'
                ),
                array(
                    $this->_dest->sname,
                    $this->_dest->email,
                    $this->_dest->days_remaining
                ),
                _T("%name <%mail> (%days days)")
            );

            if ( $sent == GaletteMail::MAIL_SENT ) {
                $this->_success = true;
                $msg = '';
                if ( $type_name == 'late' ) {
                    $msg = _T("Sent reminder mail for late membership");
                } else {
                    $msg = _T("Sent reminder mail for impending membership");
                }
                $this->_msg = $details;
                $hist->add($msg, $details);
            } else {
                $this->_success = false;
                if ( $type_name == 'late' ) {
                    $msg = _T("A problem happened while sending late membership mail");
                } else {
                    $msg = _T("A problem happened while sending impending membership mail");
                }
                $this->_msg = $details;
                $hist->add($str, $details);
            }
        } else {
            $this->_success = false;
            $this->_nomail = true;
            $str = str_replace(
                '%membership',
                $type_name,
                _T("Unable to send %membership reminder (no mail address).")
            );
            $details = str_replace(
                array(
                    '%name',
                    '%id',
                    '%days'
                ),
                array(
                    $this->_dest->sname,
                    $this->_dest->id,
                    $this->_dest->days_remaining
                ),
                _T("%name (#%id - %days days)")
            );
            $hist->add($str, $details);
            $this->_msg = $this->_dest->sname;
        }
        //store reminder in database
        $this->_store($zdb);
        return $this->_success;
    }

    /**
     * Retrieve message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->_msg;
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $rname = '_' . $name;
        switch ( $name ) {
        case 'member_id':
            return $this->_dest->id;
            break;
        default:
            Analog::log(
                'Unable to get Reminder property ' . $name,
                Analog::WARNING
            );
            break;
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $rname = '_' . $name;
        switch ( $name ) {
        case 'type':
            if ( $value === self::IMPENDING
                || $value === self::LATE
            ) {
                $this->_type = $value;
            } else {
                throw new \UnexpectedValueException(
                    'Unknown type!'
                );
            }
            break;
        case 'dest':
            if ( $this->_type !== null && $value instanceof Adherent ) {
                $this->_dest = $value;
                $this->_replaces['login_adh'] = $value->login;
                $this->_replaces['name_adh'] = custom_html_entity_decode($value->sname);
                $this->_replaces['firstname_adh'] = custom_html_entity_decode($value->surname);
                $this->_replaces['lastname_adh'] = custom_html_entity_decode($value->name);
                if ( $value->email != '' ) {
                    $this->_nomail = false;
                }
                if ( $this->_type === self::LATE ) {
                    $this->_replaces['days_expired'] = $value->days_remaining *-1;
                }
                if ( $this->_type === self::IMPENDING ) {
                    $this->_replaces['days_remaining'] = $value->days_remaining;
                }
            } else {
                if ( !$value instanceof Adherent ) {
                    throw new \UnexpectedValueException(
                        'Please provide a member object.'
                    );
                } else {
                    throw new \UnderflowException(
                        'Please set reminder type first.'
                    );
                }
            }
            break;
        default:
            Analog::log(
                'Unable to set property ' .$name,
                Analog::WARNING
            );
            break;
        }
    }
}
