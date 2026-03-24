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
use Galette\Core\Installation\Step\AdminStep;
use PHPUnit\Framework\TestCase;

/**
 * AdminStep behavioral tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdminStepTest extends TestCase
{
    private AdminStep $step;

    /**
     * Initialize AdminStep with a fresh Install instance
     */
    protected function setUp(): void
    {
        $this->step = new AdminStep(new Install());
    }

    /**
     * Admin step must be included when installing from scratch
     */
    public function testApplicableForInstall(): void
    {
        $this->assertTrue($this->step->isApplicable(Install::INSTALL));
    }

    /**
     * Admin step must be skipped during upgrades (accounts already exist)
     */
    public function testNotApplicableForUpdate(): void
    {
        $this->assertFalse($this->step->isApplicable(Install::UPDATE));
    }

    /**
     * Admin step requires the user to fill in credentials
     */
    public function testRequiresUserInput(): void
    {
        $this->assertTrue($this->step->requiresUserInput());
    }

    /**
     * Admin credentials form must always be shown, never silently skipped
     */
    public function testCannotSkipDisplay(): void
    {
        $this->assertFalse($this->step->canSkipDisplay());
    }

    /**
     * Execute must return a successful result that requires display
     */
    public function testExecuteRequiresDisplay(): void
    {
        $result = $this->step->execute();
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->requiresDisplay());
    }
}
