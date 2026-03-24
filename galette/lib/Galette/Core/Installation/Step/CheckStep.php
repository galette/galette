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
     * Accepted data keys:
     * - skip_permissions (bool): skip file permission checks (e.g. for CLI)
     *
     * @param array<string, mixed> $data
     */
    public function execute(array $data = []): StepResult
    {
        $allPassed = true;
        $messages = [];
        $report = [];

        // PHP version
        $phpOk = version_compare(PHP_VERSION, GALETTE_PHP_MIN, '>=');
        $report[] = [
            'message' => _T("PHP version") . ' (' . PHP_VERSION . ' >= ' . GALETTE_PHP_MIN . ')',
            'res' => $phpOk,
        ];
        if (!$phpOk) {
            $allPassed = false;
            $messages[] = _T("PHP version is too old. Please upgrade your PHP installation.");
        }

        // Date settings
        $dateOk = false;
        try {
            new \Safe\DateTime();
            $dateOk = true;
        } catch (\Exception) {
            // Date settings not configured properly
        }
        $dateEntry = ['message' => _T("Date settings"), 'res' => $dateOk];
        if (!$dateOk) {
            $dateEntry['debug'] = _T("Your PHP date settings are not correct. Maybe you've missed the timezone settings that is mandatory since PHP 5.3?");
            $allPassed = false;
            $messages[] = $dateEntry['debug'];
        }
        $report[] = $dateEntry;

        // PHP Modules
        $cm = new CheckModules();
        $modulesOk = $cm->isValid();
        foreach ($cm->getMissings() as $module) {
            $report[] = ['message' => $module, 'res' => false];
        }
        foreach ($cm->getGoods() as $module) {
            $report[] = ['message' => $module, 'res' => true];
        }
        foreach ($cm->getShoulds() as $module) {
            $report[] = ['message' => $module . ' ' . _T("(recommended)"), 'res' => true];
        }
        if (!$modulesOk) {
            $allPassed = false;
            $messages[] = _T("Some PHP modules are missing. Please install them or contact your support.");
        }

        // File permissions (skippable for CLI contexts)
        if (!($data['skip_permissions'] ?? false)) {
            $files_need_rw = [
                _T("Photos")           => GALETTE_PHOTOS_PATH,
                _T("Cache")            => str_replace(GALETTE_VERSION, '', GALETTE_CACHE_DIR),
                _T("Temporary images") => GALETTE_TEMPIMAGES_PATH,
                _T("Configuration")    => GALETTE_CONFIG_PATH,
                _T("Exports")          => GALETTE_EXPORTS_PATH,
                _T("Imports")          => GALETTE_IMPORTS_PATH,
                _T("Logs")             => GALETTE_LOGS_PATH,
                _T("Attachments")      => GALETTE_ATTACHMENTS_PATH,
                _T("Files")            => GALETTE_FILES_PATH,
            ];
            $permsOk = true;
            foreach ($files_need_rw as $label => $path) {
                $writable = is_writable($path);
                $report[] = ['message' => $label, 'res' => $writable];
                if (!$writable) {
                    $permsOk = false;
                }
            }
            if (!$permsOk) {
                $allPassed = false;
                $install_mode = $this->install->isInstall() ? _T("work as expected") : _T("be updated");
                $messages[] = sprintf(_T("Galette needs write permission on some directories to %s."), $install_mode);
            }
        }

        if ($allPassed) {
            return StepResult::success(
                [_T("Galette requirements are met :)")],
                false, // auto-advance on success
                $report,
                ['checks_passed' => true]
            );
        }

        return StepResult::error($messages, $report, ['checks_passed' => false]);
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
