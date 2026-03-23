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

namespace Galette\Core\Installation;

use Galette\Core\Install;

/**
 * Abstract base class for installation steps
 *
 * Provides common functionality for installation steps,
 * reducing boilerplate in concrete implementations.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractStep implements StepInterface
{
    /**
     * Constructor
     *
     * @param Install $install Install instance
     */
    public function __construct(protected Install $install)
    {
    }

    /**
     * Default validation: always passes
     *
     * Override in subclasses that need validation.
     *
     * @param array<string, mixed> $data Data to validate
     */
    public function validate(array $data = []): StepResult
    {
        return StepResult::success();
    }

    /**
     * Default: step doesn't require user input
     *
     * Override in subclasses that need user input.
     */
    public function requiresUserInput(): bool
    {
        return false;
    }

    /**
     * Default: can skip display on success
     *
     * Override in subclasses that must always display.
     */
    public function canSkipDisplay(): bool
    {
        return true;
    }

    /**
     * Default: applicable to all modes
     *
     * Override in subclasses with specific mode requirements.
     *
     * @param string $mode Installation mode ('i' for install, 'u' for update)
     */
    public function isApplicable(string $mode): bool
    {
        return true;
    }

    /**
     * Get Install instance
     */
    protected function getInstall(): Install
    {
        return $this->install;
    }
}
