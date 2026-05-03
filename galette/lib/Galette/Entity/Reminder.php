<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Safe\DateTime;
use Galette\Features\Replacements;
use Throwable;
use Analog\Analog;
use Galette\Core\GaletteMail;
use Galette\Core\Db;
use Galette\Core\History;
use UnderflowException;
use UnexpectedValueException;

/**
 * Reminders
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property-read int      $member_id
 * @property      int      $type
 * @property      Adherent $dest
 * @property      string   $date
 */

class Reminder
{
    use Replacements;

    public const string TABLE = 'reminders';
    public const string PK = 'reminder_id';

    private int $id;
    private int $type;
    private Adherent $dest;
    private string $date;
    private bool $success = false;
    private bool $nomail;
    private string $comment;
    private string $msg;

    public const int IMPENDING = 1;
    public const int LATE = 2;

    /**
     * Main constructor
     *
     * @param ArrayObject<string,int|string>|int|null $args Arguments
     */
    public function __construct(ArrayObject|int|null $args = null)
    {
        if ($args !== null) {
            if (is_int($args)) {
                $this->load($args);
            } elseif ($args instanceof ArrayObject) {
                $this->loadFromRS($args);
            }
        }
    }

    /**
     * Load a reminder from its id
     *
     * @param int $id Identifier
     */
    private function load(int $id): void
    {
        global $zdb;
        try {
            $select = $zdb->select(self::TABLE);
            $select->limit(1)
                ->where([self::PK => $id]);

            $results = $zdb->execute($select);
            $this->loadFromRS($results->current());
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading reminder #' . $id . "Message:\n"
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load reminder from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     */
    private function loadFromRS(ArrayObject $rs): void
    {
        global $zdb;

        try {
            $pk = self::PK;
            $this->id = (int)$rs->$pk;
            $this->type = (int)$rs->reminder_type;
            $this->dest = new Adherent($zdb, (int)$rs->reminder_dest);
            $this->date = $rs->reminder_date;
            $this->success = $rs->reminder_success == 1;
            $this->nomail = $rs->reminder_nomail == 1;
            $this->comment = $rs->reminder_comment;
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ': incorrect ResultSet. Error: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Store reminder in database and history
     *
     * @param Db $zdb Database instance
     */
    private function store(Db $zdb): bool
    {
        $now = new DateTime();
        $data = [
            'reminder_type'     => $this->type,
            'reminder_dest'     => $this->dest->id,
            'reminder_date'     => $now->format('Y-m-d'),
            'reminder_success'  => ($this->success)
                ? true
                : ($zdb->isPostgres() ? 'false' : 0),
            'reminder_nomail'   => ($this->nomail)
                ? true
                : ($zdb->isPostgres() ? 'false' : 0)
        ];
        try {
            $insert = $zdb->insert(self::TABLE);
            $insert->values($data);

            $add = $zdb->execute($insert);
            if ($add->count() <= 0) {
                Analog::log('Reminder not stored!', Analog::ERROR);
                return false;
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing reminder: ' . $e->getMessage()
                . "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Was reminder sent successfully?
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Did member had an email when reminder was sent?
     */
    public function hasMail(): bool
    {
        return !$this->nomail;
    }

    /**
     * Send the reminder
     *
     * @param Texts   $texts Text object
     * @param History $hist  History
     * @param Db      $zdb   Database instance
     */
    public function send(Texts $texts, History $hist, Db $zdb): bool
    {
        global $preferences;

        $this->success = false;

        // When late, the number of days expired is required, not the number of days remaining.
        $type_name = 'late';
        $days_remaining = $this->dest->days_remaining + 1;
        if ($this->type === self::IMPENDING) {
            $type_name = 'impending';
            $days_remaining = $this->dest->days_remaining;
        }

        if ($this->hasMail()) {
            $texts->setMember($this->dest)
                ->setNoContribution();

            $texts->getTexts(
                $type_name . 'duedate',
                $this->dest->language
            );

            $mail = new GaletteMail($preferences);
            $mail->setSubject($texts->getSubject());
            $mail->setRecipients(
                [
                    $this->dest->getEmail() => $this->dest->sname
                ]
            );
            $mail->setMessage($texts->getBody());
            $sent = $mail->send();

            $details = sprintf(
                //TRANS first param is name, second email, third days interval
                _T('%1$s <%2$s> (%3$s days)'),
                $this->dest->sname,
                $this->dest->getEmail(),
                (string)$days_remaining
            );

            if ($sent == GaletteMail::MAIL_SENT) {
                $this->success = true;
                $msg = '';
                if ($type_name == 'late') {
                    $msg = _T("Sent reminder email for late membership");
                } else {
                    $msg = _T("Sent reminder email for impending membership");
                }
                $this->msg = $details;
                $hist->add($msg, $details);
            } else {
                if ($type_name == 'late') {
                    $msg = _T("A problem happened while sending late membership email");
                } else {
                    $msg = _T("A problem happened while sending impending membership email");
                }
                $this->msg = $details;
                $hist->add($msg, $details);
            }
        } else {
            $this->nomail = true;
            $str = str_replace(
                '%membership',
                $type_name,
                _T("Unable to send %membership reminder (no email address).")
            );
            $details = sprintf(
                //TRANS: first parameter is name, second the id, this days interval
                _T('%1$s (#%2$s - %3$s days)'),
                $this->dest->sname,
                (string)$this->dest->id,
                (string)$days_remaining
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
     */
    public function getMessage(): string
    {
        return $this->msg;
    }

    /**
     * Getter
     *
     * @param string $name Property name
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'member_id' => $this->dest->id,
            'type', 'date' => $this->$name,
            'comment' => $this->comment,
            default => throw new \RuntimeException(
                sprintf(
                    'Unable to get property "%s::%s"!',
                    static::class,
                    $name
                )
            ),
        };
    }

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'member_id', 'type', 'date', 'comment' => true,
            default => false,
        };
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'type':
                if (
                    $value === self::IMPENDING
                    || $value === self::LATE
                ) {
                    $this->type = $value;
                } else {
                    throw new UnexpectedValueException(
                        'Unknown type!'
                    );
                }
                break;
            case 'dest':
                if (isset($this->type) && $value instanceof Adherent) {
                    $this->dest = $value;
                    if ($value->getEmail() != '') {
                        $this->nomail = false;
                    }
                } elseif (!$value instanceof Adherent) {
                    throw new UnexpectedValueException(
                        'Please provide a member object.'
                    );
                } else {
                    throw new UnderflowException(
                        'Please set reminder type first.'
                    );
                }
                break;
            default:
                Analog::log(
                    'Unable to set property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }
}
