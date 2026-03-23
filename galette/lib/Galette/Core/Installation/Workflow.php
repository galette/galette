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
use Analog\Analog;

/**
 * Installation workflow manager
 *
 * Manages the sequence of installation steps and their execution.
 * Handles navigation between steps and tracks installation progress.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Workflow
{
    /** @var array<int, StepInterface> */
    private array $steps = [];

    private int $currentStepIndex = 0;

    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * Constructor
     *
     * @param Install $install Install instance
     */
    public function __construct(private readonly Install $install)
    {
    }

    /**
     * Add a step to the workflow
     *
     * @param StepInterface $step Step to add
     */
    public function addStep(StepInterface $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    /**
     * Build steps for the current installation mode
     *
     * Creates and registers all applicable steps based on installation mode.
     */
    public function buildSteps(): self
    {
        $mode = $this->install->getMode() ?? Install::INSTALL;

        // Order matters! Steps will be executed in this sequence
        $stepClasses = [
            Step\CheckStep::class,
            Step\TypeStep::class,
            Step\DatabaseStep::class,
            Step\DatabaseCheckStep::class,
            Step\VersionSelectionStep::class,
            Step\DatabaseInstallStep::class,
            Step\AdminStep::class,
            Step\TelemetryStep::class,
            Step\InitializationStep::class,
            Step\EndStep::class,
        ];

        foreach ($stepClasses as $stepClass) {
            if (class_exists($stepClass)) {
                $step = new $stepClass($this->install);
                if ($step->isApplicable($mode)) {
                    $this->addStep($step);
                }
            }
        }

        // Sort by order
        usort($this->steps, function (StepInterface $a, StepInterface $b) {
            return $a->getOrder() <=> $b->getOrder();
        });

        return $this;
    }

    /**
     * Get current step
     */
    public function getCurrentStep(): ?StepInterface
    {
        if (!isset($this->steps[$this->currentStepIndex])) {
            return null;
        }
        return $this->steps[$this->currentStepIndex];
    }

    /**
     * Get current step index
     */
    public function getCurrentStepIndex(): int
    {
        return $this->currentStepIndex;
    }

    /**
     * Get total number of steps
     */
    public function getTotalSteps(): int
    {
        return count($this->steps);
    }

    /**
     * Execute current step
     *
     * @param array<string, mixed> $data Input data for the step
     */
    public function executeCurrentStep(array $data = []): StepResult
    {
        $step = $this->getCurrentStep();
        if ($step === null) {
            return StepResult::error([_T("No step to execute")]);
        }

        Analog::log(
            'Executing installation step: ' . $step->getStepName(),
            Analog::INFO
        );

        // Merge with workflow context
        $data = array_merge($this->context, $data);

        // Validate before executing
        $validationResult = $step->validate($data);
        if (!$validationResult->isSuccess()) {
            Analog::log(
                'Step validation failed: ' . $step->getStepName(),
                Analog::WARNING
            );
            return $validationResult;
        }

        // Execute step
        $result = $step->execute($data);

        // Store result data in context for next steps
        if ($result->getData() !== null) {
            $this->context = array_merge($this->context, (array)$result->getData());
        }

        Analog::log(
            'Step executed: ' . $step->getStepName() . ' - Status: ' . $result->getStatus()->value,
            $result->isSuccess() ? Analog::INFO : Analog::ERROR
        );

        return $result;
    }

    /**
     * Advance to next step
     */
    public function advance(): bool
    {
        if ($this->hasNext()) {
            $this->currentStepIndex++;
            Analog::log(
                'Advanced to step ' . ($this->currentStepIndex + 1) . '/' . $this->getTotalSteps(),
                Analog::DEBUG
            );
            return true;
        }
        return false;
    }

    /**
     * Go back to previous step
     */
    public function goBack(): bool
    {
        if ($this->hasPrevious()) {
            $this->currentStepIndex--;
            Analog::log(
                'Went back to step ' . ($this->currentStepIndex + 1) . '/' . $this->getTotalSteps(),
                Analog::DEBUG
            );
            return true;
        }
        return false;
    }

    /**
     * Check if there is a next step
     */
    public function hasNext(): bool
    {
        return $this->currentStepIndex < count($this->steps) - 1;
    }

    /**
     * Check if there is a previous step
     */
    public function hasPrevious(): bool
    {
        return $this->currentStepIndex > 0;
    }

    /**
     * Check if workflow is complete
     */
    public function isComplete(): bool
    {
        return !$this->hasNext() && $this->currentStepIndex > 0;
    }

    /**
     * Get workflow context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set workflow context
     *
     * @param array<string, mixed> $context Context data
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Jump to a specific step by name
     *
     * @param string $stepName Step name to jump to
     */
    public function jumpToStep(string $stepName): bool
    {
        foreach ($this->steps as $index => $step) {
            if ($step->getStepName() === $stepName) {
                $this->currentStepIndex = $index;
                Analog::log(
                    'Jumped to step: ' . $stepName,
                    Analog::DEBUG
                );
                return true;
            }
        }

        Analog::log(
            'Failed to jump to step: ' . $stepName . ' (not found)',
            Analog::WARNING
        );
        return false;
    }

    /**
     * Get all steps
     *
     * @return array<int, StepInterface>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Set current step index
     *
     * @param int $index Step index
     */
    public function setCurrentStepIndex(int $index): self
    {
        if ($index >= 0 && $index < count($this->steps)) {
            $this->currentStepIndex = $index;
        }
        return $this;
    }
}
