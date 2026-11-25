<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\TwoFactorSuspend as TwoFactorSuspendCommand;
use Galette\Core\TwoFactorAuth;
use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * TwoFactorSuspend command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorSuspend extends GaletteTestCase
{
    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->preferences->pref_2fa_suspended_until = 0;
        $this->preferences->store();
        parent::tearDown();
    }

    /**
     * Run the command
     *
     * @param array<string, mixed> $arguments Command arguments and options
     */
    private function runCommand(array $arguments): CommandTester
    {
        $command = $this->container->make(TwoFactorSuspendCommand::class, ['basepath' => GALETTE_ROOT]);
        $commandTester = new CommandTester($command);
        $commandTester->execute($arguments);
        return $commandTester;
    }

    /**
     * Suspends for a day by default, for the given hours otherwise
     */
    public function testSuspends(): void
    {
        $before = time();
        $tester = $this->runCommand([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $until = TwoFactorAuth::suspendedUntil($this->preferences);
        $this->assertNotNull($until);
        $this->assertGreaterThanOrEqual($before + 24 * 3600, $until);
        $this->assertLessThanOrEqual(time() + 24 * 3600, $until);

        $before = time();
        $tester = $this->runCommand(['--hours' => '2']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $until = TwoFactorAuth::suspendedUntil($this->preferences);
        $this->assertGreaterThanOrEqual($before + 2 * 3600, $until);
        $this->assertLessThanOrEqual(time() + 2 * 3600, $until);
        $this->assertStringContainsString('--resume', $tester->getDisplay());

        //stored, not only set on the instance at hand
        $this->preferences->load();
        $this->assertSame($until, TwoFactorAuth::suspendedUntil($this->preferences));
    }

    /**
     * Refuses a length that is no whole number of hours, or out of bounds
     */
    public function testRefusesInvalidHours(): void
    {
        foreach (['0', '-1', (string)(TwoFactorAuth::SUSPEND_MAX_HOURS + 1), 'abc', '1.5', ''] as $hours) {
            $tester = $this->runCommand(['--hours' => $hours]);
            $this->assertSame(Command::FAILURE, $tester->getStatusCode(), 'hours: ' . $hours);
            $this->assertNull(TwoFactorAuth::suspendedUntil($this->preferences), 'hours: ' . $hours);
        }

        $tester = $this->runCommand(['--hours' => (string)TwoFactorAuth::SUSPEND_MAX_HOURS]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * A history that cannot be written does not turn a saved change into a
     * failure
     */
    public function testHistoryFailureKeepsTheResult(): void
    {
        $history = $this->createStub(\Galette\Core\History::class);
        $history->method('add')->willThrowException(new \RuntimeException('history is down'));
        $command = $this->container->make(TwoFactorSuspendCommand::class, ['basepath' => GALETTE_ROOT]);
        $property = new \ReflectionProperty($command, 'history');
        $property->setValue($command, $history);

        $tester = new CommandTester($command);
        $tester->execute(['--hours' => '1']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertNotNull(TwoFactorAuth::suspendedUntil($this->preferences));
        $this->assertStringContainsString('could not be recorded in the history', $tester->getDisplay());

        $tester->execute(['--resume' => true]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertNull(TwoFactorAuth::suspendedUntil($this->preferences));
        $this->assertStringContainsString('could not be recorded in the history', $tester->getDisplay());
    }

    /**
     * Ends a running suspension, and says so when there is none
     */
    public function testResume(): void
    {
        $tester = $this->runCommand(['--resume' => true]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('not suspended', $tester->getDisplay());

        $this->runCommand(['--hours' => '1']);
        $this->assertNotNull(TwoFactorAuth::suspendedUntil($this->preferences));

        $tester = $this->runCommand(['--resume' => true]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertNull(TwoFactorAuth::suspendedUntil($this->preferences));
        $this->preferences->load();
        $this->assertNull(TwoFactorAuth::suspendedUntil($this->preferences));
    }
}
