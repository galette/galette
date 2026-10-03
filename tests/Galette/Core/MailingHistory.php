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
 * Mailing history tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailingHistory extends GaletteTestCase
{
    protected int $seed = 20240131082138;

    /**
     * Test history workflow
     */
    public function testHistoryFlow(): void
    {
        $this->logSuperAdmin();
        $mh = new \Galette\Core\MailingHistory(
            $this->zdb,
            $this->login,
            $this->preferences
        );

        //start from an empty history; database may already hold entries (seeded fixtures for
        //example). Test runs in a transaction, this is rolled back on teardown.
        $this->zdb->execute($this->zdb->delete(\Galette\Core\MailingHistory::TABLE));

        //nothing in the logs at the beginning
        $list = $mh->getHistory();
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\MembersList();
        $adh1 = $this->getMemberOne();
        $adh2 = $this->getMemberTwo();
        $filters->selected = [$adh1->id, $adh2->id];

        $m = new \Galette\Repository\Members();
        $members = $m->getArrayList($filters->selected);
        $mailing = new \Galette\Core\Mailing($this->preferences, $members);

        $mailing->subject = 'Test mailing';
        $mailing->message = 'This is a test mailing';
        $mailing->setSender(
            name: 'Galette unit tests',
            address: 'test@galette.eu'
        );
        $mailing->current_step = \Galette\Core\Mailing::STEP_SEND;

        $mh = new \Galette\Core\MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: null,
            mailing: $mailing
        );
        //user store mailing request (not send yet)
        $this->assertTrue($mh->storeMailing());

        //one entry in the logs
        $list = $mh->getHistory();
        $this->assertCount(1, $list);

        $entry = $list[0];
        $this->assertSame('Test mailing', $entry->mailing_subject);
        $this->assertCount(2, $entry->mailing_recipients);
        $this->assertEquals(0, $entry->mailing_sent);
        $this->assertSame(0, $entry->attachments);

        $first_not_sent_id = (int)$entry->mailing_id;

        $mailing = new \Galette\Core\Mailing($this->preferences, $members);
        $mh = new \Galette\Core\MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: null,
            mailing: $mailing
        );
        $this->assertTrue($mh::loadFrom(zdb: $this->zdb, id: $first_not_sent_id, mailing: $mailing, new: false));

        $this->assertSame('Test mailing', $mailing->subject);
        $this->assertCount(2, $entry->mailing_recipients);
        $this->assertEquals(0, $entry->mailing_sent);
        $this->assertSame(0, $entry->attachments);

        //change and store again (still not send yet)
        $mailing->subject = 'Test mailing (changed)';
        $this->assertTrue($mh->storeMailing());

        //still one entry in the logs
        $list = $mh->getHistory();
        $this->assertCount(1, $list);

        $entry = $list[0];
        $second_not_sent_id = (int)$entry->mailing_id;
        $this->assertSame('Test mailing (changed)', $entry->mailing_subject);
        $this->assertCount(2, $entry->mailing_recipients);
        $this->assertEquals(0, $entry->mailing_sent);
        $this->assertSame(0, $entry->attachments);
        $this->assertSame($first_not_sent_id, $second_not_sent_id);

        $mailing = new \Galette\Core\Mailing($this->preferences, $members);
        $mh = new \Galette\Core\MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: null,
            mailing: $mailing
        );
        $this->assertTrue($mh::loadFrom(zdb: $this->zdb, id: $second_not_sent_id, mailing: $mailing, new: false));

        //store "sent" mailing
        $this->assertTrue($mh->storeMailing(sent: true));

        //still one entry in the logs
        $list = $mh->getHistory();
        $this->assertCount(1, $list);

        $entry = $list[0];
        $this->assertSame('Test mailing (changed)', $entry->mailing_subject);
        $this->assertCount(2, $entry->mailing_recipients);
        $this->assertEquals(1, $entry->mailing_sent);
        $this->assertSame(0, $entry->attachments);
        $this->assertSame($second_not_sent_id, (int)$entry->mailing_id);

        //add antoher mailing in history
        $mailing = new \Galette\Core\Mailing($this->preferences, $members);

        $mailing->subject = 'Filter subject test';
        $mailing->message = 'This is a test mailing for filters';
        $mailing->setSender(
            name: 'Galette admin unit tests',
            address: 'test+admin@galette.eu'
        );
        $mailing->current_step = \Galette\Core\Mailing::STEP_SEND;

        $filters = new \Galette\Filters\MailingsList();
        $mh = new \Galette\Core\MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: $filters,
            mailing: $mailing
        );
        //user store mailing request (not send yet)
        $this->assertTrue($mh->storeMailing());

        //still one entry in the logs
        $list = $mh->getHistory();
        $this->assertCount(2, $list);

        $filters->subject_filter = 'filter';
        $list = $mh->getHistory();
        $this->assertCount(1, $list);

        $filters->subject_filter = null;
        $filters->sent_filter = \Galette\Core\MailingHistory::FILTER_SENT;
        $list = $mh->getHistory();
        $this->assertCount(1, $list);

        $entry = $list[0];
        $this->assertSame('Test mailing (changed)', $entry->mailing_subject);
        $this->assertCount(2, $entry->mailing_recipients);
        $this->assertEquals(1, $entry->mailing_sent);
    }

    /**
     * Test that a mailing whose queue still has to drain is told apart from
     * one that was never sent.
     */
    public function testSendingMailingIsFlagged(): void
    {
        $this->logSuperAdmin();

        $this->zdb->execute($this->zdb->delete(\Galette\Core\MailingQueue::TABLE));
        $this->zdb->execute($this->zdb->delete(\Galette\Core\MailingHistory::TABLE));

        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->getMemberOne()->id, $this->getMemberTwo()->id];
        $members = (new \Galette\Repository\Members())->getArrayList($filters->selected);

        $mailing = new \Galette\Core\Mailing($this->preferences, $members);
        $mailing->subject = 'Queued mailing';
        $mailing->message = 'This one is still on its way';
        $mailing->current_step = \Galette\Core\Mailing::STEP_SEND;

        $mh = new \Galette\Core\MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: null,
            mailing: $mailing
        );
        $this->assertTrue($mh->storeMailing(sent: false));

        //stored unsent, with nothing queued yet: this is a draft
        $list = $mh->getHistory();
        $this->assertCount(1, $list);
        $this->assertFalse($list[0]->mailing_sending);

        //its recipients are now waiting to be sent
        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(2, $queue->enqueue((int)$mailing->id, $mailing->recipients));

        $list = $mh->getHistory();
        $this->assertCount(1, $list);
        $this->assertEquals(0, $list[0]->mailing_sent);
        $this->assertTrue($list[0]->mailing_sending);

        //once the queue is empty, it is not on its way any more
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'  => \Galette\Core\MailingQueue::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_id' => (int)$mailing->id]);
        $this->zdb->execute($update);

        $list = $mh->getHistory();
        $this->assertFalse($list[0]->mailing_sending);
    }

    /**
     * Test the sent filter tells the three states apart.
     */
    public function testSentFilter(): void
    {
        $this->logSuperAdmin();

        $this->zdb->execute($this->zdb->delete(\Galette\Core\MailingQueue::TABLE));
        $this->zdb->execute($this->zdb->delete(\Galette\Core\MailingHistory::TABLE));

        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->getMemberOne()->id, $this->getMemberTwo()->id];
        $members = (new \Galette\Repository\Members())->getArrayList($filters->selected);

        $ids = [];
        foreach (['Sent one' => true, 'Draft one' => false, 'Queued one' => false] as $subject => $sent) {
            $mailing = new \Galette\Core\Mailing($this->preferences, $members);
            $mailing->subject = $subject;
            $mailing->message = 'Body of ' . $subject;
            $mailing->current_step = \Galette\Core\Mailing::STEP_SEND;

            $mh = new \Galette\Core\MailingHistory(
                zdb: $this->zdb,
                login: $this->login,
                preferences: $this->preferences,
                filters: null,
                mailing: $mailing
            );
            $this->assertTrue($mh->storeMailing(sent: $sent));
            $ids[$subject] = (int)$mailing->id;
        }

        $queue = new \Galette\Core\MailingQueue($this->zdb, $this->preferences);
        $this->assertSame(2, $queue->enqueue($ids['Queued one'], $members));

        $list_filters = new \Galette\Filters\MailingsList();
        $mh = new \Galette\Core\MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: $list_filters
        );

        $subjects = function (int $filter) use ($mh, $list_filters): array {
            $list_filters->sent_filter = $filter;
            /** @var array<int, string> $found */
            $found = [];
            foreach ($mh->getHistory() as $entry) {
                $found[] = (string)$entry->mailing_subject;
            }
            sort($found);
            return $found;
        };

        $this->assertSame(
            ['Draft one', 'Queued one', 'Sent one'],
            $subjects(\Galette\Core\MailingHistory::FILTER_DC_SENT)
        );
        $this->assertSame(['Sent one'], $subjects(\Galette\Core\MailingHistory::FILTER_SENT));
        $this->assertSame(['Queued one'], $subjects(\Galette\Core\MailingHistory::FILTER_SENDING));
        //a mailing on its way is no longer counted among those that never left
        $this->assertSame(['Draft one'], $subjects(\Galette\Core\MailingHistory::FILTER_NOT_SENT));

        //once its queue is empty, it falls back among the unsent ones
        $update = $this->zdb->update(\Galette\Core\MailingQueue::TABLE);
        $update->set(
            [
                'status'  => \Galette\Core\MailingQueue::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        );
        $update->where(['mailing_id' => $ids['Queued one']]);
        $this->zdb->execute($update);

        $this->assertSame([], $subjects(\Galette\Core\MailingHistory::FILTER_SENDING));
        $this->assertSame(
            ['Draft one', 'Queued one'],
            $subjects(\Galette\Core\MailingHistory::FILTER_NOT_SENT)
        );
    }
}
