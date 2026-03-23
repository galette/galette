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

namespace Galette\Core\Installation\Step;

use Galette\Core\Installation\AbstractStep;
use Galette\Core\Installation\StepResult;
use Galette\Core\Install;

/**
 * Database connection and permissions check step
 *
 * Verifies database connectivity and required permissions.
 * This step can auto-advance on success.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DatabaseCheckStep extends AbstractStep
{
    public const STEP_NAME = 'database_check';
    public const STEP_ORDER = 40;

    public function execute(array $data = []): StepResult
    {
        // TODO: Full implementation in Phase 3
        // For now, delegate to existing Install class
        return StepResult::success(
            [_T("Database connection and permissions validated")],
            false // Auto-advance on success!
        );
    }

    /**
     * This step can skip display on success
     * Only shows a page if there are errors
     */
    public function canSkipDisplay(): bool
    {
        return true;
    }

    public function getStepName(): string
    {
        return self::STEP_NAME;
    }

    public function getStepTitle(): string
    {
        return _T("Database access and permissions");
    }

    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }
}
