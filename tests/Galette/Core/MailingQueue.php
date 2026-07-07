<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\Fixtures\RecordingGaletteMail;
use Galette\Tests\GaletteTestCase;

/**
 * Mailing queue tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailingQueue extends GaletteTestCase
{
    protected int $seed = 20240131082139;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(\Galette\Core\MailingQueue::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Core\MailingHistory::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\Reminder::TABLE);
        $this->zdb->execute($delete);
        $this->cleanMembers();

        parent::tearDown();
    }

    /**
     * Build and store a mailing (not sent), return it.
     */
    private function buildStoredMailing(): \Galette\Core\Mailing
    {
        $adh1 = $this->getMemberOne();
        $adh2 = $this->getMemberTwo();

        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$adh1->id, $adh2->id];

        $m = new \Galette\Repository\Members();
        $members = $m->getArrayList($filters->selected);
        $mailing = new \Galette\Core\Mailing($this->preferences, $members);
        $mailing->subject = 'Queue test';
        $mailing->message = 'Queue test body';
        $mailing->setSender(
            name: 'Galette unit tests',
            address: 'test@galette.eu'
        );

        $mh = new \Galette\Core\MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: null,
            mailing: $mailing
        );
        $this->assertTrue($mh->storeMailing(sent: false));

        return $mailing;
    }

    /**
     * Test enqueue and statistics
     */
    public function testEnqueueAndStats(): void
    {
        $this->logSuperAdmin();

        $mailing = $this->buildStoredMailing();
        $mailing_id = (int)$mailing->id;
        //both test members have a valid email address
        $this->assertCount(2, $mailing->recipients);

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $nb = $queue->enqueue($mailing_id, $mailing->recipients);
        $this->assertSame(2, $nb);

        $stats = $queue->getStats($mailing_id);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['remaining']);
        $this->assertSame(0, $stats['sent_total']);
        $this->assertSame(0, $stats['failed_total']);
        $this->assertFalse($stats['done']);
        $this->assertFalse($stats['rate_limited']);

        //global stats (no mailing filter) reflect the same rows
        $global = $queue->getStats();
        $this->assertSame(2, $global['total']);
        $this->assertSame(2, $global['remaining']);
    }

    /**
     * Test that the daily limit prevents sending and reports a rate limit,
     * without attempting to actually send anything.
     */
    public function testRateLimited(): void
    {
        $this->logSuperAdmin();

        $mailing = $this->buildStoredMailing();
        $mailing_id = (int)$mailing->id;

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(2, $queue->enqueue($mailing_id, $mailing->recipients));

        //mark one queued recipient as already sent, now
        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['mailing_queue_id'])->order('mailing_queue_id ASC')->limit(1);
        $first_id = (int)$this->zdb->execute($select)->current()->mailing_queue_id;

        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'  => \Galette\Core\MailingQueue::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_queue_id' => $first_id]);
        $this->zdb->execute($update);

        //allow only one email per day: the quota is already reached
        $this->preferences->pref_mail_daily_limit = 1;

        $progress = $queue->processBatch($mailing_id);

        $this->assertTrue($progress['rate_limited']);
        $this->assertSame(0, $progress['batch_sent']);
        $this->assertSame(0, $progress['batch_failed']);
        //nothing more was sent: one pending remains, one already sent
        $this->assertSame(1, $progress['remaining']);
        $this->assertSame(1, $progress['sent_total']);
        $this->assertFalse($progress['done']);

        //reset preference for other tests
        $this->preferences->pref_mail_daily_limit = 0;
    }

    /**
     * Test the rule that decides whether sending has to go through the queue.
     * Batching alone stays synchronous; a quota is what spreads sending over
     * time, and therefore requires the queue.
     */
    public function testMustQueue(): void
    {
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);

        $this->assertFalse($queue->mustQueue());

        $this->preferences->pref_mail_batch_size = 10;
        $this->preferences->pref_mail_batch_delay = 1;
        $this->assertFalse($queue->mustQueue());

        $this->preferences->pref_mail_hourly_limit = 5;
        $this->assertTrue($queue->mustQueue());
        $this->preferences->pref_mail_hourly_limit = 0;

        $this->preferences->pref_mail_daily_limit = 5;
        $this->assertTrue($queue->mustQueue());
        $this->preferences->pref_mail_daily_limit = 0;

        $this->assertFalse($queue->mustQueue());

        //reset preferences for other tests
        $this->preferences->pref_mail_batch_size = 0;
        $this->preferences->pref_mail_batch_delay = 0;
    }

    /**
     * Test the unattended drainer: it stops on an empty queue, and on a
     * reached quota, without attempting to send anything.
     */
    public function testDrain(): void
    {
        $this->logSuperAdmin();

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);

        //nothing queued, nothing to do
        $result = $queue->drain();
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($result['rate_limited']);
        $this->assertTrue($result['progress']['done']);

        $mailing = $this->buildStoredMailing();
        $this->assertSame(2, $queue->enqueue((int)$mailing->id, $mailing->recipients));

        //mark one queued recipient as already sent, now
        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['mailing_queue_id'])->order('mailing_queue_id ASC')->limit(1);
        $first_id = (int)$this->zdb->execute($select)->current()->mailing_queue_id;

        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'  => \Galette\Core\MailingQueue::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_queue_id' => $first_id]);
        $this->zdb->execute($update);

        //quota already exhausted: the drainer gives up for this run, and the
        //pending recipient stays in queue for the next one
        $this->preferences->pref_mail_daily_limit = 1;

        $result = $queue->drain();
        $this->assertTrue($result['rate_limited']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($result['progress']['done']);
        $this->assertSame(1, $result['progress']['remaining']);

        //reset preference for other tests
        $this->preferences->pref_mail_daily_limit = 0;
    }

    /**
     * Test that a row being delivered is not handed to a second worker, and
     * comes back to the queue once its claim has expired.
     */
    public function testClaimedRowsAreNotProcessedTwice(): void
    {
        $this->logSuperAdmin();

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $mailing = $this->buildStoredMailing();
        $mailing_id = (int)$mailing->id;
        $this->assertSame(2, $queue->enqueue($mailing_id, $mailing->recipients));

        //simulate a worker that claimed both rows and has not come back yet
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'     => \Galette\Core\MailingQueue::STATUS_SENDING,
                'claimed_at' => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_id' => $mailing_id]);
        $this->zdb->execute($update);

        //rows being delivered still count as remaining, and nothing is done
        $stats = $queue->getStats($mailing_id);
        $this->assertSame(2, $stats['remaining']);
        $this->assertFalse($stats['done']);

        //a second worker finds nothing to send, and sends nothing
        $progress = $queue->processBatch($mailing_id);
        $this->assertSame(0, $progress['batch_sent']);
        $this->assertSame(0, $progress['batch_failed']);
        $this->assertFalse($progress['done']);

        //the mailing is not flagged as sent while rows are in flight
        $select = $this->zdb->select(\Galette\Core\MailingHistory::TABLE);
        $select->columns(['mailing_sent'])->where(['mailing_id' => $mailing_id]);
        $this->assertEmpty($this->zdb->execute($select)->current()->mailing_sent);

        //the worker never came back: the claim expires and rows are given back
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'claimed_at' => (new \Safe\DateTime(
                    '-' . (\Galette\Core\MailingQueue::CLAIM_TIMEOUT + 1) . ' minutes'
                ))->format('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_id' => $mailing_id]);
        $this->zdb->execute($update);

        //exhaust the quota so the batch stops right after the release: only
        //the release is under test here, nothing must be sent
        $this->markOneRowSent($mailing_id);
        $this->preferences->pref_mail_daily_limit = 1;
        $this->preferences->pref_mail_hourly_limit = 0;

        $progress = $queue->processBatch($mailing_id);
        $this->assertTrue($progress['rate_limited']);
        $this->assertSame(0, $progress['batch_sent']);
        $this->expectLogEntry(\Analog\Analog::WARNING, 'stale mailing queue claim(s) released');

        $this->preferences->pref_mail_daily_limit = 0;

        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['status', 'attempts'])
            ->where(['mailing_id' => $mailing_id])
            ->where->notEqualTo('status', \Galette\Core\MailingQueue::STATUS_SENT);
        $rows = $this->zdb->execute($select);
        $this->assertSame(1, $rows->count());
        foreach ($rows as $row) {
            $this->assertSame(\Galette\Core\MailingQueue::STATUS_PENDING, (int)$row->status);
            //the attempt that never completed is accounted for
            $this->assertSame(1, (int)$row->attempts);
        }
    }

    /**
     * Mark the first queued row of a mailing as sent, now.
     *
     * @param int $mailing_id Mailing history id
     */
    private function markOneRowSent(int $mailing_id): void
    {
        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['mailing_queue_id'])
            ->where(['mailing_id' => $mailing_id])
            ->order('mailing_queue_id ASC')
            ->limit(1);
        $id = (int)$this->zdb->execute($select)->current()->mailing_queue_id;

        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'     => \Galette\Core\MailingQueue::STATUS_SENT,
                'claimed_at' => null,
                'sent_at'    => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_queue_id' => $id]);
        $this->zdb->execute($update);
    }

    /**
     * Build a reminder for a member.
     */
    private function buildReminder(int $type, \Galette\Entity\Adherent $member): \Galette\Entity\Reminder
    {
        $reminder = new \Galette\Entity\Reminder();
        $reminder->type = $type;
        $reminder->dest = $member;
        return $reminder;
    }

    /**
     * Test reminders enqueue and the anti-duplicate guard
     */
    public function testEnqueueReminders(): void
    {
        $this->logSuperAdmin();

        $adh1 = $this->getMemberOne();
        $adh2 = $this->getMemberTwo();
        $reminders = [
            $this->buildReminder(\Galette\Entity\Reminder::LATE, $adh1),
            $this->buildReminder(\Galette\Entity\Reminder::LATE, $adh2)
        ];

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(2, $queue->enqueueReminders($reminders));

        $stats = $queue->getStats(mailing_id: null, kind: \Galette\Core\MailingQueue::KIND_REMINDER);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['remaining']);
        $this->assertSame(0, $stats['sent_total']);

        //enqueuing the same reminders again must not create duplicates
        $this->assertSame(0, $queue->enqueueReminders($reminders));
        $stats = $queue->getStats(mailing_id: null, kind: \Galette\Core\MailingQueue::KIND_REMINDER);
        $this->assertSame(2, $stats['total']);

        //a different type for the same member is a distinct reminder
        $this->assertSame(
            1,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::IMPENDING, $adh1)
            ])
        );
        $this->assertSame(3, $queue->getStats(mailing_id: null, kind: \Galette\Core\MailingQueue::KIND_REMINDER)['total']);
    }

    /**
     * Test that a reminder queued twice by concurrent enqueues is only
     * delivered once.
     */
    public function testDuplicateReminderIsDroppedAtDrain(): void
    {
        $this->logSuperAdmin();

        $adh = $this->getMemberOne();
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(
            1,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::LATE, $adh)
            ])
        );

        //a concurrent enqueue that found nothing pending would insert a second,
        //identical row: bypass the guard to reproduce it
        $insert = $this->zdb->insert(\Galette\Core\MailingQueue::TABLE);
        $insert->values(
            [
                'kind'            => \Galette\Core\MailingQueue::KIND_REMINDER,
                'reminder_type'   => \Galette\Entity\Reminder::LATE,
                'recipient_id'    => $adh->id,
                'recipient_email' => $adh->getEmail(),
                'recipient_name'  => $adh->sname,
                'status'          => \Galette\Core\MailingQueue::STATUS_PENDING,
                'attempts'        => 0,
                'scheduled_at'    => date('Y-m-d H:i:s')
            ]
        );
        $this->zdb->execute($insert);

        $kind = \Galette\Core\MailingQueue::KIND_REMINDER;
        $this->assertSame(2, $queue->getStats(mailing_id: null, kind: $kind)['total']);

        //the first one has been delivered
        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['mailing_queue_id'])->order('mailing_queue_id ASC')->limit(1);
        $first_id = (int)$this->zdb->execute($select)->current()->mailing_queue_id;
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'  => \Galette\Core\MailingQueue::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_queue_id' => $first_id]);
        $this->zdb->execute($update);

        //draining the leftover must not send it again: it is simply dropped
        $queue->setReminderContext($this->history, $this->login);
        $progress = $queue->processBatch(only_mailing_id: null, kind: $kind);
        $this->expectLogEntry(\Analog\Analog::INFO, 'Dropped duplicate queued reminder');

        $this->assertSame(0, $progress['batch_sent']);
        $this->assertSame(0, $progress['batch_failed']);
        $this->assertSame(1, $progress['total']);
        $this->assertSame(1, $progress['sent_total']);
        $this->assertSame(0, $progress['remaining']);
        $this->assertTrue($progress['done']);
    }

    /**
     * Test that the database refuses a second pending row for the same
     * reminder, even when two enqueues both find nothing pending.
     */
    public function testPendingReminderIsUniqueInDatabase(): void
    {
        $this->logSuperAdmin();

        $adh = $this->getMemberOne();
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(
            1,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::LATE, $adh)
            ])
        );

        //what a concurrent enqueue would write once its own check found
        //nothing pending: the unique key is the only thing left to stop it
        $insert = $this->zdb->insert(\Galette\Core\MailingQueue::TABLE);
        $insert->values(
            [
                'kind'            => \Galette\Core\MailingQueue::KIND_REMINDER,
                'reminder_type'   => \Galette\Entity\Reminder::LATE,
                'recipient_id'    => $adh->id,
                'recipient_email' => $adh->getEmail(),
                'recipient_name'  => $adh->sname,
                'status'          => \Galette\Core\MailingQueue::STATUS_PENDING,
                'attempts'        => 0,
                'scheduled_at'    => date('Y-m-d H:i:s'),
                'dedup_key'       => 'reminder-' . $adh->id . '-' . \Galette\Entity\Reminder::LATE
            ]
        );

        //a rejected statement leaves a PostgreSQL transaction unusable, and
        //each test runs inside one: give it a point to come back to
        $this->zdb->db->query(
            'SAVEPOINT dedup_check',
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        $rejected = false;
        try {
            $this->zdb->execute($insert);
        } catch (\Throwable) {
            $rejected = true;
            $this->zdb->db->query(
                'ROLLBACK TO SAVEPOINT dedup_check',
                \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
            );
        }
        $this->assertTrue($rejected, 'A duplicate pending reminder has been queued.');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Query error');

        $kind = \Galette\Core\MailingQueue::KIND_REMINDER;
        $this->assertSame(1, $queue->getStats(mailing_id: null, kind: $kind)['total']);
    }

    /**
     * Test that a delivered reminder hands its key back, so the same reminder
     * may legitimately be queued again later.
     */
    public function testReminderKeyIsReleasedOnceDelivered(): void
    {
        $this->logSuperAdmin();

        $adh = $this->getMemberOne();
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $reminder = $this->buildReminder(\Galette\Entity\Reminder::LATE, $adh);
        $this->assertSame(1, $queue->enqueueReminders([$reminder]));
        //still waiting: it must not be queued twice
        $this->assertSame(0, $queue->enqueueReminders([$reminder]));

        //it has now left
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'    => \Galette\Core\MailingQueue::STATUS_SENT,
                'sent_at'   => date('Y-m-d H:i:s'),
                'dedup_key' => null
            ]
        );
        $update->where(['kind' => \Galette\Core\MailingQueue::KIND_REMINDER]);
        $this->zdb->execute($update);

        //the next period may remind that very member again
        $this->assertSame(
            1,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::LATE, $adh)
            ])
        );
    }

    /**
     * Test that a row whose worker never comes back ends up failed rather than
     * being handed out forever.
     */
    public function testStaleClaimsEventuallyFail(): void
    {
        $this->logSuperAdmin();

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $mailing = $this->buildStoredMailing();
        $mailing_id = (int)$mailing->id;
        $this->assertSame(2, $queue->enqueue($mailing_id, $mailing->recipients));

        //rows claimed by a worker that died, on their very last attempt
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'     => \Galette\Core\MailingQueue::STATUS_SENDING,
                'attempts'   => \Galette\Core\MailingQueue::MAX_ATTEMPTS - 1,
                'claimed_at' => (new \Safe\DateTime(
                    '-' . (\Galette\Core\MailingQueue::CLAIM_TIMEOUT + 1) . ' minutes'
                ))->format('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_id' => $mailing_id]);
        $this->zdb->execute($update);

        $progress = $queue->processBatch($mailing_id);
        $this->expectLogEntry(\Analog\Analog::WARNING, 'failed after too many stale claims');

        //nothing is waiting any more, and nothing has been sent
        $this->assertSame(0, $progress['remaining']);
        $this->assertSame(0, $progress['sent_total']);
        $this->assertSame(2, $progress['failed_total']);
        $this->assertTrue($progress['done']);

        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['status', 'attempts', 'last_error'])->where(['mailing_id' => $mailing_id]);
        foreach ($this->zdb->execute($select) as $row) {
            $this->assertSame(\Galette\Core\MailingQueue::STATUS_FAILED, (int)$row->status);
            $this->assertSame(\Galette\Core\MailingQueue::MAX_ATTEMPTS, (int)$row->attempts);
            $this->assertStringContainsString('giving up', $row->last_error);
        }

        //a mailing that ends with failed recipients is not a sent one
        $select = $this->zdb->select(\Galette\Core\MailingHistory::TABLE);
        $select->columns(['mailing_sent'])->where(['mailing_id' => $mailing_id]);
        $this->assertEmpty($this->zdb->execute($select)->current()->mailing_sent);
    }

    /**
     * Test that a reminder progress page reports on the run being drained,
     * not on every reminder ever queued.
     */
    public function testReminderStatsAreScopedToCurrentRun(): void
    {
        $this->logSuperAdmin();

        $kind = \Galette\Core\MailingQueue::KIND_REMINDER;
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);

        //a first run, entirely delivered a while ago
        $this->assertSame(
            1,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::LATE, $this->getMemberOne())
            ])
        );
        $past = (new \Safe\DateTime('-2 days'))->format('Y-m-d H:i:s');
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'       => \Galette\Core\MailingQueue::STATUS_SENT,
                'scheduled_at' => $past,
                'sent_at'      => $past,
                'dedup_key'    => null
            ]
        );
        $update->where(['kind' => $kind]);
        $this->zdb->execute($update);

        //that run is over: it is the one being reported on
        $stats = $queue->getStats(mailing_id: null, kind: $kind);
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['sent_total']);
        $this->assertTrue($stats['done']);

        //a new run starts: the previous one must not weigh on its progress
        $this->assertSame(
            1,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::IMPENDING, $this->getMemberTwo())
            ])
        );

        $stats = $queue->getStats(mailing_id: null, kind: $kind);
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['remaining']);
        $this->assertSame(0, $stats['sent_total']);
        $this->assertFalse($stats['done']);
    }

    /**
     * Test that completed reminders, which hang on to no mailing, are dropped
     * once no quota window can reach them any more.
     */
    public function testCompletedRemindersArePurged(): void
    {
        $this->logSuperAdmin();

        $kind = \Galette\Core\MailingQueue::KIND_REMINDER;
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(
            2,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::LATE, $this->getMemberOne()),
                $this->buildReminder(\Galette\Entity\Reminder::LATE, $this->getMemberTwo())
            ])
        );

        $old = (new \Safe\DateTime(
            '-' . (\Galette\Core\MailingQueue::RETENTION_DAYS + 1) . ' days'
        ))->format('Y-m-d H:i:s');

        //one was delivered long ago, the other gave up long ago
        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['mailing_queue_id'])->order('mailing_queue_id ASC');
        $ids = [];
        foreach ($this->zdb->execute($select) as $row) {
            $ids[] = (int)$row->mailing_queue_id;
        }

        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'       => \Galette\Core\MailingQueue::STATUS_SENT,
                'scheduled_at' => $old,
                'sent_at'      => $old,
                'dedup_key'    => null
            ]
        );
        $update->where(['mailing_queue_id' => $ids[0]]);
        $this->zdb->execute($update);

        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'       => \Galette\Core\MailingQueue::STATUS_FAILED,
                'scheduled_at' => $old,
                'dedup_key'    => null
            ]
        );
        $update->where(['mailing_queue_id' => $ids[1]]);
        $this->zdb->execute($update);

        //an empty queue is where the housekeeping happens
        $queue->processBatch(only_mailing_id: null, kind: $kind);

        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['c' => new \Laminas\Db\Sql\Expression('COUNT(*)')]);
        $this->assertSame(0, (int)$this->zdb->execute($select)->current()->c);
    }

    /**
     * Test that the global daily quota also gates reminders (no send attempted)
     */
    public function testRemindersShareGlobalQuota(): void
    {
        $this->logSuperAdmin();

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $queue->enqueueReminders([
            $this->buildReminder(\Galette\Entity\Reminder::LATE, $this->getMemberOne()),
            $this->buildReminder(\Galette\Entity\Reminder::LATE, $this->getMemberTwo())
        ]);

        //mark one reminder row as already sent, now
        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['mailing_queue_id'])->order('mailing_queue_id ASC')->limit(1);
        $first_id = (int)$this->zdb->execute($select)->current()->mailing_queue_id;
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'  => \Galette\Core\MailingQueue::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_queue_id' => $first_id]);
        $this->zdb->execute($update);

        //global daily quota already reached: nothing more may be sent
        $this->preferences->pref_mail_daily_limit = 1;

        $progress = $queue->processBatch(only_mailing_id: null, kind: \Galette\Core\MailingQueue::KIND_REMINDER);

        $this->assertTrue($progress['rate_limited']);
        $this->assertSame(0, $progress['batch_sent']);
        $this->assertSame(1, $progress['remaining']);
        $this->assertSame(1, $progress['sent_total']);

        $this->preferences->pref_mail_daily_limit = 0;
    }

    /**
     * Count recorded direct sendings
     */
    private function countDirectRows(): int
    {
        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['c' => new \Laminas\Db\Sql\Expression('COUNT(*)')]);
        $select->where->equalTo('kind', \Galette\Core\MailingQueue::KIND_DIRECT);
        return (int)$this->zdb->execute($select)->current()->c;
    }

    /**
     * Build a mail that records instead of sending
     *
     * @param array<string, string> $recipients Recipients, as email => name
     */
    private function buildRecordingMail(array $recipients): RecordingGaletteMail
    {
        $mail = new RecordingGaletteMail($this->preferences);
        $mail->setSubject('Direct subject');
        $mail->setMessage('Direct body');
        $mail->setSender(name: 'Galette unit tests', address: 'test@galette.eu');
        $mail->setRecipients($recipients);
        return $mail;
    }

    /**
     * Test that a direct sending (new account, lost password, ...) is counted
     * against the quota - and refused once there is no room left for it.
     */
    public function testDirectSendingCountsAgainstQuota(): void
    {
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);

        //no quota configured: nothing is counted, nothing is stored
        $this->assertNull($queue->getRemainingQuota());
        $mail = $this->buildRecordingMail(['free@example.com' => 'Free']);
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_SENT, $mail->send());
        $this->assertSame(0, $this->countDirectRows());

        //two messages a day, and nothing sent yet
        $this->preferences->pref_mail_daily_limit = 2;
        $this->assertSame(2, $queue->getRemainingQuota());

        $mail = $this->buildRecordingMail(['user1@example.com' => 'User 1']);
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_SENT, $mail->send());
        $this->assertCount(1, $mail->recorder->sent);
        $this->assertSame(1, $this->countDirectRows());
        $this->assertSame(1, $queue->getRemainingQuota());

        //a message to two recipients does not fit in the single slot left:
        //it is refused as a whole, and nothing has even been prepared
        $mail = $this->buildRecordingMail(
            [
                'user2@example.com' => 'User 2',
                'user3@example.com' => 'User 3'
            ]
        );
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_ERROR, $mail->send());
        //nothing has even been handed to the mailer
        $this->assertCount(0, $mail->recorder->sent);
        $this->assertNotEmpty($mail->getErrors());
        $this->assertSame(1, $this->countDirectRows());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Sending quota reached');

        //the last slot is still there for a single recipient
        $mail = $this->buildRecordingMail(['user4@example.com' => 'User 4']);
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_SENT, $mail->send());
        $this->assertSame(2, $this->countDirectRows());
        $this->assertSame(0, $queue->getRemainingQuota());

        //quota exhausted: mass mailings and direct sendings alike are stuck
        $mail = $this->buildRecordingMail(['user5@example.com' => 'User 5']);
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_ERROR, $mail->send());
        $this->assertSame(2, $this->countDirectRows());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Sending quota reached');

        //reset preference for other tests
        $this->preferences->pref_mail_daily_limit = 0;
    }

    /**
     * Test that a sending already accounted for by the queue is not counted
     * a second time when the message actually leaves.
     */
    public function testQuotaManagedSendingIsNotCountedTwice(): void
    {
        $this->preferences->pref_mail_daily_limit = 5;

        $mail = $this->buildRecordingMail(['user1@example.com' => 'User 1']);
        $mail->setQuotaManaged();
        $this->assertSame(\Galette\Core\GaletteMail::MAIL_SENT, $mail->send());
        $this->assertCount(1, $mail->recorder->sent);
        $this->assertSame(0, $this->countDirectRows());

        $this->preferences->pref_mail_daily_limit = 0;
    }

    /**
     * Test that recorded sendings are cleaned up as new ones are written,
     * without ever dropping a row a quota window could still need.
     */
    public function testLedgerIsPurgedOnWrite(): void
    {
        $this->preferences->pref_mail_daily_limit = 10;
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);

        $this->assertSame(1, $queue->recordDirect(['old@example.com' => 'Old']));
        $this->assertSame(1, $queue->recordDirect(['recent@example.com' => 'Recent']));

        //the first one is now way past the retention delay, the second one is
        //older than the daily window but still within retention
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'sent_at' => (new \Safe\DateTime(
                    '-' . (\Galette\Core\MailingQueue::RETENTION_DAYS + 1) . ' days'
                ))->format('Y-m-d H:i:s')
            ]
        );
        $update->where(['recipient_email' => 'old@example.com']);
        $this->zdb->execute($update);

        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(['sent_at' => (new \Safe\DateTime('-2 days'))->format('Y-m-d H:i:s')]);
        $update->where(['recipient_email' => 'recent@example.com']);
        $this->zdb->execute($update);

        //a new sending triggers the cleanup
        $this->assertSame(1, $queue->recordDirect(['now@example.com' => 'Now']));

        $select = $this->zdb->select(\Galette\Core\MailingQueue::TABLE);
        $select->columns(['recipient_email'])->order('recipient_email ASC');
        $emails = [];
        foreach ($this->zdb->execute($select) as $row) {
            $emails[] = $row->recipient_email;
        }
        $this->assertSame(['now@example.com', 'recent@example.com'], $emails);

        //only what is inside the daily window counts against the quota
        $this->assertSame(9, $queue->getRemainingQuota());

        $this->preferences->pref_mail_daily_limit = 0;
    }
}
