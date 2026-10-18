<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\SeedFixtures as SeedFixturesCommand;
use Galette\Core\Plugins\FixturesContext;
use Galette\Entity\Adherent;
use Galette\Tests\GaletteTestCase;
use GaletteTest2Plugin\Fixtures;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * SeedFixtures command tests with plugins providing fixtures
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SeedFixturesPlugins extends GaletteTestCase
{
    protected bool $db_transactions = false;
    protected bool $load_plugins = true;

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
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        Fixtures::$calls = [];
    }

    /**
     * Clean fixtures after each test
     */
    public function tearDown(): void
    {
        $this->runSeed(['--clean' => true]);
        Fixtures::$calls = [];
        parent::tearDown();
    }

    /**
     * Test plugins are seeded after core fixtures, and cleaned before them
     */
    public function testPluginsFixtures(): void
    {
        $tester = $this->runSeed();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Seeding plugin plugin-test2', $tester->getDisplay());
        $this->assertStringContainsString('Nothing created', $tester->getDisplay());

        //cleaned before seeding, for idempotence
        $this->assertSame(['clean', 'seed'], array_column(Fixtures::$calls, 'method'));

        $context = Fixtures::$calls[1]['context'];
        $this->assertInstanceOf(FixturesContext::class, $context);
        $this->assertTrue($context->login->isSuperAdmin());

        $select = $this->zdb->select(Adherent::TABLE);
        $select->columns([Adherent::PK]);
        $select->where(['fingerprint' => SeedFixturesCommand::FIXTURE_FINGERPRINT]);
        $select->order(Adherent::PK);
        $member_ids = [];
        foreach ($this->zdb->execute($select) as $row) {
            $member_ids[] = (int)$row->{Adherent::PK};
        }
        $this->assertSame($member_ids, array_values($context->members));
        $this->assertSame($member_ids[0], $context->getMemberId(0));
        //positions wrap around
        $this->assertSame($member_ids[1], $context->getMemberId(count($member_ids) + 1));

        $this->assertContains('Bureau', array_keys($context->groups));
        $this->assertSame($context->groups['Bureau'], $context->getGroupId(0));

        //plugins are cleaned while fixture members still exist
        $seed_calls = count(Fixtures::$calls);
        $tester = $this->runSeed(['--clean' => true]);
        $this->assertSame(0, $tester->getStatusCode());
        $calls = array_slice(Fixtures::$calls, $seed_calls);
        $this->assertSame(['clean'], array_column($calls, 'method'));
        $this->assertSame($member_ids, array_values($calls[0]['context']->members));
    }

    /**
     * Test only active plugins with a Fixtures class provide fixtures
     */
    public function testGetFixturesProviders(): void
    {
        //plugin-news has a Fixtures class, but is disabled
        $this->assertTrue($this->plugins->isDisabled('plugin-news'));

        $providers = $this->plugins->getFixturesProviders();
        $this->assertSame(['plugin-test2'], array_keys($providers));
        $this->assertInstanceOf(Fixtures::class, $providers['plugin-test2']);
    }

    /**
     * Test context without any fixture
     */
    public function testEmptyContext(): void
    {
        $context = new FixturesContext(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            history: $this->history,
            plugins: $this->plugins
        );
        $this->assertNull($context->getMemberId(0));
        $this->assertNull($context->getGroupId(3));
    }
}
