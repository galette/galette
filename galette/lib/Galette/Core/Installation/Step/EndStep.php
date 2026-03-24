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
 * Installation end step
 *
 * This step performs initialization (config file + objects) and displays
 * the final result. It merges the former GaletteInitStep functionality.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class EndStep extends AbstractStep
{
    public const STEP_NAME = 'end';
    public const STEP_ORDER = 100;

    /**
     * Trigger final display; config file writing and object initialization are handled in the view.
     *
     * @param array<string, mixed> $data Execution context data
     */
    public function execute(array $data = []): StepResult
    {
        // This step performs initialization AND displays the result
        // The actual initialization (writeConfFile, initObjects) is done in the view
        // to maintain compatibility with the existing error handling and retry mechanism
        return StepResult::success(
            [],
            requiresDisplay: true
        );
    }

    /**
     * Final result page must always be displayed
     */
    public function canSkipDisplay(): bool
    {
        return false;
    }

    /**
     * Get step identifier
     */
    public function getStepName(): string
    {
        return self::STEP_NAME;
    }

    /**
     * Get localized step title
     */
    public function getStepTitle(): string
    {
        return _T("End!");
    }

    /**
     * Get step execution order
     */
    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }
}
