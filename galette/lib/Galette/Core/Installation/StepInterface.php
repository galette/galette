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

/**
 * Interface for installation steps
 *
 * Each installation step must implement this interface to be executed
 * by the installation workflow.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface StepInterface
{
    /**
     * Execute the step logic
     *
     * This method contains the business logic of the step.
     * It should perform the necessary operations and return
     * a StepResult indicating success or failure.
     *
     * @param array<string, mixed> $data Data from previous steps or user input
     *
     * @return StepResult Result of the step execution
     */
    public function execute(array $data = []): StepResult;

    /**
     * Validate prerequisites for this step
     *
     * Check if all required conditions are met before executing the step.
     * This is called before execute() to ensure the step can run.
     *
     * @param array<string, mixed> $data Data to validate
     *
     * @return StepResult Validation result
     */
    public function validate(array $data = []): StepResult;

    /**
     * Check if step requires user input
     *
     * Steps that require user input (e.g., database credentials, admin password)
     * should return true. Steps that only perform checks or automated tasks
     * should return false.
     */
    public function requiresUserInput(): bool;

    /**
     * Get step name/identifier
     *
     * Returns a unique identifier for this step, used for routing
     * and step tracking.
     */
    public function getStepName(): string;

    /**
     * Get human-readable step title
     *
     * Returns the localized title to display to the user.
     */
    public function getStepTitle(): string;

    /**
     * Can skip display on success?
     *
     * If true, when the step succeeds, the installer can automatically
     * advance to the next step without showing a page.
     * If false, a page must always be shown, even on success.
     *
     * Example: Database checks can skip display on success,
     *          but admin configuration must always show a form.
     */
    public function canSkipDisplay(): bool;

    /**
     * Get step order/priority
     *
     * Returns the order in which this step should be executed.
     * Lower numbers are executed first.
     */
    public function getOrder(): int;

    /**
     * Check if step is applicable for current installation mode
     *
     * Some steps only apply to fresh install (e.g., admin creation),
     * others only to upgrades (e.g., version selection).
     *
     * @param string $mode Installation mode ('i' for install, 'u' for update)
     */
    public function isApplicable(string $mode): bool;
}
