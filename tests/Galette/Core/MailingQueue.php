<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

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
            $this->zdb,
            $this->login,
            $this->preferences,
            null,
            $mailing
        );
        $this->assertTrue($mh->storeMailing(false));

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

        $stats = $queue->getStats(null, \Galette\Core\MailingQueue::KIND_REMINDER);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['remaining']);
        $this->assertSame(0, $stats['sent_total']);

        //enqueuing the same reminders again must not create duplicates
        $this->assertSame(0, $queue->enqueueReminders($reminders));
        $stats = $queue->getStats(null, \Galette\Core\MailingQueue::KIND_REMINDER);
        $this->assertSame(2, $stats['total']);

        //a different type for the same member is a distinct reminder
        $this->assertSame(
            1,
            $queue->enqueueReminders([
                $this->buildReminder(\Galette\Entity\Reminder::IMPENDING, $adh1)
            ])
        );
        $this->assertSame(3, $queue->getStats(null, \Galette\Core\MailingQueue::KIND_REMINDER)['total']);
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

        $progress = $queue->processBatch(null, \Galette\Core\MailingQueue::KIND_REMINDER);

        $this->assertTrue($progress['rate_limited']);
        $this->assertSame(0, $progress['batch_sent']);
        $this->assertSame(1, $progress['remaining']);
        $this->assertSame(1, $progress['sent_total']);

        $this->preferences->pref_mail_daily_limit = 0;
    }
}
