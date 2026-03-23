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
 * Result of an installation step execution
 *
 * This class encapsulates the outcome of an installation step,
 * including status, messages, and whether the step requires
 * user interaction or display.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StepResult
{
    /**
     * Constructor
     *
     * @param StepStatus           $status          Step execution status
     * @param array<int, string>   $messages        Messages to display to user
     * @param bool                 $requiresDisplay Whether step needs to display a page
     * @param array<string, mixed> $report          Detailed report (e.g., SQL queries execution)
     * @param mixed                $data            Additional data to pass to next step or view
     */
    public function __construct(
        private readonly StepStatus $status,
        private readonly array $messages = [],
        private readonly bool $requiresDisplay = true,
        private readonly ?array $report = null,
        private readonly mixed $data = null
    ) {
    }

    /**
     * Get step status
     */
    public function getStatus(): StepStatus
    {
        return $this->status;
    }

    /**
     * Check if step was successful
     */
    public function isSuccess(): bool
    {
        return $this->status->isSuccess();
    }

    /**
     * Check if step has errors
     */
    public function hasErrors(): bool
    {
        return $this->status->isError();
    }

    /**
     * Get messages
     *
     * @return array<int, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Check if step requires a page to be displayed
     *
     * When false, the installer can automatically advance to next step.
     * When true, a page must be shown to the user (either for input or to display results).
     */
    public function requiresDisplay(): bool
    {
        return $this->requiresDisplay;
    }

    /**
     * Check if installer should automatically advance to next step
     *
     * Auto-advance happens when:
     * - Step is successful
     * - AND step doesn't require display
     */
    public function shouldAutoAdvance(): bool
    {
        return $this->isSuccess() && !$this->requiresDisplay();
    }

    /**
     * Get detailed report
     *
     * @return array<string, mixed>|null
     */
    public function getReport(): ?array
    {
        return $this->report;
    }

    /**
     * Check if step has a report
     */
    public function hasReport(): bool
    {
        return $this->report !== null && count($this->report) > 0;
    }

    /**
     * Get additional data
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Create a success result
     *
     * @param array<int, string>   $messages        Success messages
     * @param bool                 $requiresDisplay Whether to show a page
     * @param array<string, mixed> $report          Optional detailed report
     * @param mixed                $data            Optional additional data
     */
    public static function success(
        array $messages = [],
        bool $requiresDisplay = false,
        ?array $report = null,
        mixed $data = null
    ): self {
        return new self(
            StepStatus::SUCCESS,
            $messages,
            $requiresDisplay,
            $report,
            $data
        );
    }

    /**
     * Create an error result
     *
     * @param array<int, string>   $messages Error messages
     * @param array<string, mixed> $report   Optional detailed report
     * @param mixed                $data     Optional additional data
     */
    public static function error(
        array $messages,
        ?array $report = null,
        mixed $data = null
    ): self {
        return new self(
            StepStatus::ERROR,
            $messages,
            true, // Always display errors
            $report,
            $data
        );
    }

    /**
     * Create a warning result
     *
     * @param array<int, string>   $messages        Warning messages
     * @param bool                 $requiresDisplay Whether to show a page
     * @param array<string, mixed> $report          Optional detailed report
     * @param mixed                $data            Optional additional data
     */
    public static function warning(
        array $messages,
        bool $requiresDisplay = true,
        ?array $report = null,
        mixed $data = null
    ): self {
        return new self(
            StepStatus::WARNING,
            $messages,
            $requiresDisplay,
            $report,
            $data
        );
    }
}
