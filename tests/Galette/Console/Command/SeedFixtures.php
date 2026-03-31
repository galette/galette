<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\SeedFixtures as SeedFixturesCommand;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Tester\CommandTester;

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

        // Get fixture member IDs
        $select = $this->zdb->select(Adherent::TABLE);
        $select->columns(['id_adh']);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $results = $this->zdb->execute($select);
        $member_ids = [];
        foreach ($results as $row) {
            $member_ids[] = (int)$row->id_adh;
        }

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

        $select = $this->zdb->select(Adherent::TABLE);
        $select->columns(['id_adh']);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $results = $this->zdb->execute($select);
        $member_ids = [];
        foreach ($results as $row) {
            $member_ids[] = (int)$row->id_adh;
        }

        $select = $this->zdb->select(Transaction::TABLE);
        $select->where->in('id_adh', $member_ids);
        $results = $this->zdb->execute($select);

        $this->assertGreaterThanOrEqual(5, $results->count());
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

        // Clean
        $tester = $this->runSeed(['--clean' => true]);
        $this->assertSame(0, $tester->getStatusCode());

        // Verify members are gone
        $select = $this->zdb->select(Adherent::TABLE);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
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
