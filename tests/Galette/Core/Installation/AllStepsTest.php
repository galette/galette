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

namespace Galette\Core\Installation\test\units;

use Galette\Core\Install;
use Galette\Core\Installation\StepInterface;
use Galette\Core\Installation\StepResult;
use Galette\Core\Installation\Step\AdminStep;
use Galette\Core\Installation\Step\CheckStep;
use Galette\Core\Installation\Step\DatabaseCheckStep;
use Galette\Core\Installation\Step\DatabaseInstallStep;
use Galette\Core\Installation\Step\DatabaseStep;
use Galette\Core\Installation\Step\EndStep;
use Galette\Core\Installation\Step\TelemetryStep;
use Galette\Core\Installation\Step\TypeStep;
use Galette\Core\Installation\Step\VersionSelectionStep;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all installation step classes metadata and contracts
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AllStepsTest extends TestCase
{
    private Install $install;

    /**
     * Initialize a fresh Install instance shared by all tests in this class
     */
    protected function setUp(): void
    {
        $this->install = new Install();
    }

    /**
     * @return array<string, array{class-string<StepInterface>}>
     */
    public static function provideStepClasses(): array
    {
        return [
            'CheckStep'            => [CheckStep::class],
            'TypeStep'             => [TypeStep::class],
            'DatabaseStep'         => [DatabaseStep::class],
            'DatabaseCheckStep'    => [DatabaseCheckStep::class],
            'VersionSelectionStep' => [VersionSelectionStep::class],
            'DatabaseInstallStep'  => [DatabaseInstallStep::class],
            'AdminStep'            => [AdminStep::class],
            'TelemetryStep'        => [TelemetryStep::class],
            'EndStep'              => [EndStep::class],
        ];
    }

    /**
     * @return array<string, array{class-string<StepInterface>}>
     */
    public static function provideExecutableStepClasses(): array
    {
        return [
            'CheckStep'     => [CheckStep::class],
            'TypeStep'      => [TypeStep::class],
            'AdminStep'     => [AdminStep::class],
            'TelemetryStep' => [TelemetryStep::class],
            'EndStep'       => [EndStep::class],
        ];
    }

    /**
     * @dataProvider provideStepClasses
     * @param class-string<StepInterface> $class
     */
    public function testStepInstantiation(string $class): void
    {
        $step = new $class($this->install);
        $this->assertInstanceOf(StepInterface::class, $step);
    }

    /**
     * @dataProvider provideStepClasses
     * @param class-string<StepInterface> $class
     */
    public function testStepNameIsNonEmptyString(string $class): void
    {
        $step = new $class($this->install);
        $name = $step->getStepName();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    /**
     * @dataProvider provideStepClasses
     * @param class-string<StepInterface> $class
     */
    public function testStepTitleIsNonEmptyString(string $class): void
    {
        $step = new $class($this->install);
        $title = $step->getStepTitle();
        $this->assertIsString($title);
        $this->assertNotEmpty($title);
    }

    /**
     * @dataProvider provideStepClasses
     * @param class-string<StepInterface> $class
     */
    public function testStepOrderIsReasonable(string $class): void
    {
        $step = new $class($this->install);
        $order = $step->getOrder();
        $this->assertIsInt($order);
        $this->assertGreaterThanOrEqual(10, $order);
        $this->assertLessThanOrEqual(100, $order);
    }

    /**
     * @dataProvider provideStepClasses
     * @param class-string<StepInterface> $class
     */
    public function testCanSkipDisplayReturnsBool(string $class): void
    {
        $step = new $class($this->install);
        $this->assertIsBool($step->canSkipDisplay());
    }

    /**
     * @dataProvider provideStepClasses
     * @param class-string<StepInterface> $class
     */
    public function testRequiresUserInputReturnsBool(string $class): void
    {
        $step = new $class($this->install);
        $this->assertIsBool($step->requiresUserInput());
    }

    /**
     * @dataProvider provideExecutableStepClasses
     * @param class-string<StepInterface> $class
     */
    public function testExecuteReturnsStepResult(string $class): void
    {
        $step = new $class($this->install);
        $result = $step->execute();
        $this->assertInstanceOf(StepResult::class, $result);
    }
}
