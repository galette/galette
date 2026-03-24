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

/**
 * Installation orchestrator - Bridges old and new systems
 *
 * This file provides functions to execute the new Step classes
 * and handle their StepResult, including auto-advancement.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

use Galette\Core\Installation\AbstractStep;
use Galette\Core\Installation\StepResult;

/**
 * Execute a step and handle its result
 *
 * This function:
 * - Instantiates the step class
 * - Executes it with provided data
 * - Handles auto-advancement if requiresDisplay is false
 * - Returns the StepResult for rendering
 *
 * @param string                $stepClassName Fully qualified step class name
 * @param array<string, mixed>  $data          Data to pass to the step
 * @param \Galette\Core\Install $install       Install instance for state management
 * @return StepResult|null Returns StepResult if display needed, null if auto-advanced
 */
function executeStep(string $stepClassName, array $data, \Galette\Core\Install $install): ?StepResult
{
    if (!class_exists($stepClassName)) {
        throw new \RuntimeException("Step class not found: $stepClassName");
    }

    /** @var AbstractStep $step */
    $step = new $stepClassName($install);
    $result = $step->execute($data);

    // Auto-advancement logic
    if (!$result->requiresDisplay()) {
        // Step passed and doesn't need display - auto advance
        return null; // Signal to caller to redirect
    }

    return $result;
}

/**
 * Generate auto-advance HTML with notification
 *
 * Generates a page that shows a brief notification and then
 * automatically submits a form to advance to the next step.
 *
 * @param StepResult           $result         The step result
 * @param string               $nextStepAction The POST parameter to trigger next step
 * @param array<string, mixed> $hiddenData     Additional data to pass to next step
 * @return void Outputs HTML directly
 */
function renderAutoAdvance(StepResult $result, string $nextStepAction, array $hiddenData = []): void
{
    $messages = $result->getMessages();
    $message = !empty($messages) ? $messages[0] : _T("Step completed successfully");
    ?>
    <div class="ui icon positive message">
        <i class="notched circle loading icon"></i>
        <div class="content">
            <div class="header"><?php echo $message; ?></div>
            <p><?php echo _T("Proceeding to next step..."); ?></p>
        </div>
    </div>

    <form id="auto-advance-form" method="post" action="installer.php" style="display: none;">
        <input type="hidden" name="<?php echo htmlspecialchars($nextStepAction); ?>" value="1" />
        <?php foreach ($hiddenData as $key => $value): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars((string)$value); ?>" />
        <?php endforeach; ?>
    </form>

    <script>
    (function() {
        // Auto-submit after 1 second
        setTimeout(function() {
            document.getElementById('auto-advance-form').submit();
        }, 1000);
    })();
    </script>

    <noscript>
        <div class="ui warning message">
            <p><?php echo _T("JavaScript is disabled. Please click the button below to continue."); ?></p>
        </div>
        <form method="post" action="installer.php">
            <input type="hidden" name="<?php echo htmlspecialchars($nextStepAction); ?>" value="1" />
            <?php foreach ($hiddenData as $key => $value): ?>
                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars((string)$value); ?>" />
            <?php endforeach; ?>
            <button type="submit" class="ui primary button">
                <?php echo _T("Continue"); ?>
            </button>
        </form>
    </noscript>
    <?php
}

/**
 * Check if we should use new Step system for current step
 *
 * Returns true if the step has been refactored to use new system.
 * All steps are now refactored.
 *
 * @param \Galette\Core\Install $install Install instance
 * @return bool True if should use new system
 */
function shouldUseNewSystem(\Galette\Core\Install $install): bool
{
    // All steps have been refactored to use the new system
    return $install->isCheckStep()
        || $install->isTypeStep()
        || $install->isDbStep()
        || $install->isDbCheckStep()
        || $install->isVersionSelectionStep()
        || $install->isDbinstallStep()
        || $install->isDbUpgradeStep()
        || $install->isAdminStep()
        || $install->isTelemetryStep()
        || $install->isGaletteInitStep()
        || $install->isEndStep();
}

/**
 * Get Step class name for current install step
 *
 * Maps old system step constants to new Step classes
 *
 * @param \Galette\Core\Install $install Install instance
 * @return string|null Fully qualified class name or null if not a valid step
 */
function getStepClassName(\Galette\Core\Install $install): ?string
{
    if ($install->isCheckStep()) {
        return \Galette\Core\Installation\Step\CheckStep::class;
    } elseif ($install->isTypeStep()) {
        return \Galette\Core\Installation\Step\TypeStep::class;
    } elseif ($install->isDbStep()) {
        return \Galette\Core\Installation\Step\DatabaseStep::class;
    } elseif ($install->isDbCheckStep()) {
        return \Galette\Core\Installation\Step\DatabaseCheckStep::class;
    } elseif ($install->isVersionSelectionStep()) {
        return \Galette\Core\Installation\Step\VersionSelectionStep::class;
    } elseif ($install->isDbinstallStep() || $install->isDbUpgradeStep()) {
        return \Galette\Core\Installation\Step\DatabaseInstallStep::class;
    } elseif ($install->isAdminStep()) {
        return \Galette\Core\Installation\Step\AdminStep::class;
    } elseif ($install->isTelemetryStep()) {
        return \Galette\Core\Installation\Step\TelemetryStep::class;
    } elseif ($install->isGaletteInitStep()) {
        return \Galette\Core\Installation\Step\InitializationStep::class;
    } elseif ($install->isEndStep()) {
        return \Galette\Core\Installation\Step\EndStep::class;
    }

    return null;
}

/**
 * Get POST parameter name that triggers next step
 *
 * @param \Galette\Core\Install $install Install instance
 * @return string POST parameter name
 */
function getNextStepAction(\Galette\Core\Install $install): string
{
    if ($install->isCheckStep()) {
        return 'install_permsok';
    } elseif ($install->isTypeStep()) {
        return 'install_type';
    } elseif ($install->isDbStep()) {
        return 'install_dbtype';
    } elseif ($install->isDbCheckStep()) {
        return 'install_dbperms_ok';
    } elseif ($install->isVersionSelectionStep()) {
        return 'previous_version';
    } elseif ($install->isDbinstallStep() || $install->isDbUpgradeStep()) {
        return 'install_dbwrite_ok';
    } elseif ($install->isAdminStep()) {
        return 'install_adminlogin';
    } elseif ($install->isTelemetryStep()) {
        return 'install_telemetry_ok';
    } elseif ($install->isGaletteInitStep()) {
        return 'install_prefs_ok';
    }

    return 'next_step';
}

/**
 * Get additional data to pass when auto-advancing
 *
 * @param \Galette\Core\Install $install Install instance
 * @param StepResult            $result  Step result
 * @return array<string, mixed> Hidden form data
 */
function getAutoAdvanceData(\Galette\Core\Install $install, StepResult $result): array
{
    $data = [];

    // Add any data from the step result
    $stepData = $result->getData();
    if (!empty($stepData)) {
        // Filter to only include simple scalar values that can be passed via POST
        foreach ($stepData as $key => $value) {
            if (is_scalar($value)) {
                $data[$key] = $value;
            }
        }
    }

    return $data;
}
