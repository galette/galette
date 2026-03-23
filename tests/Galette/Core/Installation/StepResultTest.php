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

use Galette\Core\Installation\StepStatus;
use Galette\Core\Installation\StepResult;
use PHPUnit\Framework\TestCase;

/**
 * Step result tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StepResultTest extends TestCase
{
    /**
     * Test success result creation
     */
    public function testSuccessResult(): void
    {
        $result = StepResult::success(['Test message']);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->hasErrors());
        $this->assertEquals(['Test message'], $result->getMessages());
        $this->assertFalse($result->requiresDisplay());
        $this->assertTrue($result->shouldAutoAdvance());
    }

    /**
     * Test error result creation
     */
    public function testErrorResult(): void
    {
        $result = StepResult::error(['Error message']);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->hasErrors());
        $this->assertEquals(['Error message'], $result->getMessages());
        $this->assertTrue($result->requiresDisplay());
        $this->assertFalse($result->shouldAutoAdvance());
    }

    /**
     * Test warning result creation
     */
    public function testWarningResult(): void
    {
        $result = StepResult::warning(['Warning message'], false);

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->hasErrors());
        $this->assertEquals(['Warning message'], $result->getMessages());
        $this->assertFalse($result->requiresDisplay());
    }

    /**
     * Test result with report
     */
    public function testResultWithReport(): void
    {
        $report = ['query1' => true, 'query2' => false];
        $result = StepResult::success([], false, $report);

        $this->assertTrue($result->hasReport());
        $this->assertEquals($report, $result->getReport());
    }

    /**
     * Test result with data
     */
    public function testResultWithData(): void
    {
        $data = ['key' => 'value'];
        $result = StepResult::success([], false, null, $data);

        $this->assertEquals($data, $result->getData());
    }

    /**
     * Test auto-advance logic
     */
    public function testAutoAdvanceLogic(): void
    {
        // Success + no display = auto-advance
        $result1 = StepResult::success([], false);
        $this->assertTrue($result1->shouldAutoAdvance());

        // Success + requires display = no auto-advance
        $result2 = StepResult::success([], true);
        $this->assertFalse($result2->shouldAutoAdvance());

        // Error = no auto-advance
        $result3 = StepResult::error(['Error']);
        $this->assertFalse($result3->shouldAutoAdvance());
    }

    /**
     * Test status access
     */
    public function testStatusAccess(): void
    {
        $result = StepResult::success();
        $this->assertEquals(StepStatus::SUCCESS, $result->getStatus());
        $this->assertTrue($result->getStatus()->isSuccess());
    }
}

