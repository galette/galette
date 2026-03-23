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
 * Database installation/upgrade step
 *
 * Executes SQL scripts for installation or upgrade.
 * Report is shown in modal on success.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DatabaseInstallStep extends AbstractStep
{
    public const STEP_NAME = 'database_install';
    public const STEP_ORDER = 60;

    public function execute(array $data = []): StepResult
    {
        // TODO: Full implementation in Phase 4
        // Report should be available even on success (shown in modal)
        return StepResult::success(
            [],
            false, // Auto-advance
            [] // Report will go here
        );
    }

    public function canSkipDisplay(): bool
    {
        return true; // Can skip, but report shown in modal
    }

    public function getStepName(): string
    {
        return self::STEP_NAME;
    }

    public function getStepTitle(): string
    {
        if ($this->install->isInstall()) {
            return _T("Tables Creation");
        }
        return _T("Database upgrade");
    }

    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }
}
