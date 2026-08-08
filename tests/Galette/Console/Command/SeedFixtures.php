<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\SeedFixtures as SeedFixturesCommand;
use Galette\Core\MailingHistory;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Tests\GaletteTestCase;
use Safe\DateTime;
use Symfony\Component\Console\Tester\CommandTester;

use function Safe\json_decode;

/**
 * SeedFixtures command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SeedFixtures extends GaletteTestCase
{
    protected bool $db_transactions = false;

    /**
     * Run the seed command and return the tester
     *
     * @param array<string, mixed> $options
     */
    private function runSeed(array $options = []): CommandTester
    {
        $command = new SeedFixturesCommand('');
        $commandTester = new CommandTester($command);
        $commandTester->execute($options);
        return $commandTester;
    }

    /**
     * Clean fixtures after each test
     */
    public function tearDown(): void
    {
        $this->runSeed(['--clean' => true]);
        parent::tearDown();
    }

    /**
     * Get identifiers of all seeded members
     *
     * @return array<int, int>
     */
    private function getFixtureMemberIds(): array
    {
        $select = $this->zdb->select(Adherent::TABLE);
        $select->columns(['id_adh']);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $results = $this->zdb->execute($select);

        $member_ids = [];
        foreach ($results as $row) {
            $member_ids[] = (int)$row->id_adh;
        }
        return $member_ids;
    }

    /**
     * Test that seeding creates the expected number of members
     */
    public function testSeedCreatesMembers(): void
    {
        $tester = $this->runSeed();
        $this->assertSame(0, $tester->getStatusCode());

        $select = $this->zdb->select(Adherent::TABLE);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $results = $this->zdb->execute($select);

        $this->assertGreaterThanOrEqual(30, $results->count());
    }

    /**
     * Test that seeding is idempotent
     */
    public function testSeedIsIdempotent(): void
    {
        $this->runSeed();

        $select = $this->zdb->select(Adherent::TABLE);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $first_count = $this->zdb->execute($select)->count();

        // Run again
        $this->runSeed();

        $select = $this->zdb->select(Adherent::TABLE);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $second_count = $this->zdb->execute($select)->count();

        $this->assertSame($first_count, $second_count);
    }

    /**
     * Test that seeding creates dynamic fields
     */
    public function testSeedCreatesDynamicFields(): void
    {
        $this->runSeed();

        // Check member dynamic fields
        $select = $this->zdb->select(DynamicField::TABLE);
        $select->where(['field_form' => 'adh', 'field_name' => 'Univers fictif d\'origine']);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        // Check contribution dynamic fields
        $select = $this->zdb->select(DynamicField::TABLE);
        $select->where(['field_form' => 'contrib', 'field_name' => 'Mode de paiement alternatif']);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());
    }

    /**
     * Test that seeding creates groups with managers and members
     */
    public function testSeedCreatesGroups(): void
    {
        $this->runSeed();

        $select = $this->zdb->select(Group::TABLE);
        $select->where(['group_name' => 'Bureau']);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        $group_id = (int)$results->current()->id_group;

        // Check managers exist
        $select = $this->zdb->select(Group::GROUPSMANAGERS_TABLE);
        $select->where(['id_group' => $group_id]);
        $results = $this->zdb->execute($select);
        $this->assertGreaterThanOrEqual(1, $results->count());

        // Check members exist
        $select = $this->zdb->select(Group::GROUPSUSERS_TABLE);
        $select->where(['id_group' => $group_id]);
        $results = $this->zdb->execute($select);
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    /**
     * Test that seeding creates contributions
     */
    public function testSeedCreatesContributions(): void
    {
        $this->runSeed();

        $member_ids = $this->getFixtureMemberIds();

        $select = $this->zdb->select(Contribution::TABLE);
        $select->where->in('id_adh', $member_ids);
        $results = $this->zdb->execute($select);

        $this->assertGreaterThanOrEqual(20, $results->count());
    }

    /**
     * Test that seeding creates transactions
     */
    public function testSeedCreatesTransactions(): void
    {
        $this->runSeed();

        $member_ids = $this->getFixtureMemberIds();

        $select = $this->zdb->select(Transaction::TABLE);
        $select->where->in('id_adh', $member_ids);
        $results = $this->zdb->execute($select);

        $this->assertGreaterThanOrEqual(5, $results->count());
    }

    /**
     * Test that seeding creates mailings history entries
     */
    public function testSeedCreatesMailings(): void
    {
        $this->runSeed();

        $member_ids = $this->getFixtureMemberIds();

        $select = $this->zdb->select(MailingHistory::TABLE);
        $select->where->in('mailing_sender', $member_ids);
        $results = $this->zdb->execute($select);

        $this->assertGreaterThanOrEqual(50, $results->count());

        $sent = 0;
        $not_sent = 0;
        foreach ($results as $row) {
            $this->assertNotEmpty($row->mailing_subject);
            $this->assertNotEmpty($row->mailing_body);
            $this->assertNotEmpty($row->mailing_sender_name);
            $this->assertNotEmpty($row->mailing_sender_address);

            //dates are all in the past
            $this->assertLessThan(
                (new DateTime())->format('Y-m-d H:i:s'),
                $row->mailing_date
            );

            //recipients are stored as a JSON map of member id => "name <address>"
            $recipients = json_decode($row->mailing_recipients, true);
            $this->assertIsArray($recipients);
            $this->assertNotEmpty($recipients);
            foreach ($recipients as $id => $recipient) {
                $this->assertContains((int)$id, $member_ids);
                $this->assertMatchesRegularExpression('/^.+ <.+@.+>$/', $recipient);
            }

            if ($row->mailing_sent) {
                $sent++;
            } else {
                $not_sent++;
            }
        }

        //both sent mailings and drafts are seeded
        $this->assertGreaterThan(0, $sent);
        $this->assertGreaterThan(0, $not_sent);
    }

    /**
     * Test that seeded mailings are usable from MailingHistory
     */
    public function testSeededMailingsHistory(): void
    {
        $this->runSeed();
        $this->logSuperAdmin();

        $mh = new MailingHistory($this->zdb, $this->login, $this->preferences);
        $mh->filters->show = 0; //no pagination
        $list = $mh->getHistory();

        $this->assertGreaterThanOrEqual(50, count($list));

        //recipients have been decoded, and senders resolved from members table
        foreach ($list as $entry) {
            $this->assertIsArray($entry->mailing_recipients);
            $this->assertNotEmpty($entry->mailing_recipients);
            $this->assertNotNull($entry->mailing_sender_name);
            $this->assertSame(0, $entry->attachments);
        }

        //not sent mailings only
        $mh->filters->sent_filter = MailingHistory::FILTER_NOT_SENT;
        $not_sent = $mh->getHistory();
        $this->assertGreaterThan(0, count($not_sent));
        foreach ($not_sent as $entry) {
            $this->assertEquals(0, $entry->mailing_sent);
        }

        //sent mailings only
        $mh->filters->sent_filter = MailingHistory::FILTER_SENT;
        $sent = $mh->getHistory();
        $this->assertGreaterThan(0, count($sent));
        foreach ($sent as $entry) {
            $this->assertEquals(1, $entry->mailing_sent);
        }

        $this->assertSame(count($list), count($sent) + count($not_sent));
    }

    /**
     * Test that a seeded mailing can be loaded as a Mailing
     */
    public function testSeededMailingLoad(): void
    {
        $this->runSeed();
        $this->logSuperAdmin();

        $mh = new MailingHistory($this->zdb, $this->login, $this->preferences);
        $mh->filters->show = 0; //no pagination
        $mh->filters->subject_filter = 'assemblée générale';
        $list = $mh->getHistory();

        $this->assertGreaterThan(0, count($list));

        $mailing = new \Galette\Core\Mailing($this->preferences, []);
        $this->assertTrue(
            MailingHistory::loadFrom(
                zdb: $this->zdb,
                id: (int)$list[0]->mailing_id,
                mailing: $mailing,
                new: false
            )
        );

        $this->assertStringContainsStringIgnoringCase('assemblée générale', $mailing->subject);
        $this->assertNotEmpty($mailing->recipients);
    }

    /**
     * Test that clean removes all fixture data
     */
    public function testCleanRemovesFixtures(): void
    {
        $this->runSeed();

        // Verify data exists
        $select = $this->zdb->select(Adherent::TABLE);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $this->assertGreaterThan(0, $this->zdb->execute($select)->count());

        $member_ids = $this->getFixtureMemberIds();
        $select = $this->zdb->select(MailingHistory::TABLE);
        $select->where->in('mailing_sender', $member_ids);
        $this->assertGreaterThan(0, $this->zdb->execute($select)->count());

        // Clean
        $tester = $this->runSeed(['--clean' => true]);
        $this->assertSame(0, $tester->getStatusCode());

        // Verify members are gone
        $select = $this->zdb->select(Adherent::TABLE);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $this->assertSame(0, $this->zdb->execute($select)->count());

        // Verify mailings are gone as well (FK on mailing_sender is RESTRICT)
        $select = $this->zdb->select(MailingHistory::TABLE);
        $select->where->in('mailing_sender', $member_ids);
        $this->assertSame(0, $this->zdb->execute($select)->count());
    }

    /**
     * Test that parent-child relationships are created
     */
    public function testSeedCreatesParentChildRelationships(): void
    {
        $this->runSeed();

        $select = $this->zdb->select(Adherent::TABLE);
        $select->where([
            'fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT,
        ]);
        $select->where->isNotNull('parent_id');
        $results = $this->zdb->execute($select);

        $this->assertGreaterThanOrEqual(8, $results->count());
    }
}
