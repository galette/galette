<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Repository;

use DateTime;
use Galette\Core\Db;
use Galette\Entity\Reminder;
use Galette\Filters\MembersList;
use Analog\Analog;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\IsNull;
use Laminas\Db\Sql\Predicate\Operator;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Select;
use Throwable;

/**
 * Reminders
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Reminders
{
    public const TABLE = 'reminders';
    public const PK = 'reminder_id';

    private Db $zdb;

    /** @var array<int> */
    private array $selected;
    /** @var array<int, array<string,mixed>> */
    private array $toremind;
    private StatementInterface $select_stmt;

    /**
     * Main constructor
     *
     * @param ?array<int> $selected Selected types for sending
     */
    public function __construct(?array $selected = null)
    {
        if (isset($selected)) {
            $this->selected = array_map('intval', $selected);
        } else {
            $this->selected = array(Reminder::IMPENDING, Reminder::LATE);
        }
    }

    /**
     * Load late members
     *
     * @param boolean $nomail Get reminders for members who do not have email address
     *
     * @return void
     */
    private function loadLate(bool $nomail = false): void
    {
        $now = new \DateTime();
        $filters = new MembersList();
        $filters->filter_account = Members::ACTIVE_ACCOUNT;
        $filters->membership_filter = Members::MEMBERSHIP_LATE;
        $filters->email_filter = ($nomail === false ? Members::FILTER_W_EMAIL : Members::FILTER_WO_EMAIL);

        $members = new Members($filters);
        $members_list = $members->getList(true);

        if (!count($members_list)) {
            return;
        }

        foreach ($members_list as $member) {
            //per default, no reminder is sent.
            $toremind = false;

            //for each member, check if there are existing reminders send since $limit_date
            $reminders = $this->select_stmt->execute(
                [
                    'reminder_type' => Reminder::LATE,
                    'reminder_dest' => $member->id
                ]
            );

            $due_date = new DateTime($member->rdue_date);
            //reminders 30 days and 60 days after
            $first = clone $due_date;
            $second = clone $due_date;
            $first->modify('+30 days');
            $second->modify('+60 days');

            if ($reminders->count() > 0) {
                //a reminder of this type already exists in period
                $reminder = $reminders->current();
                $last_reminder = new DateTime($reminder['reminder_date']);
                if ($now >= $second && $second > $last_reminder) {
                    $toremind = true;
                } elseif ($now > $first && $first > $last_reminder) {
                    $toremind = true;
                }
            } else {
                //no existing reminder. Just check if we exceed first reminder date to send it
                if ($now >= $first) {
                    $toremind = true;
                }
            }

            if ($toremind === true) {
                $this->toremind[Reminder::LATE][$member->id] = $member;
            }
        }
    }

    /**
     * Load late members
     *
     * @param boolean $nomail Get reminders for members who do not have email address
     *
     * @return void
     */
    private function loadImpendings(bool $nomail = false): void
    {
        $now = new \DateTime();
        $filters = new MembersList();
        $filters->filter_account = Members::ACTIVE_ACCOUNT;
        $filters->membership_filter = Members::MEMBERSHIP_NEARLY;
        $filters->email_filter = ($nomail === false ? Members::FILTER_W_EMAIL : Members::FILTER_WO_EMAIL);

        $members = new Members($filters);
        $members_list = $members->getList(true);

        if (!count($members_list)) {
            return;
        }

        foreach ($members_list as $member) {
            //per default, no reminder is sent.
            $toremind = false;

            //for each member, check if there are existing reminders send since $limit_date
            $reminders = $this->select_stmt->execute(
                [
                    'reminder_type' => Reminder::IMPENDING,
                    'reminder_dest' => $member->id
                ]
            );

            $due_date = new DateTime($member->rdue_date);
            //reminders 30 days and 7 days before
            $first = clone $due_date;
            $second = clone $due_date;
            $first->modify('-30 days');
            $second->modify('-7 days');

            if ($reminders->count() > 0) {
                //a reminder of this type already exists in period. Do not remind until date has been checked.
                $toremind = false;

                $reminder = $reminders->current();
                $last_reminder = new DateTime($reminder['reminder_date']);

                if ($now >= $second && $second > $last_reminder) {
                    //current date is after second reminder
                    $toremind = true;
                } elseif ($now >= $first && $first > $last_reminder) {
                    //current date is after first reminder
                    $toremind = true;
                }
            } else {
                //no existing reminder. Just check if we exceed first reminder date to send it
                if ($now >= $first) {
                    $toremind = true;
                }
            }

            if ($toremind === true) {
                $this->toremind[Reminder::IMPENDING][$member->id] = $member;
            }
        }
    }

    /**
     * Get limit date calculated from preferences
     *
     * @return DateTime
     * @throws Throwable
     */
    private function getLimitDate(): DateTime
    {
        global $preferences;

        $limit_now = new \DateTime();
        $limit_now->setTime(23, 59, 59);
        if ($preferences->pref_beg_membership != '') {
            //case beginning of membership
            list($j, $m) = explode('/', $preferences->pref_beg_membership);
            $limit_date = new \DateTime($limit_now->format('Y') . '-' . $m . '-' . $j);
            while ($limit_now <= $limit_date) {
                $limit_date->sub(new \DateInterval('P1Y'));
            }
        } elseif ($preferences->pref_membership_ext != '') {
            //case membership extension
            $limit_date = clone $limit_now;
            $limit_date->sub(new \DateInterval('P' . $preferences->pref_membership_ext . 'M'));
        } else {
            throw new \RuntimeException(
                'Unable to define end date; none of pref_beg_membership nor pref_membership_ext are defined!'
            );
        }

        return $limit_date;
    }

    /**
     * Get the list of reminders
     *
     * @param Db      $zdb    Database instance
     * @param boolean $nomail Get reminders for members who do not have email address
     *
     * @return array<Reminder>
     */
    public function getList(Db $zdb, bool $nomail = false): array
    {
        $this->zdb = $zdb;
        $this->toremind = [
            Reminder::IMPENDING => [],
            Reminder::LATE => []
        ];

        $limit_date = $this->getLimitDate();
        $select = $this->zdb->select(Reminder::TABLE);
        $select->where(
            [
                'reminder_type' => ':reminder_type',
                'reminder_dest' => ':reminder_dest'
            ]
        );
        $select->where->greaterThanOrEqualTo(
            'reminder_date',
            $limit_date->format('Y-m-d')
        );
        $select->order('reminder_date DESC')
            ->limit(1);

        $this->select_stmt = $this->zdb->sql->prepareStatementForSqlObject($select);

        $reminders = array();
        $m = new Members();

        if (in_array(Reminder::LATE, $this->selected)) {
            $this->loadLate($nomail);
        }

        if (in_array(Reminder::IMPENDING, $this->selected)) {
            $this->loadImpendings($nomail);
        }

        foreach ($this->toremind as $type => $members) {
            foreach ($members as $member) {
                $reminder = new Reminder();
                $reminder->type = $type;
                $reminder->dest = $member;

                $reminders[] = $reminder;
            }
        }

        return $reminders;
    }
}
