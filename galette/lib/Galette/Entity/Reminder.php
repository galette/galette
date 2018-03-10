<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Reminders
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-11
 */

namespace Galette\Entity;

use Analog\Analog;
use Zend\Db\Sql\Expression;
use Galette\Core\GaletteMail;
use Galette\Entity\Texts;
use Galette\Core\Db;
use Galette\Core\History;

/**
 * Reminders
 *
 * @category  Entity
 * @name      Reminder
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-11
 */

class Reminder
{
    const TABLE = 'reminders';
    const PK = 'reminder_id';

    private $id;
    private $type;
    private $dest;
    private $date;
    private $success;
    private $nomail;
    private $comment;
    private $replaces;
    private $msg;

    const IMPENDING = 1;
    const LATE = 2;

    /**
     * Main constructor
     *
     * @param mixed $args Arguments
     */
    public function __construct($args = null)
    {
        if ($args !== null) {
            if (is_int($args)) {
                $this->load($args);
            } elseif (is_object($args)) {
                $this->loadFromRs($args);
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
    private function load($id)
    {
        global $zdb;
        try {
            $select = $zdb->select(self::TABLE);
            $select->limit(1)
                ->where(self::PK . ' = ' . $id);

            $results = $zdb->execute($select);
            $this->loadFromRs($results->current());
        } catch (\Exception $e) {
            Analog::log(
                'An error occured loading reminder #' . $id . "Message:\n" .
                $e->getMessage(),
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
    private function loadFromRs($rs)
    {
        global $zdb;

        try {
            $pk = self::PK;
            $this->id = $rs->$pk;
            $this->type = $rs->reminder_type;
            $this->dest = new Adherent($zdb, (int)$rs->reminder_dest);
            $this->date = $rs->reminder_date;
            $this->success = $rs->reminder_success;
            $this->nomail = $rs->reminder_nomail;
            $this->comment = $rs->reminder_comment;
        } catch (\Exception $e) {
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
    private function store($zdb)
    {
        $now = new \DateTime();
        $data = array(
            'reminder_type'     => $this->type,
            'reminder_dest'     => $this->dest->id,
            'reminder_date'     => $now->format('Y-m-d'),
            'reminder_success'  => ($this->success) ?
                true :
                ($zdb->isPostgres() ? 'false' : 0),
            'reminder_nomail'   => ($this->nomail) ?
                true :
                ($zdb->isPostgres() ? 'false' : 0)
        );
        try {
            $insert = $zdb->insert(self::TABLE);
            $insert->values($data);

            $add = $zdb->execute($insert);
            if (!$add->count() > 0) {
                Analog::log('Reminder not stored!', Analog::ERROR);
                return false;
            }
            return true;
        } catch (\Exception $e) {
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
        return $this->success;
    }

    /**
     * Did member had a mail when reminder was sent?
     *
     * @return boolean
     */
    public function hasMail()
    {
        return !$this->nomail;
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
    public function send(Texts $texts, History $hist, Db $zdb)
    {
        global $preferences;

        $type_name = 'late';
        if ($this->type === self::IMPENDING) {
            $type_name = 'impending';
        }

        if ($this->hasMail()) {
            $texts->setReplaces($this->replaces);

            $texts->getTexts(
                $type_name . 'duedate',
                $this->dest->language
            );

            $mail = new GaletteMail($preferences);
            $mail->setSubject($texts->getSubject());
            $mail->setRecipients(
                array(
                    $this->dest->email => $this->dest->sname
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
                    $this->dest->sname,
                    $this->dest->email,
                    $this->dest->days_remaining
                ),
                _T("%name <%mail> (%days days)")
            );

            if ($sent == GaletteMail::MAIL_SENT) {
                $this->success = true;
                $msg = '';
                if ($type_name == 'late') {
                    $msg = _T("Sent reminder mail for late membership");
                } else {
                    $msg = _T("Sent reminder mail for impending membership");
                }
                $this->msg = $details;
                $hist->add($msg, $details);
            } else {
                $this->success = false;
                if ($type_name == 'late') {
                    $msg = _T("A problem happened while sending late membership mail");
                } else {
                    $msg = _T("A problem happened while sending impending membership mail");
                }
                $this->msg = $details;
                $hist->add($str, $details);
            }
        } else {
            $this->success = false;
            $this->nomail = true;
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
                    $this->dest->sname,
                    $this->dest->id,
                    $this->dest->days_remaining
                ),
                _T("%name (#%id - %days days)")
            );
            $hist->add($str, $details);
            $this->msg = $this->dest->sname;
        }
        //store reminder in database
        $this->store($zdb);
        return $this->success;
    }

    /**
     * Retrieve message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->msg;
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
        switch ($name) {
            case 'member_id':
                return $this->dest->id;
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
        switch ($name) {
            case 'type':
                if ($value === self::IMPENDING
                    || $value === self::LATE
                ) {
                    $this->type = $value;
                } else {
                    throw new \UnexpectedValueException(
                        'Unknown type!'
                    );
                }
                break;
            case 'dest':
                if ($this->type !== null && $value instanceof Adherent) {
                    $this->dest = $value;
                    $this->replaces['login_adh'] = $value->login;
                    $this->replaces['name_adh'] = custom_html_entity_decode($value->sname);
                    $this->replaces['firstname_adh'] = custom_html_entity_decode($value->surname);
                    $this->replaces['lastname_adh'] = custom_html_entity_decode($value->name);
                    if ($value->email != '') {
                        $this->nomail = false;
                    }
                    if ($this->type === self::LATE) {
                        $this->replaces['days_expired'] = $value->days_remaining *-1;
                    }
                    if ($this->type === self::IMPENDING) {
                        $this->replaces['days_remaining'] = $value->days_remaining;
                    }
                } else {
                    if (!$value instanceof Adherent) {
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
