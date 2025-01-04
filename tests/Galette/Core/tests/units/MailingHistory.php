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

namespace Galette\Core\tests\units;

use Galette\GaletteTestCase;

/**
 * Mailing history tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailingHistory extends GaletteTestCase
{
    protected int $seed = 20240131082138;

    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Core\MailingHistory::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Test history workflow
     *
     * @return void
     */
    public function testHistoryFlow(): void
    {
        $this->logSuperAdmin();
        $mh = new \Galette\Core\MailingHistory(
            $this->zdb,
            $this->login,
            $this->preferences
        );

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
        $mailing->body = 'This is a test mailing';
        $mailing->sender = [
            'name'  => 'Galette unit tests',
            'email' => 'test@galette.eu'
        ];
        $mailing->current_step = \Galette\Core\Mailing::STEP_SEND;

        $mh = new \Galette\Core\MailingHistory(
            $this->zdb,
            $this->login,
            $this->preferences,
            null,
            $mailing
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
            $this->zdb,
            $this->login,
            $this->preferences,
            null,
            $mailing
        );
        $this->assertTrue($mh::loadFrom($this->zdb, $first_not_sent_id, $mailing, false));

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
            $this->zdb,
            $this->login,
            $this->preferences,
            null,
            $mailing
        );
        $this->assertTrue($mh::loadFrom($this->zdb, $second_not_sent_id, $mailing, false));

        //store "sent" mailing
        $this->assertTrue($mh->storeMailing(true));

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
        $mailing->body = 'This is a test mailing for filters';
        $mailing->sender = [
            'name'  => 'Galette admin unit tests',
            'email' => 'test+admin@galette.eu'
        ];
        $mailing->current_step = \Galette\Core\Mailing::STEP_SEND;

        $filters = new \Galette\Filters\MailingsList();
        $mh = new \Galette\Core\MailingHistory(
            $this->zdb,
            $this->login,
            $this->preferences,
            $filters,
            $mailing
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
}
