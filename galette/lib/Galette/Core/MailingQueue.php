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
use Galette\Entity\Reminder;
use Galette\Entity\Texts;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Safe\DateTime;
use Slim\Routing\RouteParser;
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
    /** Row claimed by a worker, delivery in progress */
    public const int STATUS_SENDING = 3;
    /** Statuses of the rows that still have to leave */
    public const array PENDING_STATUSES = [self::STATUS_PENDING, self::STATUS_SENDING];

    /** Item natures */
    public const int KIND_MAILING = 0;
    public const int KIND_REMINDER = 1;
    /** Message already sent right away, only kept to account for the quota */
    public const int KIND_DIRECT = 2;

    /** Maximum number of attempts before a recipient is marked as failed */
    public const int MAX_ATTEMPTS = 3;

    /**
     * How long a claim is honoured, in minutes. A worker that dies between the
     * claim and the delivery would otherwise keep its rows forever; past that
     * delay they are handed back to the queue.
     */
    public const int CLAIM_TIMEOUT = 15;

    /**
     * How long a completed row that hangs on to no mailing is kept, in days.
     * Direct sendings and reminders have no parent record to be deleted with,
     * nothing would ever remove them. Must stay well above the widest quota
     * window (a day), otherwise the cleanup would hand back quota that has
     * actually been consumed.
     */
    public const int RETENTION_DAYS = 7;

    /** Collaborators required to send reminder rows (set via setReminderContext) */
    private ?History $history = null;
    private ?Login $login = null;
    private ?RouteParser $routeparser = null;

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
     * Provide the collaborators needed to send reminder rows.
     * Required only when the queue holds reminders (KIND_REMINDER).
     *
     * @param History      $history     History instance
     * @param Login        $login       Login instance
     * @param ?RouteParser $routeparser Route parser (optional, for Texts links)
     */
    public function setReminderContext(
        History $history,
        Login $login,
        ?RouteParser $routeparser = null
    ): self {
        $this->history = $history;
        $this->login = $login;
        $this->routeparser = $routeparser;
        return $this;
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
                        'kind'            => self::KIND_MAILING,
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
     * Add reminders to the sending queue as individual, personalized items.
     *
     * Each reminder is rendered at drain time (from Texts, in the member's
     * language) so the day counters stay accurate. A pending reminder for the
     * same member and type is not queued twice (getList recomputes due
     * reminders and their audit row only exists once actually sent), and the
     * database is what guarantees it: a row waiting to be sent holds a unique
     * dedup key, released as soon as it leaves the queue (see reminderKey).
     * Two concurrent enqueues can therefore no longer both queue the same
     * reminder; the loser simply sees its insert rejected.
     *
     * @param array<int, Reminder> $reminders Due reminders (see Reminders::getList)
     *
     * @return int Number of queued reminders
     */
    public function enqueueReminders(array $reminders): int
    {
        $count = 0;
        $now = date('Y-m-d H:i:s');
        try {
            foreach ($reminders as $reminder) {
                if (!$reminder->hasMail()) {
                    continue;
                }
                $member = $reminder->dest;
                $type = (int)$reminder->type;
                //do not queue the same reminder twice while it is still pending
                if ($this->hasPendingReminder((int)$member->id, $type)) {
                    continue;
                }

                $insert = $this->zdb->insert(self::TABLE);
                $insert->values(
                    [
                        'kind'            => self::KIND_REMINDER,
                        'reminder_type'   => $type,
                        'recipient_id'    => $member->id,
                        'recipient_email' => $member->getEmail(),
                        'recipient_name'  => $member->sname,
                        'status'          => self::STATUS_PENDING,
                        'attempts'        => 0,
                        'scheduled_at'    => $now,
                        'dedup_key'       => self::reminderKey((int)$member->id, $type)
                    ]
                );

                try {
                    $this->zdb->execute($insert);
                    $count++;
                } catch (Throwable $e) {
                    //the unique key is what makes the check above true even
                    //when another enqueue runs at the very same time: a
                    //rejected insert only means somebody got there first.
                    //Anything else is a real failure and must be reported.
                    if (!$this->isAlreadyQueued((int)$member->id, $type)) {
                        throw $e;
                    }
                    Analog::log(
                        'Reminder of type ' . $type . ' for member #' . $member->id
                        . ' was already queued, skipped.',
                        Analog::INFO
                    );
                }
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to enqueue reminders | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }

        return $count;
    }

    /**
     * Did a rejected insert only mean the reminder was already waiting?
     *
     * Asking the database again is itself a query, which may be refused in
     * turn (an enqueue running inside a transaction PostgreSQL has just given
     * up on, say). There is nothing to tell then, and the original failure is
     * the one worth reporting.
     *
     * @param int $member_id Member id
     * @param int $type      Reminder type (Reminder::IMPENDING|LATE)
     */
    private function isAlreadyQueued(int $member_id, int $type): bool
    {
        try {
            return $this->hasPendingReminder($member_id, $type);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Key that a reminder holds for as long as it waits to be sent.
     *
     * The column it is stored in is unique, and null everywhere else: a member
     * can only have one reminder of a given type in the queue at a time. The
     * key is released once the row has left (sent, failed or dropped), so the
     * next period can legitimately queue that very same reminder again.
     *
     * @param int $member_id Member id
     * @param int $type      Reminder type (Reminder::IMPENDING|LATE)
     */
    private static function reminderKey(int $member_id, int $type): string
    {
        return 'reminder-' . $member_id . '-' . $type;
    }

    /**
     * Is there already a pending reminder queued for this member and type?
     *
     * @param int $member_id Member id
     * @param int $type      Reminder type (Reminder::IMPENDING|LATE)
     *
     * @phpstan-impure another worker may have queued one in between
     */
    private function hasPendingReminder(int $member_id, int $type): bool
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['c' => new Expression('COUNT(*)')]);
        $select->where->equalTo('kind', self::KIND_REMINDER);
        $select->where->in('status', self::PENDING_STATUSES);
        $select->where->equalTo('recipient_id', $member_id);
        $select->where->equalTo('reminder_type', $type);

        $row = $this->zdb->execute($select)->current();
        return (int)$row->c > 0;
    }

    /**
     * Account for a message that has just been sent outside of the queue.
     *
     * Direct sendings (new account, lost password, contribution receipt,
     * reminders sent synchronously, test message...) leave the same server as
     * mass mailings and eat the very same quota. They cannot wait, so they are
     * not queued: they are only recorded, as already sent rows, so the queue
     * knows how much room is left.
     *
     * @param array<string, string> $recipients Recipients, as email => name
     *
     * @return int Number of recorded recipients
     */
    public function recordDirect(array $recipients): int
    {
        $count = 0;
        $now = date('Y-m-d H:i:s');
        try {
            foreach ($recipients as $email => $name) {
                if (trim($email) === '') {
                    continue;
                }
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values(
                    [
                        'kind'            => self::KIND_DIRECT,
                        'recipient_email' => $email,
                        'recipient_name'  => $name,
                        'status'          => self::STATUS_SENT,
                        'attempts'        => 1,
                        'scheduled_at'    => $now,
                        'sent_at'         => $now
                    ]
                );
                $this->zdb->execute($insert);
                $count++;
            }
            $this->purgeCompleted();
        } catch (Throwable $e) {
            //a mail that has left must not fail on its bookkeeping
            Analog::log(
                'Unable to record direct sending | ' . $e->getMessage(),
                Analog::ERROR
            );
        }

        return $count;
    }

    /**
     * How many recipients may still be sent right now?
     *
     * The quota is global: mailings, reminders and direct sendings share it.
     *
     * @return ?int Number of recipients left, null when no quota is set
     */
    public function getRemainingQuota(): ?int
    {
        $hourly = (int)$this->preferences->pref_mail_hourly_limit;
        $daily = (int)$this->preferences->pref_mail_daily_limit;

        if ($hourly <= 0 && $daily <= 0) {
            return null;
        }

        $remaining = PHP_INT_MAX;
        if ($hourly > 0) {
            $remaining = min($remaining, max(0, $hourly - $this->countSentSince('-1 hour')));
        }
        if ($daily > 0) {
            $remaining = min($remaining, max(0, $daily - $this->countSentSince('-1 day')));
        }

        return $remaining;
    }

    /**
     * Drop completed rows that hang on to no mailing any more.
     *
     * Deleting a mailing takes its own queue rows along; direct sendings and
     * reminders have no such parent, nothing would ever delete theirs and the
     * table would grow forever. They are only useful to the quota windows (a
     * day at most) and to the duplicate checks, both far shorter than the
     * retention.
     */
    private function purgeCompleted(): void
    {
        $limit = (new DateTime('-' . self::RETENTION_DAYS . ' days'))->format('Y-m-d H:i:s');
        $parentless = [self::KIND_DIRECT, self::KIND_REMINDER];

        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where->in('kind', $parentless);
            $delete->where->equalTo('status', self::STATUS_SENT);
            $delete->where->lessThan('sent_at', $limit);
            $this->zdb->execute($delete);

            //a row that failed never got a sending date, go by when it was queued
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where->in('kind', $parentless);
            $delete->where->equalTo('status', self::STATUS_FAILED);
            $delete->where->lessThan('scheduled_at', $limit);
            $this->zdb->execute($delete);
        } catch (Throwable $e) {
            //housekeeping must never get in the way of a sending
            Analog::log(
                'Unable to purge completed mailing queue rows | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Does sending have to go through the queue?
     *
     * Splitting a mailing into several messages can still be done while the
     * request runs; spreading it over hours or days cannot. An hourly or daily
     * quota is therefore what turns sending into a queue, for mass mailings
     * and reminders alike.
     */
    public function mustQueue(): bool
    {
        return (int)$this->preferences->pref_mail_hourly_limit > 0
            || (int)$this->preferences->pref_mail_daily_limit > 0;
    }

    /**
     * Drain the queue, batch after batch, until it is empty or the rate limit
     * is reached. The configured delay is applied between two batches.
     *
     * Unattended callers (cron script, console command) want the whole queue,
     * not a single batch: this is their entry point. The AJAX drainer keeps
     * calling processBatch() instead, so the browser stays responsive and
     * applies the delay client-side.
     *
     * @param ?int $only_mailing_id Restrict processing to this mailing
     * @param ?int $kind            Restrict processing to this kind
     *
     * @return array{sent: int, failed: int, rate_limited: bool,
     *     progress: array<string, int|bool>}
     */
    public function drain(?int $only_mailing_id = null, ?int $kind = null): array
    {
        $delay = (int)$this->preferences->pref_mail_batch_delay;
        $sent = 0;
        $failed = 0;
        //a batch that moves no row at all is not an error: a failed send leaves
        //its rows pending until MAX_ATTEMPTS. Counting those rounds still bounds
        //the loop, so an unattended run cannot spin forever on a stuck queue.
        $stalled = 0;

        do {
            $progress = $this->processBatch($only_mailing_id, $kind);
            $sent += (int)$progress['batch_sent'];
            $failed += (int)$progress['batch_failed'];

            //stop when the queue is empty or the rate limit is reached: the
            //remaining messages will be sent on the next runs
            if ($progress['done'] === true || $progress['rate_limited'] === true) {
                break;
            }

            $stalled = ($progress['batch_sent'] === 0 && $progress['batch_failed'] === 0)
                ? $stalled + 1
                : 0;
            if ($stalled >= self::MAX_ATTEMPTS) {
                Analog::log(
                    'Mailing queue made no progress over ' . self::MAX_ATTEMPTS
                    . ' batches, giving up for this run.',
                    Analog::WARNING
                );
                break;
            }

            if ($delay > 0) {
                sleep($delay);
            }
        } while (true);

        $this->purgeCompleted();

        return [
            'sent'         => $sent,
            'failed'       => $failed,
            'rate_limited' => (bool)$progress['rate_limited'],
            'progress'     => $progress
        ];
    }

    /**
     * Process a single batch of the queue, respecting the configured limits.
     *
     * At most one message is sent per call, so callers stay responsive: the
     * delay between messages is applied by the caller (client-side for the
     * AJAX drainer, sleep() for the cron drainer). That message is a BCC group
     * of up to the effective batch size for a mailing, and a single
     * personalized message for a reminder.
     *
     * @param ?int $only_mailing_id Restrict processing and stats to this mailing
     * @param ?int $kind            Restrict processing and stats to this kind
     *
     * Several workers may drain the same queue at once (the AJAX drainer, the
     * cron scripts, the console command): rows are claimed before delivery, so
     * a recipient is only ever handed to a single one of them.
     *
     * @return array<string, int|bool> Progress information: total, remaining,
     *   sent_total, failed_total, batch_sent, batch_failed, done, rate_limited
     */
    public function processBatch(?int $only_mailing_id = null, ?int $kind = null): array
    {
        $batch_size = (int)$this->preferences->pref_mail_batch_size;

        //rows a dead worker left behind are given back to the queue first
        $this->releaseStaleClaims();

        $pending = $this->countByStatus(self::PENDING_STATUSES, $only_mailing_id, $kind);
        if ($pending === 0) {
            //nothing is waiting: a good time to take the old rows out
            $this->purgeCompleted();
            return $this->progress(
                mailing_id: $only_mailing_id,
                rate_limited: false,
                batch_sent: 0,
                batch_failed: 0,
                kind: $kind
            );
        }

        //how many recipients may still be sent right now? (the quota is global,
        //shared across mailings, reminders and direct sendings)
        $allowed = $batch_size > 0 ? $batch_size : $pending;
        $remaining = $this->getRemainingQuota();
        if ($remaining !== null) {
            $allowed = min($allowed, $remaining);
        }

        if ($allowed <= 0) {
            //rate limit reached, nothing can be sent for now
            return $this->progress(
                mailing_id: $only_mailing_id,
                rate_limited: true,
                batch_sent: 0,
                batch_failed: 0,
                kind: $kind
            );
        }

        $next = $this->getNextPendingRow($only_mailing_id, $kind);
        if ($next === null) {
            return $this->progress(
                mailing_id: $only_mailing_id,
                rate_limited: false,
                batch_sent: 0,
                batch_failed: 0,
                kind: $kind
            );
        }

        if ((int)$next->kind === self::KIND_REMINDER) {
            //reminders are individual personalized messages: a single one is
            //sent per call, so the configured delay applies between each of
            //them. The batch size caps the recipients of a shared message, it
            //says nothing about how many distinct messages may be sent at once.
            $rows = $this->claim($this->getPendingReminderRows(1));
            $result = $this->sendReminderRows($rows);
        } else {
            //mailings share a body and are sent as a BCC chunk
            $mailing_id = (int)$next->mailing_id;
            $rows = $this->claim($this->getPendingRows($mailing_id, $allowed));
            $result = $this->sendRows($mailing_id, $rows);
            $this->maybeMarkMailingSent($mailing_id);
        }

        return $this->progress(
            mailing_id: $only_mailing_id,
            rate_limited: false,
            batch_sent: $result['sent'],
            batch_failed: $result['failed'],
            kind: $kind
        );
    }

    /**
     * Which mailings still have recipients waiting to be sent?
     *
     * A mailing sits unsent in the history for as long as its queue drains:
     * that is not a failure, and the history has to be able to say so. This is
     * where that question is answered, either as a subquery the history
     * filters on, or as the ids of the mailings a page is showing.
     *
     * The mailing id is never null on those rows, but saying so is what keeps
     * a NOT IN built on this from discarding everything.
     */
    public function getSendingSelect(): Select
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['mailing_id']);
        $select->where->equalTo('kind', self::KIND_MAILING);
        $select->where->in('status', self::PENDING_STATUSES);
        $select->where->isNotNull('mailing_id');

        return $select;
    }

    /**
     * Which of these mailings still have recipients waiting to be sent?
     *
     * @param array<int, int> $mailing_ids Mailing history ids to look at
     *
     * @return array<int, int> Ids of the mailings still being sent
     */
    public function getSendingMailingIds(array $mailing_ids): array
    {
        if (count($mailing_ids) === 0) {
            return [];
        }

        try {
            $select = $this->getSendingSelect();
            $select->where->in('mailing_id', $mailing_ids);
            $select->group('mailing_id');

            $ids = [];
            foreach ($this->zdb->execute($select) as $row) {
                $ids[] = (int)$row->mailing_id;
            }
            return $ids;
        } catch (Throwable $e) {
            //the history is still worth showing without that detail
            Analog::log(
                'Unable to get mailings being sent | ' . $e->getMessage(),
                Analog::WARNING
            );
            return [];
        }
    }

    /**
     * Get current queue statistics, without sending anything.
     *
     * @param ?int $mailing_id Restrict stats to this mailing
     * @param ?int $kind       Restrict stats to this kind
     *
     * @return array<string, int|bool>
     */
    public function getStats(?int $mailing_id = null, ?int $kind = null): array
    {
        return $this->progress(
            mailing_id: $mailing_id,
            rate_limited: false,
            batch_sent: 0,
            batch_failed: 0,
            kind: $kind
        );
    }

    /**
     * Get sending usage over the recent rolling windows, against the configured
     * hourly/daily limits (all natures combined, as the quota is global).
     *
     * @return array<string, ?int> sent_last_hour, sent_last_day, hourly_limit,
     *   daily_limit, hourly_remaining, daily_remaining (the *_remaining values
     *   are null when the matching limit is disabled)
     */
    public function getUsage(): array
    {
        $hourly = (int)$this->preferences->pref_mail_hourly_limit;
        $daily = (int)$this->preferences->pref_mail_daily_limit;
        $sent_hour = $this->countSentSince('-1 hour');
        $sent_day = $this->countSentSince('-1 day');

        return [
            'sent_last_hour'   => $sent_hour,
            'sent_last_day'    => $sent_day,
            'hourly_limit'     => $hourly,
            'daily_limit'      => $daily,
            'hourly_remaining' => $hourly > 0 ? max(0, $hourly - $sent_hour) : null,
            'daily_remaining'  => $daily > 0 ? max(0, $daily - $sent_day) : null
        ];
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

        //build recipients for this chunk from the stored member ids. A member
        //deleted since it was queued yields an empty object: such a row can
        //never be delivered, and must not be silently counted as sent.
        $members = [];
        foreach ($rows as $row) {
            $qid = (int)$row->mailing_queue_id;
            if ($row->recipient_id === null) {
                $this->markRow($qid, self::STATUS_FAILED, 'No recipient to send to');
                $failed++;
                continue;
            }

            $member = new Adherent($this->zdb, (int)$row->recipient_id, deps: false);
            if ($member->id === null) {
                $this->markRow($qid, self::STATUS_FAILED, 'Recipient does not exist anymore');
                $failed++;
                continue;
            }
            $members[$qid] = $member;
        }

        if (count($members) === 0) {
            return ['sent' => $sent, 'failed' => $failed];
        }

        $mailing->setRecipients(array_values($members));
        //the quota has been accounted for above, do not count these twice
        $mailing->setQuotaManaged();

        $unreachable_ids = array_map(
            fn(Adherent $m): int => (int)$m->id,
            $mailing->unreachables
        );

        //members without a usable address are not part of the message
        $recipients = [];
        foreach ($members as $qid => $member) {
            if (in_array((int)$member->id, $unreachable_ids, strict: true)) {
                //no usable email address, do not retry
                $this->markRow($qid, self::STATUS_FAILED, 'No valid email address');
                $failed++;
            } else {
                $recipients[$qid] = $member;
            }
        }

        if (count($recipients) === 0) {
            //a message with no recipient left would only reach the sender
            return ['sent' => $sent, 'failed' => $failed];
        }

        $mailing->send();
        $error = implode("\n", $mailing->errors);
        //a chunk that left cannot be taken back: go by what was delivered
        //rather than by the overall result, so nothing is ever sent twice
        $delivered = $mailing->getSentRecipients();

        foreach ($rows as $row) {
            $qid = (int)$row->mailing_queue_id;
            if (!isset($recipients[$qid])) {
                //already handled above
                continue;
            }
            if (isset($delivered[$recipients[$qid]->getEmail()])) {
                $this->markRow($qid, self::STATUS_SENT, error: null);
                $sent++;
            } else {
                $attempts = (int)$row->attempts + 1;
                $status = $attempts >= self::MAX_ATTEMPTS
                    ? self::STATUS_FAILED
                    : self::STATUS_PENDING;
                $this->markRow(id: $qid, status: $status, error: $error, attempts: $attempts);
                if ($status === self::STATUS_FAILED) {
                    $failed++;
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send a chunk of queued reminders, one individual message each.
     *
     * Reuses Reminder::send() so the message rendering (Texts, member language),
     * the History entry and the galette_reminders audit row are produced exactly
     * as in the synchronous path. Reminders are not retried: send() already
     * records an audit row on each attempt.
     *
     * @param array<int, ArrayObject<string, mixed>> $rows Pending reminder rows
     *
     * @return array{sent: int, failed: int}
     */
    private function sendReminderRows(array $rows): array
    {
        $sent = 0;
        $failed = 0;

        if ($this->history === null || $this->login === null) {
            foreach ($rows as $row) {
                $this->markRow((int)$row->mailing_queue_id, self::STATUS_FAILED, 'Reminder context not configured');
                $failed++;
            }
            Analog::log(
                'Cannot send queued reminders: reminder context not set (see MailingQueue::setReminderContext)',
                Analog::ERROR
            );
            return ['sent' => $sent, 'failed' => $failed];
        }

        //a single Texts instance is reused across recipients (setMember per row)
        $texts = new Texts($this->preferences, $this->routeparser);

        foreach ($rows as $row) {
            $qid = (int)$row->mailing_queue_id;
            if ($this->isDuplicateReminder($row)) {
                //the member has already had this very reminder since this row
                //was queued: it must not be delivered a second time
                $this->dropRow($qid);
                Analog::log(
                    'Dropped duplicate queued reminder #' . $qid . '.',
                    Analog::INFO
                );
                continue;
            }

            try {
                $member = new Adherent($this->zdb, (int)$row->recipient_id);
                $reminder = new Reminder();
                $reminder->type = (int)$row->reminder_type;
                $reminder->dest = $member;
                $reminder->setDb($this->zdb)
                    ->setLogin($this->login)
                    ->setPreferences($this->preferences);
                //the quota has been accounted for above, do not count these twice
                $reminder->setQuotaManaged();
                if ($this->routeparser !== null) {
                    $reminder->setRouteparser($this->routeparser);
                }

                if ($reminder->send($texts, $this->history, $this->zdb)) {
                    $this->markRow($qid, self::STATUS_SENT, error: null);
                    $sent++;
                } else {
                    $this->markRow($qid, self::STATUS_FAILED, $reminder->getMessage());
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->markRow($qid, self::STATUS_FAILED, $e->getMessage());
                $failed++;
                Analog::log(
                    'Unable to send queued reminder #' . $qid . ' | ' . $e->getMessage(),
                    Analog::ERROR
                );
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Has this very reminder already been delivered since it was queued?
     *
     * The unique dedup key already keeps a member from having the same
     * reminder waiting twice (see reminderKey). This is the last line of
     * defence, on the very edge of delivery: a row queued before that key
     * existed, or one a hand-written query brought back, must still not turn
     * into a second message.
     *
     * @param ArrayObject<string, mixed> $row Reminder queue row
     */
    private function isDuplicateReminder(ArrayObject $row): bool
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['c' => new Expression('COUNT(*)')]);
        $select->where->equalTo('kind', self::KIND_REMINDER);
        $select->where->equalTo('status', self::STATUS_SENT);
        $select->where->equalTo('recipient_id', (int)$row->recipient_id);
        $select->where->equalTo('reminder_type', (int)$row->reminder_type);
        $select->where->notEqualTo(self::PK, (int)$row->mailing_queue_id);
        $select->where->greaterThanOrEqualTo('sent_at', $row->scheduled_at);

        $res = $this->zdb->execute($select)->current();
        return (int)$res->c > 0;
    }

    /**
     * Drop a queue row that must not be sent, and must not be counted either.
     *
     * @param int $id Queue row id
     */
    private function dropRow(int $id): void
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            $this->zdb->execute($delete);
        } catch (Throwable $e) {
            Analog::log(
                'Unable to drop mailing queue row #' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
        }
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
     * Mark the related mailing as sent once every recipient has been reached.
     *
     * A mailing that still has pending recipients is obviously not sent yet;
     * one that ends with failed recipients has not been sent either, and must
     * not be shown as such in the history.
     *
     * @param int $mailing_id Mailing history id
     */
    private function maybeMarkMailingSent(int $mailing_id): void
    {
        if ($this->countByStatus(self::PENDING_STATUSES, $mailing_id) !== 0) {
            return;
        }

        if ($this->countByStatus(self::STATUS_FAILED, $mailing_id) !== 0) {
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
     * Update a queue row status, releasing the claim it was sent under, and
     * the dedup key it held once it has left the queue for good.
     *
     * @param int     $id       Queue row id
     * @param int     $status   New status
     * @param ?string $error    Error message to store, if any
     * @param ?int    $attempts New attempts count, if any
     */
    private function markRow(int $id, int $status, ?string $error, ?int $attempts = null): void
    {
        $values = ['status' => $status, 'claimed_at' => null];
        if ($status === self::STATUS_SENT) {
            $values['sent_at'] = date('Y-m-d H:i:s');
        }
        if (!in_array($status, self::PENDING_STATUSES, strict: true)) {
            //the row has left the queue: hand its dedup key back, so the same
            //reminder may legitimately be queued again on the next period
            $values['dedup_key'] = null;
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
     * Take ownership of the rows that are about to be sent.
     *
     * Nothing prevents two workers from picking the same rows: the AJAX
     * drainer, both cron scripts and the console command all drain the same
     * queue. Each row is therefore claimed with an update that only matches a
     * row still pending, and only the rows this very update moved are handed
     * over for delivery; the ones another worker took are simply left to it.
     *
     * @param array<int, ArrayObject<string, mixed>> $rows Candidate rows
     *
     * @return array<int, ArrayObject<string, mixed>> Rows owned by this worker
     */
    private function claim(array $rows): array
    {
        $claimed = [];
        foreach ($rows as $row) {
            if ($this->claimRow((int)$row->mailing_queue_id)) {
                $claimed[] = $row;
            }
        }
        return $claimed;
    }

    /**
     * Claim a single row, if it is still up for grabs.
     *
     * @param int $id Queue row id
     *
     * @return bool Whether the row now belongs to this worker
     */
    private function claimRow(int $id): bool
    {
        try {
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [
                    'status'     => self::STATUS_SENDING,
                    'claimed_at' => date('Y-m-d H:i:s')
                ]
            );
            $update->where(
                [
                    self::PK => $id,
                    'status' => self::STATUS_PENDING
                ]
            );
            return $this->zdb->execute($update)->count() === 1;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to claim mailing queue row #' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Hand back the rows of a worker that never came back.
     *
     * A process killed between the claim and the delivery would keep its rows
     * out of everyone's reach; they return to the queue once the claim has
     * expired, and their next attempt is counted. A row that keeps killing its
     * worker is not handed back forever though: once it has used up its
     * attempts it fails, as any other undeliverable row would.
     */
    private function releaseStaleClaims(): void
    {
        $limit = (new DateTime('-' . self::CLAIM_TIMEOUT . ' minutes'))->format('Y-m-d H:i:s');

        try {
            //rows whose next attempt is their last one: they have had their
            //chances, give up on them rather than let them loop
            $giveup = $this->zdb->update(self::TABLE);
            $giveup->set(
                [
                    'status'     => self::STATUS_FAILED,
                    'attempts'   => new Expression('attempts + 1'),
                    'claimed_at' => null,
                    'dedup_key'  => null,
                    'last_error' => 'Delivery never reported back, giving up after '
                        . self::MAX_ATTEMPTS . ' attempt(s)'
                ]
            );
            $giveup->where->equalTo('status', self::STATUS_SENDING);
            $giveup->where->lessThan('claimed_at', $limit);
            $giveup->where->greaterThanOrEqualTo('attempts', self::MAX_ATTEMPTS - 1);
            $failed = $this->zdb->execute($giveup)->count();
            if ($failed > 0) {
                Analog::log(
                    $failed . ' mailing queue row(s) failed after too many stale claims.',
                    Analog::WARNING
                );
            }

            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [
                    'status'     => self::STATUS_PENDING,
                    'attempts'   => new Expression('attempts + 1'),
                    'claimed_at' => null
                ]
            );
            $update->where->equalTo('status', self::STATUS_SENDING);
            $update->where->lessThan('claimed_at', $limit);
            $released = $this->zdb->execute($update)->count();
            if ($released > 0) {
                Analog::log(
                    $released . ' stale mailing queue claim(s) released.',
                    Analog::WARNING
                );
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to release stale mailing queue claims | ' . $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Get the oldest pending row (any kind), to decide what to process next.
     *
     * @param ?int $only_mailing_id Restrict to this mailing id
     * @param ?int $kind            Restrict to this kind
     *
     * @return ?ArrayObject<string, mixed>
     */
    private function getNextPendingRow(?int $only_mailing_id = null, ?int $kind = null): ?ArrayObject
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['kind', 'mailing_id']);
        $select->where->equalTo('status', self::STATUS_PENDING);
        if ($only_mailing_id !== null) {
            $select->where->equalTo('mailing_id', $only_mailing_id);
        }
        if ($kind !== null) {
            $select->where->equalTo('kind', $kind);
        }
        $select->order(self::PK . ' ASC');
        $select->limit(1);

        $row = $this->zdb->execute($select)->current();
        if (!$row instanceof ArrayObject) {
            return null;
        }
        return $row;
    }

    /**
     * Get pending reminder rows (individual messages).
     *
     * @param int $limit Maximum number of rows to fetch (one per sent message)
     *
     * @return array<int, ArrayObject<string, mixed>>
     */
    private function getPendingReminderRows(int $limit): array
    {
        $select = $this->zdb->select(self::TABLE);
        $select->where->equalTo('status', self::STATUS_PENDING);
        $select->where->equalTo('kind', self::KIND_REMINDER);
        $select->order(self::PK . ' ASC');
        $select->limit($limit);

        $rows = [];
        foreach ($this->zdb->execute($select) as $row) {
            $rows[] = $row;
        }
        return $rows;
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
     * @param int|array<int, int> $status     Status, or statuses, to count
     * @param ?int                $mailing_id Restrict to this mailing id
     * @param ?int                $kind       Restrict to this kind
     * @param ?string             $since      Restrict to rows queued since then
     */
    private function countByStatus(
        int|array $status,
        ?int $mailing_id = null,
        ?int $kind = null,
        ?string $since = null
    ): int {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['c' => new Expression('COUNT(*)')]);
        $select->where->in('status', (array)$status);
        if ($mailing_id !== null) {
            $select->where->equalTo('mailing_id', $mailing_id);
        }
        if ($kind !== null) {
            $select->where->equalTo('kind', $kind);
        }
        if ($since !== null) {
            $select->where->greaterThanOrEqualTo('scheduled_at', $since);
        }

        $row = $this->zdb->execute($select)->current();
        return (int)$row->c;
    }

    /**
     * When did the reminder run currently being drained start?
     *
     * Reminders queued in one go share their scheduling date: that date is
     * what a run is made of. The run to report on is the one the oldest
     * waiting reminder belongs to; once nothing is waiting any more, the last
     * one that went out.
     *
     * @return ?string Scheduling date of the run, null when none was ever queued
     */
    private function getReminderRunStart(): ?string
    {
        foreach ([self::PENDING_STATUSES, null] as $statuses) {
            $select = $this->zdb->select(self::TABLE);
            $select->columns(
                ['s' => new Expression($statuses === null ? 'MAX(scheduled_at)' : 'MIN(scheduled_at)')]
            );
            $select->where->equalTo('kind', self::KIND_REMINDER);
            if ($statuses !== null) {
                $select->where->in('status', $statuses);
            }

            $row = $this->zdb->execute($select)->current();
            if ($row instanceof ArrayObject && $row->s !== null) {
                return (string)$row->s;
            }
        }

        return null;
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
     * @param ?int $kind         Restrict stats to this kind
     *
     * @return array<string, int|bool>
     */
    private function progress(
        ?int $mailing_id,
        bool $rate_limited,
        int $batch_sent = 0,
        int $batch_failed = 0,
        ?int $kind = null
    ): array {
        //a mailing scopes its own progress; reminders hang on to no parent
        //record, so without a scope a progress page would report on every
        //reminder ever queued, and never reach 100%
        $since = ($kind === self::KIND_REMINDER) ? $this->getReminderRunStart() : null;

        //a row claimed by a worker is not done yet, it still counts as pending
        $pending = $this->countByStatus(
            status: self::PENDING_STATUSES,
            mailing_id: $mailing_id,
            kind: $kind,
            since: $since
        );
        $sent = $this->countByStatus(status: self::STATUS_SENT, mailing_id: $mailing_id, kind: $kind, since: $since);
        $failed = $this->countByStatus(
            status: self::STATUS_FAILED,
            mailing_id: $mailing_id,
            kind: $kind,
            since: $since
        );

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
