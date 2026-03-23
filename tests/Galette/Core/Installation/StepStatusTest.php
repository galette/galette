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
use PHPUnit\Framework\TestCase;

/**
 * Step status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StepStatusTest extends TestCase
{
    /**
     * Test status enum values
     */
    public function testEnumValues(): void
    {
        $this->assertEquals('success', StepStatus::SUCCESS->value);
        $this->assertEquals('error', StepStatus::ERROR->value);
        $this->assertEquals('warning', StepStatus::WARNING->value);
        $this->assertEquals('info', StepStatus::INFO->value);
    }

    /**
     * Test isSuccess method
     */
    public function testIsSuccess(): void
    {
        $this->assertTrue(StepStatus::SUCCESS->isSuccess());
        $this->assertFalse(StepStatus::ERROR->isSuccess());
        $this->assertFalse(StepStatus::WARNING->isSuccess());
        $this->assertFalse(StepStatus::INFO->isSuccess());
    }

    /**
     * Test isError method
     */
    public function testIsError(): void
    {
        $this->assertTrue(StepStatus::ERROR->isError());
        $this->assertFalse(StepStatus::SUCCESS->isError());
        $this->assertFalse(StepStatus::WARNING->isError());
        $this->assertFalse(StepStatus::INFO->isError());
    }

    /**
     * Test isWarning method
     */
    public function testIsWarning(): void
    {
        $this->assertTrue(StepStatus::WARNING->isWarning());
        $this->assertFalse(StepStatus::SUCCESS->isWarning());
        $this->assertFalse(StepStatus::ERROR->isWarning());
        $this->assertFalse(StepStatus::INFO->isWarning());
    }

    /**
     * Test CSS class mapping
     */
    public function testGetCssClass(): void
    {
        $this->assertEquals('green', StepStatus::SUCCESS->getCssClass());
        $this->assertEquals('red', StepStatus::ERROR->getCssClass());
        $this->assertEquals('orange', StepStatus::WARNING->getCssClass());
        $this->assertEquals('blue', StepStatus::INFO->getCssClass());
    }

    /**
     * Test icon name mapping
     */
    public function testGetIconName(): void
    {
        $this->assertEquals('check', StepStatus::SUCCESS->getIconName());
        $this->assertEquals('times', StepStatus::ERROR->getIconName());
        $this->assertEquals('exclamation triangle', StepStatus::WARNING->getIconName());
        $this->assertEquals('info circle', StepStatus::INFO->getIconName());
    }
}

