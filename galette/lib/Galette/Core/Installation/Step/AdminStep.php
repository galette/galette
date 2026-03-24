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
 * Administrator configuration step (install only)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdminStep extends AbstractStep
{
    public const STEP_NAME = 'admin';
    public const STEP_ORDER = 70;

    /**
     * Display administrator creation form; credentials are saved on form submission.
     *
     * @param array<string, mixed> $data Execution context data
     */
    public function execute(array $data = []): StepResult
    {
        // This step requires display (form with admin credentials)
        // Credentials are saved when form is submitted
        return StepResult::success(
            [],
            requiresDisplay: true
        );
    }

    /**
     * Only applicable for fresh installation; upgrades keep existing admin accounts
     *
     * @param string $mode Installation mode ('i' for install, 'u' for update)
     */
    public function isApplicable(string $mode): bool
    {
        return $mode === Install::INSTALL;
    }

    /**
     * User must provide administrator login and password
     */
    public function requiresUserInput(): bool
    {
        return true;
    }

    /**
     * Admin credentials form must always be displayed
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
        return _T("Admin parameters");
    }

    /**
     * Get step execution order
     */
    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }
}
