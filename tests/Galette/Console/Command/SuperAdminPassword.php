<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\SuperAdminPassword as SuperAdminPasswordCommand;
use Galette\Core\Preferences;
use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * SuperAdminPassword command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SuperAdminPassword extends GaletteTestCase
{
    /**
     * Run the command with the given hidden answers
     *
     * @param array<int, string>   $inputs  Answers to the hidden questions
     * @param array<string, mixed> $options Tester options
     */
    private function runCommand(array $inputs, array $options = []): CommandTester
    {
        $command = new SuperAdminPasswordCommand(GALETTE_ROOT);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute([], $options);
        return $commandTester;
    }

    /**
     * Get super administrator password as it is stored in database
     */
    private function getStoredPassword(): string
    {
        $select = $this->zdb->select(Preferences::TABLE);
        $select->columns(['val_pref'])->where(['nom_pref' => 'pref_admin_pass']);
        $results = $this->zdb->execute($select);

        return (string)$results->current()->val_pref;
    }

    /**
     * Test password is changed and stored hashed
     */
    public function testChangesStoredPassword(): void
    {
        $password = 'Str0ng!Passw0rd';
        $commandTester = $this->runCommand([$password, $password]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Super administrator password has been changed.',
            $commandTester->getDisplay()
        );
        //the login is displayed, so one knows which account has been changed
        $this->assertStringContainsString(
            $this->preferences->pref_admin_login,
            $commandTester->getDisplay()
        );

        $stored = $this->getStoredPassword();
        $this->assertNotSame($password, $stored);
        $this->assertTrue(password_verify($password, $stored));
    }

    /**
     * Test a mistyped confirmation leaves the password untouched
     */
    public function testRefusesMismatchingConfirmation(): void
    {
        $original = $this->getStoredPassword();

        $commandTester = $this->runCommand(['Str0ng!Passw0rd', 'Str0ng!Passw0rb']);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Passwords mismatch', $commandTester->getDisplay());
        $this->assertSame($original, $this->getStoredPassword());
    }

    /**
     * Test a password shorter than pref_password_length is refused
     */
    public function testRefusesPasswordAgainstPolicy(): void
    {
        $original = $this->getStoredPassword();
        $short = str_repeat('a', $this->preferences->pref_password_length - 1);

        $commandTester = $this->runCommand([$short, $short]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Too short', $commandTester->getDisplay());
        $this->assertSame($original, $this->getStoredPassword());
    }

    /**
     * Test the command refuses to run without a terminal to prompt on
     */
    public function testRefusesNonInteractiveRun(): void
    {
        $original = $this->getStoredPassword();

        $commandTester = $this->runCommand([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'This command requires an interactive terminal.',
            $commandTester->getDisplay()
        );
        $this->assertSame($original, $this->getStoredPassword());
    }
}
