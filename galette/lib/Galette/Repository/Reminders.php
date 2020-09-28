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
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-13
 */

namespace Galette\Repository;

use Galette\Entity\Reminder;
use Galette\Filters\MembersList;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * Reminders
 *
 * @category  Entity
 * @name      Reminders
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-13
 */

class Reminders
{
    public const TABLE = 'reminders';
    public const PK = 'reminder_id';

    private $selected;
    private $types;
    private $reminders;
    private $toremind;

    /**
     * Main constructor
     *
     * @param array $selected Selected types for sending
     */
    public function __construct($selected = null)
    {
        if (isset($selected) && is_array($selected)) {
            $this->selected = array_map('intval', $selected);
        } else {
            $this->selected = array(Reminder::IMPENDING, Reminder::LATE);
        }
    }

    /**
     * Load reminders
     *
     * @param Db     $zdb  Database instance
     * @param string $type Reminder type
     *
     * @return void
     */
    private function loadToRemind($zdb, $type)
    {
        $this->toremind = array();
        $select = $zdb->select(Members::TABLE, 'a');
        $select->columns([Members::PK, 'date_echeance']);
        $select->join(
            array('r' => PREFIX_DB . self::TABLE),
            'a.' . Members::PK . '=r.reminder_dest',
            array(
                'last_reminder' => new Expression('MAX(reminder_date)'),
                'reminder_type' => new Expression('MAX(reminder_type)')
            ),
            $select::JOIN_LEFT
        )->join(
            array('p' => PREFIX_DB . Members::TABLE),
            'a.parent_id=p.' . Members::PK,
            array(),
            $select::JOIN_LEFT
        )->where('(a.email_adh != \'\' OR p.email_adh != \'\')')
            ->where('a.activite_adh=true')
            ->where('a.bool_exempt_adh=false');

        if ($type === Reminder::LATE) {
            $select->where->LessThan(
                'a.date_echeance',
                date('Y-m-d', time())
            );
        } else {
            $now = new \DateTime();
            $duedate = new \DateTime();
            $duedate->modify('+1 month');
            $select->where->greaterThanOrEqualTo(
                'a.date_echeance',
                $now->format('Y-m-d')
            )->lessThan(
                'a.date_echeance',
                $duedate->format('Y-m-d')
            );
        }

        $select->group('a.id_adh');

        $results = $zdb->execute($select);

        foreach ($results as $r) {
            if ($r->reminder_type < $type) {
                //sent impending, but is now late. reset last remind.
                $r->reminder_type = $type;
                $r->last_reminder = '';
            }

            if ($r->reminder_type === null || (int)$r->reminder_type === $type) {
                $date_checked = false;

                $due_date = new \DateTime($r->date_echeance);
                $now = new \DateTime();

                switch ($type) {
                    case Reminder::IMPENDING:
                        //reminders 30 days and 7 days before
                        $first = clone $due_date;
                        $second = clone $due_date;
                        $first->modify('-1 month');
                        $second->modify('-7 day');
                        if ($now >= $first || $now >= $second) {
                            if ($r->last_reminder == '') {
                                $date_checked = true;
                            } else {
                                $last_reminder = new \DateTime($r->last_reminder);
                                if ($now >= $second && $second > $last_reminder) {
                                    $date_checked = true;
                                }
                            }
                        }
                        break;
                    case Reminder::LATE:
                        //reminders 30 days and 60 days after
                        $first = clone $due_date;
                        $second = clone $due_date;
                        $first->modify('1 month');
                        $second->modify('2 month');
                        if ($now >= $second || $now >= $first) {
                            if ($r->last_reminder === null || $r->last_reminder == '') {
                                $date_checked = true;
                            } else {
                                $last_reminder = new \DateTime($r->last_reminder);
                                if ($now >= $second && $second > $last_reminder) {
                                    $date_checked = true;
                                }
                            }
                        }
                        break;
                }

                if ($date_checked) {
                    $pk = Members::PK;
                    $this->toremind[] = $r->$pk;
                }
            } else {
                Analog::log(
                    'Reminder does not suits current requested type ' .
                    print_r($r, true),
                    Analog::DEBUG
                );
            }
        }
    }

    /**
     * Get the list of reminders
     *
     * @param Db      $zdb    Database instance
     * @param boolean $nomail Get reminders for members who do not have email address
     *
     * @return array
     */
    public function getList($zdb, $nomail = false)
    {
        $this->types = array();
        $this->reminders = array();

        $types = array();
        foreach ($this->selected as $s) {
            $this->loadToRemind($zdb, $s);

            if (count($this->toremind) > 0) {
                //and then get list
                $m = new Members();
                $members = $m->getArrayList(
                    $this->toremind,
                    null,
                    false,
                    true,
                    null,
                    false,
                    true
                );
                $this->types[$s] = $members;
            }
        }

        if (is_array($this->types)) {
            foreach ($this->types as $type => $members) {
                //load message
                if (is_array($members)) {
                    foreach ($members as $member) {
                        $reminder = new Reminder();
                        $reminder->type = $type;
                        $reminder->dest = $member;

                        $this->reminders[] = $reminder;
                    }
                }
            }
        }
        return $this->reminders;
    }
}
