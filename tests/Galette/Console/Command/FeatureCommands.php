<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\FeatureDisable;
use Galette\Console\Command\FeatureEnable;
use Galette\Core\FeatureFlagManager;
use Galette\Core\Preferences;
use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Preview features console commands tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FeatureCommands extends GaletteTestCase
{
    private const string FLAG = 'two-factor-required';

    /**
     * Run a command
     *
     * @param Command              $command   Command to run
     * @param array<string, mixed> $arguments Command arguments and options
     * @param array<int, string>   $inputs    Answers to the questions
     * @param array<string, mixed> $options   Tester options
     */
    private function runCommand(
        Command $command,
        array $arguments = [],
        array $inputs = [],
        array $options = []
    ): CommandTester {
        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute($arguments, $options);
        return $commandTester;
    }

    /**
     * Preview features stored in database
     *
     * @return array<string>
     */
    private function getStored(): array
    {
        return (new Preferences($this->zdb))->getFeatureFlags();
    }

    /**
     * Turning a preview feature on, then off
     */
    public function testEnableThenDisable(): void
    {
        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Features to turn on: ' . self::FLAG, $output);
        //what is at stake is shown before anything is done
        $this->assertStringContainsString('galette:twofactor:reset', $output);
        $this->assertStringContainsString('Preview features have been turned on.', $output);
        $this->assertSame([self::FLAG], $this->getStored());
        $this->assertTrue((new FeatureFlagManager(new Preferences($this->zdb)))->isEnabled(self::FLAG));

        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('This preview feature is already turned on.', $tester->getDisplay());

        $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Preview features have been turned off.', $tester->getDisplay());
        $this->assertSame([], $this->getStored());

        $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('No such preview feature is turned on.', $tester->getDisplay());
    }

    /**
     * Confirmation is asked, and a refusal changes nothing
     */
    public function testConfirmation(): void
    {
        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => self::FLAG], ['no']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Nothing has been changed.', $tester->getDisplay());
        $this->assertSame([], $this->getStored());

        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => self::FLAG], ['yes']);
        $tester->assertCommandIsSuccessful();
        $this->assertSame([self::FLAG], $this->getStored());

        //unattended, --force is required
        $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), ['--all' => true], options: ['interactive' => false]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('pass --force', $tester->getDisplay());
        $this->assertSame([self::FLAG], $this->getStored());

        $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), ['--all' => true, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertSame([], $this->getStored());
    }

    /**
     * Only preview features can be turned on
     */
    public function testEnableRefusesNonPreview(): void
    {
        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => 'acls', '--force' => true]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Feature flag "acls" is not a preview feature.', $output);
        //the ones that are get listed
        $this->assertStringContainsString('Preview features: ' . self::FLAG, $output);
        $this->assertSame([], $this->getStored());
    }

    /**
     * Disabling wants either a flag or --all, not both nor none
     */
    public function testDisableArguments(): void
    {
        foreach ([[], ['flag' => self::FLAG, '--all' => true]] as $arguments) {
            $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), $arguments + ['--force' => true]);
            $this->assertSame(Command::FAILURE, $tester->getStatusCode());
            $this->assertStringContainsString('Give either a feature flag or --all.', $tester->getDisplay());
        }
    }
}
