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

/**
 * Installation type selection step
 *
 * Allows user to choose between fresh installation and upgrade.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TypeStep extends AbstractStep
{
    public const STEP_NAME = 'type';
    public const STEP_ORDER = 20;

    /**
     * Execute type selection
     *
     * @param array<string, mixed> $data
     */
    public function execute(array $data = []): StepResult
    {
        // TODO: Implement in Phase 5
        // For now, delegate to existing Install class
        return StepResult::success(
            [],
            true // Must display the form
        );
    }

    public function requiresUserInput(): bool
    {
        return true;
    }

    public function canSkipDisplay(): bool
    {
        return false; // Must always show the form
    }

    public function getStepName(): string
    {
        return self::STEP_NAME;
    }

    public function getStepTitle(): string
    {
        return _T("Installation mode");
    }

    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }
}
