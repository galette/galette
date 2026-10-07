<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\TwoFactorReset as TwoFactorResetCommand;
use Galette\Core\AuthThrottle;
use Galette\Core\History;
use Galette\Core\TwoFactorAuth;
use Galette\Core\TwoFactorSecret;
use Galette\Core\TwoFactorSuperAdmin;
use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * TwoFactorReset command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorReset extends GaletteTestCase
{
    private const string SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->seed = 20261007;
        $this->preferences->pref_2fa_mode = TwoFactorAuth::MODE_OPTIONAL;
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Run the command
     *
     * @param array<string, mixed> $arguments Command arguments and options
     * @param array<int, string>   $inputs    Answers to the questions
     * @param array<string, mixed> $options   Tester options
     */
    private function runCommand(array $arguments, array $inputs = [], array $options = []): CommandTester
    {
        $command = new TwoFactorResetCommand(GALETTE_ROOT);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute($arguments, $options);
        return $commandTester;
    }

    /**
     * Enrol the super administrator
     */
    private function enrolSuperAdmin(): TwoFactorSuperAdmin
    {
        $superadmin = new TwoFactorSuperAdmin($this->preferences);
        $this->assertTrue($superadmin->create(self::SECRET));
        $this->assertTrue($superadmin->enable());
        $this->assertTrue($superadmin->setLastTimeslice(123456));
        return $superadmin;
    }

    /**
     * Enrol a member, recovery codes included
     *
     * @param int $id_adh Member identifier
     */
    private function enrolMember(int $id_adh): TwoFactorSecret
    {
        $secret = new TwoFactorSecret($this->zdb);
        $this->assertTrue($secret->create($id_adh, self::SECRET));
        $this->assertTrue($secret->enable());
        $this->assertTrue($secret->setLastTimeslice(123456));
        $this->assertCount(TwoFactorSecret::CODES_COUNT, $secret->generateRecoveryCodes());
        return $secret;
    }

    /**
     * Count rows of a member in a second factor table
     *
     * @param string $table  Table name
     * @param int    $id_adh Member identifier
     */
    private function countRows(string $table, int $id_adh): int
    {
        $select = $this->zdb->select($table);
        $select->where([TwoFactorSecret::PK => $id_adh]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Lock the second factor of an account
     *
     * @param string $login Account login
     */
    private function lockSecondFactor(string $login): AuthThrottle
    {
        $throttle = new AuthThrottle($this->zdb, $this->preferences);
        for ($i = 0; $i < $this->preferences->pref_throttle_second_factor_attempts; $i++) {
            $throttle->recordSecondFactorFailure($login);
        }
        $this->assertGreaterThan(0, $throttle->getSecondFactorDelay($login));
        return $throttle;
    }

    /**
     * Test the super administrator second factor is removed, and its lock lifted
     */
    public function testResetsSuperAdmin(): void
    {
        $this->enrolSuperAdmin();
        $throttle = $this->lockSecondFactor($this->preferences->pref_admin_login);

        $commandTester = $this->runCommand([], ['yes']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $this->assertStringContainsString($this->preferences->pref_admin_login, $commandTester->getDisplay());

        $superadmin = new TwoFactorSuperAdmin($this->preferences);
        $this->assertFalse($superadmin->isLoaded());
        $this->assertFalse($superadmin->isEnabled());
        $this->assertNull($superadmin->getLastTimeslice());
        $this->assertSame(0, $throttle->getSecondFactorDelay($this->preferences->pref_admin_login));

        //traced, by whom it has been done
        $select = $this->zdb->select(History::TABLE);
        $select->where(['action_log' => 'Two-factor authentication reset for ' . $this->preferences->pref_admin_login]);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());
        $this->assertSame($this->preferences->pref_admin_login, $results->current()->adh_log);
    }

    /**
     * Test a member second factor is removed with its recovery codes, and its lock lifted
     */
    public function testResetsMember(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $this->enrolMember($member->id);
        $throttle = $this->lockSecondFactor($member->login);

        $commandTester = $this->runCommand(['--login' => $member->login, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $this->assertSame(0, $this->countRows(TwoFactorSecret::TABLE, $member->id));
        $this->assertSame(0, $this->countRows(TwoFactorSecret::CODES_TABLE, $member->id));
        $this->assertSame(0, $throttle->getSecondFactorDelay($member->login));
    }

    /**
     * Test --clock keeps the second factor and only forgets the last used slice
     */
    public function testClockForgetsTimesliceOnly(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $this->enrolMember($member->id);
        $this->enrolSuperAdmin();

        $commandTester = $this->runCommand(['--login' => $member->login, '--clock' => true, '--force' => true]);
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());

        $secret = new TwoFactorSecret($this->zdb);
        $this->assertTrue($secret->load($member->id));
        $this->assertTrue($secret->isEnabled());
        $this->assertSame(self::SECRET, $secret->getSecret());
        $this->assertNull($secret->getLastTimeslice());
        $this->assertSame(TwoFactorSecret::CODES_COUNT, $secret->countRemainingCodes());

        $commandTester = $this->runCommand(['--clock' => true, '--force' => true]);
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());

        $superadmin = new TwoFactorSuperAdmin($this->preferences);
        $this->assertTrue($superadmin->isEnabled());
        $this->assertSame(self::SECRET, $superadmin->getSecret());
        $this->assertNull($superadmin->getLastTimeslice());
    }

    /**
     * Test --policy-off disables the policy and keeps second factors
     */
    public function testPolicyOff(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $this->enrolMember($member->id);
        $this->enrolSuperAdmin();

        $commandTester = $this->runCommand(['--policy-off' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $this->assertSame(TwoFactorAuth::MODE_DISABLED, TwoFactorAuth::modeFrom($this->preferences));
        $this->assertSame(1, $this->countRows(TwoFactorSecret::TABLE, $member->id));
        $this->assertTrue((new TwoFactorSuperAdmin($this->preferences))->isEnabled());

        //stored, not only changed in memory
        $select = $this->zdb->select(\Galette\Core\Preferences::TABLE);
        $select->where(['nom_pref' => 'pref_2fa_mode']);
        $this->assertSame(TwoFactorAuth::MODE_DISABLED, (int)$this->zdb->execute($select)->current()->val_pref);

        //and cannot be mixed with an account
        $commandTester = $this->runCommand(['--policy-off' => true, '--login' => $member->login, '--force' => true]);
        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    /**
     * Test an unknown login is refused, an email address included
     */
    public function testRefusesUnknownLogin(): void
    {
        $member = $this->createMember($this->dataAdherentOne());
        $this->enrolMember($member->id);

        foreach (['nobody-has-this-login', $member->email] as $login) {
            $commandTester = $this->runCommand(['--login' => $login, '--force' => true]);
            $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
            $this->assertStringContainsString('No member with login', $commandTester->getDisplay());
        }
        $this->assertSame(1, $this->countRows(TwoFactorSecret::TABLE, $member->id));
    }

    /**
     * Test an account without second factor is reported and left alone
     */
    public function testNothingToReset(): void
    {
        $member = $this->createMember($this->dataAdherentOne());

        $commandTester = $this->runCommand(['--login' => $member->login, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'This account has no two-factor authentication.',
            $commandTester->getDisplay()
        );
    }

    /**
     * Test nothing changes without confirmation
     */
    public function testRequiresConfirmation(): void
    {
        $this->enrolSuperAdmin();

        $commandTester = $this->runCommand([], ['no']);
        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertTrue((new TwoFactorSuperAdmin($this->preferences))->isEnabled());

        $commandTester = $this->runCommand([], [], ['interactive' => false]);
        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('from an interactive terminal', $commandTester->getDisplay());
        $this->assertTrue((new TwoFactorSuperAdmin($this->preferences))->isEnabled());
    }
}
