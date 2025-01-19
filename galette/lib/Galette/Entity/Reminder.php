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

namespace Galette\Entity;

use ArrayObject;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Galette\Features\Replacements;
use Throwable;
use Analog\Analog;
use Galette\Core\GaletteMail;
use Galette\Core\Db;
use Galette\Core\History;

/**
 * Reminders
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property-read integer $member_id
 * @property integer $type
 * @property Adherent $dest
 * @property string $date
 */

#[ORM\Entity]
#[ORM\Table(name: 'orm_reminders')]
class Reminder
{
    use Replacements;

    public const TABLE = 'reminders';
    public const PK = 'reminder_id';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: self::PK, type: Types::INTEGER, options: ['unsigned' => true])]
    private int $id;
    #[ORM\Column(name: 'reminder_type', type: Types::INTEGER)]
    private int $type;
    #[ORM\ManyToOne(targetEntity: Adherent::class)]
    #[ORM\JoinColumn(
        name: 'reminder_dest',
        referencedColumnName: Adherent::PK,
        nullable: false,
        onDelete: 'restrict',
        options: [
            'unsigned' => true
        ]
    )]
    private Adherent $dest;
    #[ORM\Column(name: 'reminder_date', type: Types::DATETIME_MUTABLE)]
    private string $date;
    #[ORM\Column(name: 'reminder_success', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $success = false;
    #[ORM\Column(name: 'reminder_nomail', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $nomail;
    #[ORM\Column(name: 'reminder_comment', type: Types::TEXT)]
    private string $comment;
    private string $msg;

    public const IMPENDING = 1;
    public const LATE = 2;

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
     *
     * @return void
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
                'An error occurred loading reminder #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load reminder from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
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
     *
     * @return boolean
     */
    private function store(Db $zdb): bool
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
            if (!($add->count() > 0)) {
                Analog::log('Reminder not stored!', Analog::ERROR);
                return false;
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing reminder: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Was reminder sent successfully?
     *
     * @return boolean
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Did member had an email when reminder was sent?
     *
     * @return boolean
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
     *
     * @return boolean
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
                array(
                    $this->dest->getEmail() => $this->dest->sname
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
                    $this->dest->getEmail(),
                    (string)$days_remaining
                ),
                _T("%name <%mail> (%days days)")
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
            $details = str_replace(
                array(
                    '%name',
                    '%id',
                    '%days'
                ),
                array(
                    $this->dest->sname,
                    (string)$this->dest->id,
                    (string)$days_remaining
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
    public function getMessage(): string
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
    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'member_id':
                return $this->dest->id;
            case 'type':
            case 'date':
                return $this->$name;
            case 'comment':
                return $this->comment;
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                __CLASS__,
                $name
            )
        );
    }

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        switch ($name) {
            case 'member_id':
            case 'type':
            case 'date':
            case 'comment':
                return true;
        }
        return false;
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
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
                    throw new \UnexpectedValueException(
                        'Unknown type!'
                    );
                }
                break;
            case 'dest':
                if ($this->type !== null && $value instanceof Adherent) {
                    $this->dest = $value;

                    if ($value->getEmail() != '') {
                        $this->nomail = false;
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
                    'Unable to set property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }
}
