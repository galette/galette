<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use ArrayObject;
use Galette\Entity\Adherent;
use Laminas\Db\Sql\Expression;
use Safe\DateTime;
use Throwable;

/**
 * Persistent mass mailing queue.
 *
 * A mailing stored in the history (see MailingHistory) can be split into one
 * row per recipient in this queue, then drained progressively while respecting
 * the configured limits (batch size, delay, hourly and daily caps). Draining
 * is resumable: it can be run interactively through AJAX or unattended from a
 * cron job / console command.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailingQueue
{
    public const string TABLE = 'mailing_queue';
    public const string PK = 'mailing_queue_id';

    public const int STATUS_PENDING = 0;
    public const int STATUS_SENT = 1;
    public const int STATUS_FAILED = 2;

    /** Maximum number of attempts before a recipient is marked as failed */
    public const int MAX_ATTEMPTS = 3;

    /**
     * Default constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Preferences $preferences
    ) {
    }

    /**
     * Add the recipients of a stored mailing to the sending queue.
     *
     * @param int                  $mailing_id Mailing history id
     * @param array<int, Adherent> $recipients Reachable recipients (Adherent objects)
     *
     * @return int Number of queued recipients
     */
    public function enqueue(int $mailing_id, array $recipients): int
    {
        $count = 0;
        $now = date('Y-m-d H:i:s');
        try {
            foreach ($recipients as $member) {
                $email = $member->getEmail();
                if (trim($email) === '') {
                    continue;
                }
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values(
                    [
                        'mailing_id'      => $mailing_id,
                        'recipient_id'    => $member->id,
                        'recipient_email' => $email,
                        'recipient_name'  => $member->sname,
                        'status'          => self::STATUS_PENDING,
                        'attempts'        => 0,
                        'scheduled_at'    => $now
                    ]
                );
                $this->zdb->execute($insert);
                $count++;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to enqueue mailing #' . $mailing_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }

        return $count;
    }

    /**
     * Process a single batch of the queue, respecting the configured limits.
     *
     * At most one message (a BCC group of up to the effective batch size) is
     * sent per call, so callers stay responsive: the delay between messages is
     * applied by the caller (client-side for the AJAX drainer, sleep() for the
     * cron drainer).
     *
     * @param ?int $only_mailing_id Restrict processing and stats to this mailing
     *
     * @return array<string, int|bool> Progress information: total, remaining,
     *   sent_total, failed_total, batch_sent, batch_failed, done, rate_limited
     */
    public function processBatch(?int $only_mailing_id = null): array
    {
        $batch_size = (int)$this->preferences->pref_mail_batch_size;
        $hourly = (int)$this->preferences->pref_mail_hourly_limit;
        $daily = (int)$this->preferences->pref_mail_daily_limit;

        $pending = $this->countByStatus(self::STATUS_PENDING, $only_mailing_id);
        if ($pending === 0) {
            return $this->progress($only_mailing_id, false);
        }

        //how many recipients may still be sent right now?
        $allowed = $batch_size > 0 ? $batch_size : $pending;
        if ($hourly > 0) {
            $allowed = min($allowed, max(0, $hourly - $this->countSentSince('-1 hour')));
        }
        if ($daily > 0) {
            $allowed = min($allowed, max(0, $daily - $this->countSentSince('-1 day')));
        }

        if ($allowed <= 0) {
            //rate limit reached, nothing can be sent for now
            return $this->progress($only_mailing_id, true);
        }

        $mailing_id = $this->getNextPendingMailingId($only_mailing_id);
        if ($mailing_id === null) {
            return $this->progress($only_mailing_id, false);
        }

        $rows = $this->getPendingRows($mailing_id, $allowed);
        $result = $this->sendRows($mailing_id, $rows);
        $this->maybeMarkMailingSent($mailing_id);

        return $this->progress(
            $only_mailing_id,
            false,
            $result['sent'],
            $result['failed']
        );
    }

    /**
     * Get current queue statistics, without sending anything.
     *
     * @param ?int $mailing_id Restrict stats to this mailing
     *
     * @return array<string, int|bool>
     */
    public function getStats(?int $mailing_id = null): array
    {
        return $this->progress($mailing_id, false);
    }

    /**
     * Send a chunk of queued recipients belonging to the same mailing.
     *
     * @param int                                    $mailing_id Mailing history id
     * @param array<int, ArrayObject<string, mixed>> $rows       Pending queue rows
     *
     * @return array{sent: int, failed: int}
     */
    private function sendRows(int $mailing_id, array $rows): array
    {
        $sent = 0;
        $failed = 0;

        $mailing = $this->loadMailing($mailing_id);
        if ($mailing === null) {
            //mailing has disappeared, give up on these rows
            foreach ($rows as $row) {
                $this->markRow((int)$row->mailing_queue_id, self::STATUS_FAILED, 'Mailing not found');
                $failed++;
            }
            return ['sent' => $sent, 'failed' => $failed];
        }

        //build recipients for this chunk from the stored member ids
        $members = [];
        foreach ($rows as $row) {
            if ($row->recipient_id !== null) {
                $members[] = new Adherent($this->zdb, (int)$row->recipient_id, false);
            }
        }
        $mailing->setRecipients($members);

        $unreachable_ids = array_map(
            fn(Adherent $m): int => (int)$m->id,
            $mailing->unreachables
        );

        $res = $mailing->send();
        $error = implode("\n", $mailing->errors);

        foreach ($rows as $row) {
            $rid = (int)$row->recipient_id;
            $qid = (int)$row->mailing_queue_id;
            if (in_array($rid, $unreachable_ids, true)) {
                //no usable email address, do not retry
                $this->markRow($qid, self::STATUS_FAILED, 'No valid email address');
                $failed++;
            } elseif ($res === Mailing::MAIL_SENT) {
                $this->markRow($qid, self::STATUS_SENT, null);
                $sent++;
            } else {
                $attempts = (int)$row->attempts + 1;
                $status = $attempts >= self::MAX_ATTEMPTS
                    ? self::STATUS_FAILED
                    : self::STATUS_PENDING;
                $this->markRow($qid, $status, $error, $attempts);
                if ($status === self::STATUS_FAILED) {
                    $failed++;
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Rehydrate a mailing from history without loading its whole recipient list.
     *
     * @param int $mailing_id Mailing history id
     */
    private function loadMailing(int $mailing_id): ?Mailing
    {
        try {
            $select = $this->zdb->select(MailingHistory::TABLE);
            $select->where->equalTo('mailing_id', $mailing_id);
            $row = $this->zdb->execute($select)->current();
            if (!$row instanceof ArrayObject) {
                return null;
            }

            $mailing = new Mailing($this->preferences, [], $mailing_id);
            $mailing->subject = $row->mailing_subject;
            $mailing->message = $row->mailing_body;
            $mailing->html = ($row->mailing_body != strip_tags($row->mailing_body));
            if ($row->mailing_sender_name !== null || $row->mailing_sender_address !== null) {
                $mailing->setSender(
                    $row->mailing_sender_name,
                    $row->mailing_sender_address
                );
            }
            return $mailing;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to load mailing #' . $mailing_id . ' for queue | ' . $e->getMessage(),
                Analog::ERROR
            );
            return null;
        }
    }

    /**
     * Mark the related mailing as sent once no recipient is pending anymore.
     *
     * @param int $mailing_id Mailing history id
     */
    private function maybeMarkMailingSent(int $mailing_id): void
    {
        if ($this->countByStatus(self::STATUS_PENDING, $mailing_id) !== 0) {
            return;
        }

        try {
            $update = $this->zdb->update(MailingHistory::TABLE);
            $update->set(['mailing_sent' => $this->zdb->isPostgres() ? 'true' : 1]);
            $update->where(['mailing_id' => $mailing_id]);
            $this->zdb->execute($update);
        } catch (Throwable $e) {
            Analog::log(
                'Unable to mark mailing #' . $mailing_id . ' as sent | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Update a queue row status.
     *
     * @param int     $id       Queue row id
     * @param int     $status   New status
     * @param ?string $error    Error message to store, if any
     * @param ?int    $attempts New attempts count, if any
     */
    private function markRow(int $id, int $status, ?string $error, ?int $attempts = null): void
    {
        $values = ['status' => $status];
        if ($status === self::STATUS_SENT) {
            $values['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($error !== null) {
            $values['last_error'] = $error;
        }
        if ($attempts !== null) {
            $values['attempts'] = $attempts;
        }

        try {
            $update = $this->zdb->update(self::TABLE);
            $update->set($values);
            $update->where([self::PK => $id]);
            $this->zdb->execute($update);
        } catch (Throwable $e) {
            Analog::log(
                'Unable to update mailing queue row #' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Get the oldest mailing having pending recipients.
     *
     * @param ?int $only Restrict to this mailing id
     */
    private function getNextPendingMailingId(?int $only = null): ?int
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['mailing_id']);
        $select->where->equalTo('status', self::STATUS_PENDING);
        if ($only !== null) {
            $select->where->equalTo('mailing_id', $only);
        }
        $select->order(self::PK . ' ASC');
        $select->limit(1);

        $row = $this->zdb->execute($select)->current();
        if (!$row instanceof ArrayObject) {
            return null;
        }
        return (int)$row->mailing_id;
    }

    /**
     * Get pending queue rows for a mailing.
     *
     * @param int $mailing_id Mailing history id
     * @param int $limit      Maximum number of rows to fetch
     *
     * @return array<int, ArrayObject<string, mixed>>
     */
    private function getPendingRows(int $mailing_id, int $limit): array
    {
        $select = $this->zdb->select(self::TABLE);
        $select->where->equalTo('status', self::STATUS_PENDING);
        $select->where->equalTo('mailing_id', $mailing_id);
        $select->order(self::PK . ' ASC');
        $select->limit($limit);

        $rows = [];
        foreach ($this->zdb->execute($select) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Count queue rows in a given status.
     *
     * @param int  $status     Status to count
     * @param ?int $mailing_id Restrict to this mailing id
     */
    private function countByStatus(int $status, ?int $mailing_id = null): int
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['c' => new Expression('COUNT(*)')]);
        $select->where->equalTo('status', $status);
        if ($mailing_id !== null) {
            $select->where->equalTo('mailing_id', $mailing_id);
        }

        $row = $this->zdb->execute($select)->current();
        return (int)$row->c;
    }

    /**
     * Count recipients sent since a relative point in time.
     *
     * @param string $modifier A DateTime relative modifier, e.g. '-1 hour'
     */
    private function countSentSince(string $modifier): int
    {
        $since = (new DateTime($modifier))->format('Y-m-d H:i:s');
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['c' => new Expression('COUNT(*)')]);
        $select->where->equalTo('status', self::STATUS_SENT);
        $select->where->greaterThanOrEqualTo('sent_at', $since);

        $row = $this->zdb->execute($select)->current();
        return (int)$row->c;
    }

    /**
     * Build a progress payload.
     *
     * @param ?int $mailing_id   Restrict stats to this mailing
     * @param bool $rate_limited Whether the rate limit is currently reached
     * @param int  $batch_sent   Recipients sent during the last batch
     * @param int  $batch_failed Recipients failed during the last batch
     *
     * @return array<string, int|bool>
     */
    private function progress(
        ?int $mailing_id,
        bool $rate_limited,
        int $batch_sent = 0,
        int $batch_failed = 0
    ): array {
        $pending = $this->countByStatus(self::STATUS_PENDING, $mailing_id);
        $sent = $this->countByStatus(self::STATUS_SENT, $mailing_id);
        $failed = $this->countByStatus(self::STATUS_FAILED, $mailing_id);

        return [
            'total'        => $pending + $sent + $failed,
            'remaining'    => $pending,
            'sent_total'   => $sent,
            'failed_total' => $failed,
            'batch_sent'   => $batch_sent,
            'batch_failed' => $batch_failed,
            'done'         => ($pending === 0),
            'rate_limited' => $rate_limited
        ];
    }
}
