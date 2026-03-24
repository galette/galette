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
 * Database connection and permissions check step
 *
 * Verifies database connectivity and required permissions.
 * This step can auto-advance on success.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DatabaseCheckStep extends AbstractStep
{
    public const STEP_NAME = 'database_check';
    public const STEP_ORDER = 40;

    /**
     * Test database connectivity and verify required permissions (CREATE, INSERT, SELECT, UPDATE, DELETE, DROP, and ALTER for upgrades).
     * Auto-advances on success; displays error page if connection or permissions fail.
     *
     * @param array<string, mixed> $data Execution context data
     */
    public function execute(array $data = []): StepResult
    {
        // Test database connection first
        try {
            $this->install->testDbConnexion();
        } catch (\Throwable $e) {
            return StepResult::error(
                [
                    _T("Unable to connect to the database"),
                    $e->getMessage(),
                    _T("Database can't be reached. Please go back to enter the connection parameters again."),
                ],
                ['connection_error' => $e->getMessage()]
            );
        }

        // Connection successful, now check database engine and permissions
        $zdb = new \Galette\Core\Db();

        // Check if database engine is supported
        if (!$zdb->isEngineSUpported()) {
            return StepResult::error(
                [
                    _T("Incompatible database version."),
                    $zdb->getUnsupportedMessage()
                ],
                ['unsupported_engine' => true]
            );
        }

        // Check permissions
        $zdb->dropTestTable(); // Clean up if exists
        $perms_results = $zdb->grantCheck($this->install->getMode());

        $required_perms = ['create', 'insert', 'select', 'update', 'delete', 'drop'];
        if ($this->install->isUpgrade()) {
            $required_perms[] = 'alter';
        }

        $all_perms_ok = true;
        $perm_checks = [];

        foreach ($required_perms as $perm) {
            if (isset($perms_results[$perm])) {
                if ($perms_results[$perm] instanceof \Exception) {
                    $all_perms_ok = false;
                    $perm_checks[] = [
                        'message' => sprintf(_T("%s operation not allowed"), strtoupper($perm)),
                        'res' => false,
                        'debug' => $perms_results[$perm]->getMessage()
                    ];
                } elseif ($perms_results[$perm] === true) {
                    $perm_checks[] = [
                        'message' => sprintf(_T("%s operation allowed"), strtoupper($perm)),
                        'res' => true
                    ];
                }
            }
        }

        if ($all_perms_ok) {
            // Success - auto-advance without displaying page
            return StepResult::success(
                [
                    _T("Connection to database successful"),
                    _T("Permissions to database are OK.")
                ],
                false, // requiresDisplay = false -> auto-advance!
                $perm_checks,
                ['db_ready' => true]
            );
        }

        // Permissions issues - must display error page
        $error_msg = $this->install->isInstall()
            ? _T("GALETTE hasn't got enough permissions on the database to continue the installation.")
            : _T("GALETTE hasn't got enough permissions on the database to continue the update.");

        return StepResult::error(
            [$error_msg],
            $perm_checks,
            ['permission_failures' => array_filter($required_perms, function ($perm) use ($perms_results) {
                return $perms_results[$perm] instanceof \Exception;
            })]
        );
    }

    /**
     * This step can skip display on success
     * Only shows a page if there are errors
     */
    public function canSkipDisplay(): bool
    {
        return true;
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
        return _T("Database access and permissions");
    }

    /**
     * Get step execution order
     */
    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }
}
