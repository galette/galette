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

use Galette\Core\CheckModules;
use Galette\Core\Install;
use Galette\Core\Installation\Step\CheckStep;
use Galette\Core\Installation\StepResult;
use PHPUnit\Framework\TestCase;

/**
 * Check step and components tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CheckStepTest extends TestCase
{
    private Install $install;

    /**
     * Set up tests
     */
    protected function setUp(): void
    {
        $this->install = new Install();
    }

    /**
     * Test CheckModules instantiation
     */
    public function testCheckModulesInstantiation(): void
    {
        $cm = new CheckModules();
        $this->assertInstanceOf(CheckModules::class, $cm);
    }

    /**
     * Test CheckModules returns arrays for goods, missings, shoulds
     */
    public function testCheckModulesReturnsArrays(): void
    {
        $cm = new CheckModules();

        $goods = $cm->getGoods();
        $missings = $cm->getMissings();
        $shoulds = $cm->getShoulds();

        $this->assertIsArray($goods);
        $this->assertIsArray($missings);
        $this->assertIsArray($shoulds);
    }

    /**
     * Test CheckModules isValid returns boolean
     */
    public function testCheckModulesIsValidReturnsBool(): void
    {
        $cm = new CheckModules();
        $this->assertIsBool($cm->isValid());
    }

    /**
     * Test CheckStep instantiation
     */
    public function testCheckStepInstantiation(): void
    {
        $step = new CheckStep($this->install);
        $this->assertInstanceOf(CheckStep::class, $step);
    }

    /**
     * Test CheckStep has correct name
     */
    public function testCheckStepName(): void
    {
        $step = new CheckStep($this->install);
        $this->assertEquals('check', $step->getStepName());
    }

    /**
     * Test CheckStep has correct order
     */
    public function testCheckStepOrder(): void
    {
        $step = new CheckStep($this->install);
        $this->assertEquals(10, $step->getOrder());
    }

    /**
     * Test CheckStep execute returns StepResult
     */
    public function testCheckStepExecuteReturnsStepResult(): void
    {
        $step = new CheckStep($this->install);
        $result = $step->execute();

        $this->assertInstanceOf(StepResult::class, $result);
    }

    /**
     * Test CheckStep result has report
     */
    public function testCheckStepResultHasReport(): void
    {
        $step = new CheckStep($this->install);
        $result = $step->execute();

        $this->assertTrue($result->hasReport());
        $this->assertIsArray($result->getReport());
    }

    /**
     * Test CheckStep result requiresDisplay depends on success
     */
    public function testCheckStepRequiresDisplayDependsOnSuccess(): void
    {
        $step = new CheckStep($this->install);
        $result = $step->execute();

        // If success, CheckStep doesn't require display (can auto-advance)
        // If failure, it requires display to show errors
        if ($result->isSuccess()) {
            $this->assertFalse($result->requiresDisplay());
        } else {
            $this->assertTrue($result->requiresDisplay());
        }
    }

    /**
     * Test CheckStep canSkipDisplay returns false
     */
    public function testCheckStepCannotSkipDisplay(): void
    {
        $step = new CheckStep($this->install);
        $this->assertFalse($step->canSkipDisplay());
    }

    /**
     * Test CheckStep requiresUserInput returns false
     */
    public function testCheckStepDoesNotRequireUserInput(): void
    {
        $step = new CheckStep($this->install);
        $this->assertFalse($step->requiresUserInput());
    }
}
