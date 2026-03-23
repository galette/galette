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

use Galette\Core\CheckModules;
use Galette\Core\Installation\AbstractStep;
use Galette\Core\Installation\StepResult;

/**
 * System requirements check step
 *
 * Verifies that the system meets all requirements:
 * - PHP version
 * - Required PHP extensions
 * - File permissions
 * - Date settings
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CheckStep extends AbstractStep
{
    public const STEP_NAME = 'check';
    public const STEP_ORDER = 10;

    /**
     * Execute system checks
     *
     * @param array<string, mixed> $data
     */
    public function execute(array $data = []): StepResult
    {
        $checks = [
            'php_version' => $this->checkPhpVersion(),
            'date_settings' => $this->checkDateSettings(),
            'modules' => $this->checkModules(),
            'permissions' => $this->checkFilePermissions(),
        ];

        $allPassed = true;
        $messages = [];
        $report = [];
        $critical_failures = [];

        foreach ($checks as $checkName => $checkResult) {
            $report[$checkName] = $checkResult;
            
            if (!$checkResult['passed']) {
                $allPassed = false;
                
                // Build detailed error message
                $error_msg = $checkResult['message'];
                if (isset($checkResult['details']) && is_string($checkResult['details'])) {
                    $error_msg .= ': ' . $checkResult['details'];
                }
                
                $messages[] = $error_msg;
                $critical_failures[] = $checkName;
            }
        }

        if ($allPassed) {
            return StepResult::success(
                [_T("Galette requirements are met :)")],
                false, // Don't display, auto-advance to next step
                $report,
                ['checks_passed' => true] // Data for next steps
            );
        }

        // Add helpful context to error messages
        if (in_array('php_version', $critical_failures)) {
            $messages[] = _T("Please upgrade your PHP installation.");
        }
        
        if (in_array('modules', $critical_failures)) {
            $messages[] = _T("Some PHP modules are missing. Please install them or contact your support.");
        }
        
        if (in_array('permissions', $critical_failures)) {
            $install_mode = $this->install->isInstall() ? 'install' : 'upgrade';
            $messages[] = sprintf(
                _T("Galette needs write permission on some directories to %s."),
                $install_mode === 'install' ? _T("work as expected") : _T("be updated")
            );
        }

        return StepResult::error(
            $messages,
            $report,
            ['failed_checks' => $critical_failures]
        );
    }

    /**
     * Check PHP version
     *
     * @return array{passed: bool, message: string, details: string}
     */
    private function checkPhpVersion(): array
    {
        $passed = version_compare(PHP_VERSION, GALETTE_PHP_MIN, '>=');
        return [
            'passed' => $passed,
            'message' => _T("PHP version") . ' (' . PHP_VERSION . ' >= ' . GALETTE_PHP_MIN . ')',
            'details' => PHP_VERSION
        ];
    }

    /**
     * Check date settings
     *
     * @return array{passed: bool, message: string, details: string}
     */
    private function checkDateSettings(): array
    {
        $passed = false;
        try {
            new \Safe\DateTime();
            $passed = true;
        } catch (\Exception) {
            // Date settings not configured properly
        }

        return [
            'passed' => $passed,
            'message' => _T("Date settings"),
            'details' => $passed
                ? _T("Date settings are correctly configured")
                : _T("Your PHP date settings are not correct. Maybe you've missed the timezone settings that is mandatory since PHP 5.3?")
        ];
    }

    /**
     * Check required PHP modules
     *
     * @return array{passed: bool, message: string, details: array{good: array<string, string>, missing: array<int, string>, should: array<int, string>}}
     */
    private function checkModules(): array
    {
        $cm = new CheckModules();
        $passed = $cm->isValid();

        return [
            'passed' => $passed,
            'message' => _T("PHP Modules"),
            'details' => [
                'good' => $cm->getGoods(),
                'missing' => $cm->getMissings(),
                'should' => $cm->getShoulds()
            ]
        ];
    }

    /**
     * Check file permissions on required directories
     *
     * @return array{passed: bool, message: string, details: array<string, bool>}
     */
    private function checkFilePermissions(): array
    {
        $files_need_rw = [
            _T("Photos")            => GALETTE_PHOTOS_PATH,
            _T("Cache")             => str_replace(GALETTE_VERSION, '', GALETTE_CACHE_DIR),
            _T("Temporary images")  => GALETTE_TEMPIMAGES_PATH,
            _T("Configuration")     => GALETTE_CONFIG_PATH,
            _T("Exports")           => GALETTE_EXPORTS_PATH,
            _T("Imports")           => GALETTE_IMPORTS_PATH,
            _T("Logs")              => GALETTE_LOGS_PATH,
            _T("Attachments")       => GALETTE_ATTACHMENTS_PATH,
            _T("Files")             => GALETTE_FILES_PATH
        ];

        $allWritable = true;
        $details = [];

        foreach ($files_need_rw as $label => $file) {
            $writable = is_writable($file);
            $details[$label] = $writable;
            if (!$writable) {
                $allWritable = false;
            }
        }

        return [
            'passed' => $allWritable,
            'message' => _T("Files permissions"),
            'details' => $details
        ];
    }

    public function getStepName(): string
    {
        return self::STEP_NAME;
    }

    public function getStepTitle(): string
    {
        return _T("Checks");
    }

    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }

    /**
     * Cannot skip display - always show checks to user
     */
    public function canSkipDisplay(): bool
    {
        return false;
    }
}
