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
        // Get database instance
        if (!isset($data['zdb']) || !($data['zdb'] instanceof \Galette\Core\Db)) {
            // Try to create it from Install constants
            try {
                $zdb = new \Galette\Core\Db();
            } catch (\Throwable $e) {
                return StepResult::error(
                    [_T("Unable to initialize database connection")],
                    ['error' => $e->getMessage()]
                );
            }
        } else {
            $zdb = $data['zdb'];
        }

        // Execute installation/upgrade scripts
        // scripts_path allows plugins to pass their own scripts directory
        $scriptsPath = $data['scripts_path'] ?? null;
        $success = $this->install->executeScripts($zdb, $scriptsPath);
        $report = $this->install->getDbInstallReport();

        if ($success) {
            $msg = $this->install->isInstall()
                ? _T("Database has been installed :)")
                : _T("Database has been upgraded :)");

            // Success - show report in modal, then auto-advance
            return StepResult::success(
                [$msg],
                false, // Don't display full page
                $report,
                [
                    'db_installed' => true,
                    'show_report_modal' => true // Flag to show modal in view
                ]
            );
        }

        // Failure - show full page with report
        $msg = $this->install->isInstall()
            ? _T("Database has not been installed!")
            : _T("Database has not been upgraded!");

        return StepResult::error(
            [$msg],
            $report,
            ['db_installed' => false]
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
