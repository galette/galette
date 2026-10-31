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
use Galette\Console\Command\FeatureStatus;
use Galette\Core\FeatureFlagManager;
use Galette\Core\Preferences;
use Galette\Tests\GaletteTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Safe\preg_replace;

/**
 * Experimental features console commands tests class
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
     * Features stored in database
     *
     * @return array<string>
     */
    private function getStored(): array
    {
        return (new Preferences($this->zdb))->getFeatureFlags();
    }

    /**
     * Turning a feature on, then off
     */
    public function testEnableThenDisable(): void
    {
        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Features to turn on: ' . self::FLAG, $output);
        $this->assertStringContainsString('Experimental features have been turned on.', $output);
        $this->assertSame([self::FLAG], $this->getStored());
        $this->assertTrue((new FeatureFlagManager(new Preferences($this->zdb)))->isEnabled(self::FLAG));

        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('This feature is already turned on.', $tester->getDisplay());

        $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Experimental features have been turned off.', $tester->getDisplay());
        $this->assertSame([], $this->getStored());

        $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('No such feature is turned on.', $tester->getDisplay());
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
        //messages wrap at the terminal width
        $this->assertStringContainsString(
            'Run this command from an interactive terminal to confirm, or pass --force.',
            preg_replace('/\s+/', ' ', $tester->getDisplay())
        );
        $this->assertSame([self::FLAG], $this->getStored());

        $tester = $this->runCommand(new FeatureDisable(GALETTE_ROOT), ['--all' => true, '--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertSame([], $this->getStored());
    }

    /**
     * Only registered features can be turned on
     */
    public function testEnableRefusesUnknown(): void
    {
        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => 'unknown', '--force' => true]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Feature flag "unknown" is not registered.', $output);
        //the ones that are get listed
        $this->assertMatchesRegularExpression('/Experimental features: .*' . preg_quote(self::FLAG, '/') . '/', $output);
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

    /**
     * Status lists registered features, and tells which ones are on
     */
    public function testStatus(): void
    {
        $tester = $this->runCommand(new FeatureStatus(GALETTE_ROOT));
        $tester->assertCommandIsSuccessful();
        $output = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString(self::FLAG, $output);
        $this->assertStringContainsString('Total registered flags:', $output);
        $this->assertStringContainsString('Enabled flags: 0', $output);
        $this->assertStringContainsString('No flags are currently enabled.', $output);

        $tester = $this->runCommand(new FeatureEnable(GALETTE_ROOT), ['flag' => self::FLAG, '--force' => true]);
        $tester->assertCommandIsSuccessful();

        $tester = $this->runCommand(new FeatureStatus(GALETTE_ROOT));
        $tester->assertCommandIsSuccessful();
        $output = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('ENABLED', $output);
        $this->assertStringContainsString('Enabled flags: 1', $output);
        $this->assertStringContainsString('1 feature flag(s) currently active.', $output);
    }
}
