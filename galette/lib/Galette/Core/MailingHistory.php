<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
 *
 * PHP version 5
 *
 * Copyright © 2009-2023 The Galette Team
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2011-08-27
 */

namespace Galette\Core;

use ArrayObject;
use Laminas\Db\Sql\Select;
use Throwable;
use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Filters\MailingsList;
use Laminas\Db\Sql\Expression;

/**
 * Mailing features
 *
 * @category  Core
 * @name      MailingHistory
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7dev - 2011-08-27
 *
 * @property MailingsList $filters
 */
class MailingHistory extends History
{
    public const TABLE = 'mailing_history';
    public const PK = 'mailing_id';

    public const FILTER_DC_SENT = 0;
    public const FILTER_SENT = 1;
    public const FILTER_NOT_SENT = 2;

    private ?Mailing $mailing = null;
    private int $id;
    private string $date;
    private string $subject;
    private string $message;
    private array $recipients;
    private int $sender;
    private ?string $sender_name;
    private ?string $sender_address;
    private bool $sent = false;

    /**
     * Default constructor
     *
     * @param Db                $zdb         Database
     * @param Login             $login       Login
     * @param Preferences       $preferences Preferences
     * @param MailingsList|null $filters     Filtering
     * @param Mailing|null      $mailing     Mailing
     */
    public function __construct(Db $zdb, Login $login, Preferences $preferences, MailingsList $filters = null, Mailing $mailing = null)
    {
        parent::__construct($zdb, $login, $preferences, $filters);
        $this->mailing = $mailing;
    }

    /**
     * Get the entire history list
     *
     * @return array
     */
    public function getHistory(): array
    {
        try {
            $select = $this->zdb->select($this->getTableName(), 'a');
            $select->join(
                array('b' => PREFIX_DB . Adherent::TABLE),
                'a.mailing_sender=b.' . Adherent::PK,
                array('nom_adh', 'prenom_adh'),
                $select::JOIN_LEFT
            );
            $this->buildWhereClause($select);
            $select->order($this->buildOrderClause());
            $this->proceedCount($select);
            //add limits to retrieve only relevant rows
            $this->filters->setLimits($select);
            $results = $this->zdb->execute($select);

            $ret = array();
            foreach ($results as $r) {
                if ($r['mailing_sender'] !== null && $r['mailing_sender_name'] === null) {
                    $r['mailing_sender_name']
                        = Adherent::getSName($this->zdb, $r['mailing_sender']);
                }

                $recipients = [];
                if ($r['mailing_recipients'] != null) {
                    //FIXME: error suppression with @ must be removed, see https://bugs.galette.eu/issues/1744
                    $recipients = @unserialize($r['mailing_recipients']);
                }
                $r['mailing_recipients'] = $recipients;

                $attachments = 0;
                if (file_exists(GALETTE_ATTACHMENTS_PATH . $r[self::PK])) {
                    $rdi = new \RecursiveDirectoryIterator(
                        GALETTE_ATTACHMENTS_PATH . $r[self::PK],
                        \FilesystemIterator::SKIP_DOTS
                    );
                    $contents = new \RecursiveIteratorIterator(
                        $rdi,
                        \RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($contents as $path) {
                        if ($path->isFile()) {
                            $attachments++;
                        }
                    }
                }
                $r['attachments'] = $attachments;
                $ret[] = $r;
            }
            return $ret;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to get history. | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @return array SQL ORDER clauses
     */
    protected function buildOrderClause(): array
    {
        $order = array();

        switch ($this->filters->orderby) {
            case MailingsList::ORDERBY_DATE:
                $order[] = 'mailing_date ' . $this->filters->ordered;
                break;
            case MailingsList::ORDERBY_SENDER:
                $order[] = 'mailing_sender ' . $this->filters->ordered;
                break;
            case MailingsList::ORDERBY_SUBJECT:
                $order[] = 'mailing_subject ' . $this->filters->ordered;
                break;
            case MailingsList::ORDERBY_SENT:
                $order[] = 'mailing_sent ' . $this->filters->ordered;
                break;
        }

        return $order;
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function buildWhereClause(Select $select): void
    {
        try {
            if ($this->filters->start_date_filter != null) {
                $d = new \DateTime($this->filters->raw_start_date_filter);
                $select->where->greaterThanOrEqualTo(
                    'mailing_date',
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->end_date_filter != null) {
                $d = new \DateTime($this->filters->raw_end_date_filter);
                $select->where->lessThanOrEqualTo(
                    'mailing_date',
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->sender_filter != null && $this->filters->sender_filter != '0') {
                $sender = $this->filters->sender_filter;
                if ($sender == '-1') {
                    $select->where('mailing_sender IS NULL');
                } else {
                    $select->where->equalTo(
                        'mailing_sender',
                        $sender
                    );
                }
            }

            switch ($this->filters->sent_filter) {
                case self::FILTER_SENT:
                    $select->where('mailing_sent = true');
                    break;
                case self::FILTER_NOT_SENT:
                    $select->where('mailing_sent = false');
                    break;
                case self::FILTER_DC_SENT:
                    //nothing to do here.
                    break;
            }


            if ($this->filters->subject_filter != '') {
                $token = $this->zdb->platform->quoteValue(
                    '%' . strtolower($this->filters->subject_filter) . '%'
                );

                $select->where(
                    'LOWER(mailing_subject) LIKE ' .
                    $token
                );
            }
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count history entries from the query
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function proceedCount(Select $select): void
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::JOINS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->columns(
                array(
                    self::PK => new Expression('COUNT(' . self::PK . ')')
                )
            );

            $results = $this->zdb->execute($countSelect);
            $result = $results->current();

            $k = self::PK;
            $this->count = $result->$k;
            $this->filters->setCounter($this->count);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count history | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Load mailing from an existing one
     *
     * @param Db      $zdb     Database instance
     * @param integer $id      Model identifier
     * @param Mailing $mailing Mailing object
     * @param boolean $new     True if we create a 'new' mailing,
     *                         false otherwise (from preview for example)
     *
     * @return boolean
     */
    public static function loadFrom(Db $zdb, int $id, Mailing $mailing, bool $new = true): bool
    {
        try {
            $select = $zdb->select(self::TABLE);
            $select->where(['mailing_id' => $id]);

            $results = $zdb->execute($select);
            /** @var ArrayObject $result */
            $result = $results->current();

            return $mailing->loadFromHistory($result, $new);
        } catch (Throwable $e) {
            Analog::log(
                'Unable to load mailing model #' . $id . ' | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Store a mailing in the history
     *
     * @param boolean $sent Defaults to false
     *
     * @return boolean
     */
    public function storeMailing(bool $sent = false): bool
    {
        if ($this->mailing instanceof Mailing) {
            if ($this->mailing->sender_name != null) {
                $this->sender_name = $this->mailing->getSenderName();
                $this->sender_address = $this->mailing->getSenderAddress();
            }
            $this->sender = $this->login->id;
            $this->subject = $this->mailing->subject;
            $this->message = $this->mailing->message;
            $this->recipients = $this->mailing->recipients;
            $this->sent = $sent;
            $this->date = date('Y-m-d H:i:s');
            if (!$this->mailing->existsInHistory()) {
                $this->store();
                $this->mailing->id = $this->id;
                $this->mailing->moveAttachments($this->id);
            } else {
                if ($this->mailing->tmp_path !== false) {
                    //attachments are still in a temporary path, move them
                    $this->mailing->moveAttachments($this->id ?? $this->mailing->history_id);
                }
                //existing stored mailing. Just update row.
                $this->update();
            }
            return true;
        } else {
            Analog::log(
                '[' . __METHOD__ .
                '] Mailing should be an instance of Mailing',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Update in the database
     *
     * @return boolean
     */
    public function update(): bool
    {
        try {
            $_recipients = array();
            if ($this->recipients != null) {
                foreach ($this->recipients as $_r) {
                    $_recipients[$_r->id] = $_r->sname . ' <' . $_r->email . '>';
                }
            }

            $sender = ($this->sender === 0) ?
                new Expression('NULL') : $this->sender;
            $sender_name = ($this->sender_name === null) ?
                new Expression('NULL') : $this->sender_name;
            $sender_address = ($this->sender_address === null) ?
                new Expression('NULL') : $this->sender_address;

            $values = array(
                'mailing_sender'            => $sender,
                'mailing_sender_name'       => $sender_name,
                'mailing_sender_address'    => $sender_address,
                'mailing_subject'           => $this->subject,
                'mailing_body'              => $this->message,
                'mailing_date'              => $this->date,
                'mailing_recipients'        => serialize($_recipients),
                'mailing_sent'              => ($this->sent) ?
                    true :
                    ($this->zdb->isPostgres() ? 'false' : 0)
            );

            $update = $this->zdb->update(self::TABLE);
            $update->set($values);
            $update->where([self::PK => $this->mailing->history_id]);
            $this->zdb->execute($update);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurend updating Mailing | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Store in the database
     *
     * @return boolean
     */
    public function store(): bool
    {
        try {
            $_recipients = array();
            if ($this->recipients != null) {
                foreach ($this->recipients as $_r) {
                    $_recipients[$_r->id] = $_r->sname . ' <' . $_r->email . '>';
                }
            }

            $sender = null;
            if ($this->sender === 0) {
                $sender = new Expression('NULL');
            } else {
                $sender = $this->sender;
            }
            $sender_name = ($this->sender_name === null) ?
                new Expression('NULL') : $this->sender_name;
            $sender_address = ($this->sender_address === null) ?
                new Expression('NULL') : $this->sender_address;

            $values = array(
                'mailing_sender'            => $sender,
                'mailing_sender_name'       => $sender_name,
                'mailing_sender_address'    => $sender_address,
                'mailing_subject'           => $this->subject,
                'mailing_body'              => $this->message,
                'mailing_date'              => $this->date,
                'mailing_recipients'        => serialize($_recipients),
                'mailing_sent'              => ($this->sent) ?
                    true :
                    ($this->zdb->isPostgres() ? 'false' : 0)
            );

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values($values);
            $this->zdb->execute($insert);

            $this->id = $this->zdb->getLastGeneratedValue($this);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing Mailing | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove specified entries
     *
     * @param integer|array $ids  Mailing history entries identifiers
     * @param History       $hist History instance
     *
     * @return boolean
     */
    public function removeEntries(int|array $ids, History $hist): bool
    {
        $list = is_array($ids) ? $ids : [$ids];

        try {
            foreach ($list as $id) {
                $mailing = new Mailing($this->preferences, [], $id);
                $mailing->removeAttachments();
            }

            $this->zdb->connection->beginTransaction();

            //delete members
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where->in(self::PK, $list);
            $this->zdb->execute($delete);

            //commit all changes
            $this->zdb->connection->commit();

            //add an history entry
            $hist->add(
                _T("Delete mailing entries")
            );

            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to delete selected mailing history entries |' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get table's name
     *
     * @param boolean $prefixed Whether table name should be prefixed
     *
     * @return string
     */
    protected function getTableName(bool $prefixed = false): string
    {
        if ($prefixed === true) {
            return PREFIX_DB . self::TABLE;
        } else {
            return self::TABLE;
        }
    }

    /**
     * Get table's PK
     *
     * @return string
     */
    protected function getPk(): string
    {
        return self::PK;
    }

    /**
     * Get count for current query
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
