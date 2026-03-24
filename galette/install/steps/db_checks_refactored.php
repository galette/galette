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

use Galette\Core\Db as GaletteDb;

/**
 * REFACTORED VERSION - Database checks step
 *
 * This version uses the new component functions for cleaner code.
 *
 * @var \Galette\Core\Install $install
 * @var \Galette\Core\I18n $i18n
 * @var \Galette\Core\Db $zdb (may not be set yet)
 */

// Load view helpers
require_once __DIR__ . '/../views/components.php';
require_once __DIR__ . '/../views/helpers.php';

// Test database connection
try {
    $db_connected = $install->testDbConnexion();
} catch (Throwable $e) {
    $db_connected = $e;
}

$conndb_ok = true;
$permsdb_ok = true;
$supported_db = true;
$result = [];

if ($db_connected === true) {
    if (!isset($zdb)) {
        $zdb = new GaletteDb();
    }

    if (!$zdb->isEngineSUpported()) {
        $supported_db = false;
    }

    if ($supported_db) {
        // Check database permissions
        $zdb->dropTestTable();
        $results = $zdb->grantCheck($install->getMode());

        $error = false;
        $required_operations = ['create', 'insert', 'update', 'select', 'delete', 'drop'];
        
        if ($install->isUpgrade()) {
            $required_operations[] = 'alter';
        }

        foreach ($required_operations as $operation) {
            if (isset($results[$operation])) {
                if ($results[$operation] instanceof Exception) {
                    $result[] = [
                        'message' => sprintf(_T("%s operation not allowed"), strtoupper($operation)),
                        'debug' => $results[$operation]->getMessage(),
                        'res' => false
                    ];
                    $error = true;
                } elseif ($results[$operation] === true) {
                    $result[] = [
                        'message' => sprintf(_T("%s operation allowed"), strtoupper($operation)),
                        'res' => true
                    ];
                }
            }
        }

        if ($error) {
            $permsdb_ok = false;
        }
    }
}

// === AFFICHAGE ===

if (!isset($install_plugin)) {
    ?>
    <h2><?php echo _T("Check of the database"); ?></h2>
    <p><?php echo _T("Database exists and connection parameters are OK."); ?></p>
    <?php
}

// Display connection status
if ($supported_db === false) {
    renderMessageBox('error', [
        _T("Incompatible database version."),
        $zdb->getUnsupportedMessage()
    ]);
} elseif ($db_connected === true && $permsdb_ok === true) {
    // Success case - will auto-advance with new system
    $success_messages = !isset($install_plugin)
        ? [_T("Connection to database successful"), _T("Permissions to database are OK.")]
        : [_T("Permissions to database are OK.")];
    
    renderMessageBox('success', $success_messages);
}

if ($db_connected !== true) {
    $conndb_ok = false;
    renderMessageBox('error', [
        _T("Unable to connect to the database"),
        $db_connected->getMessage()
    ]);
}

// Display connection error details
if (!$conndb_ok) {
    ?>
    <p><?php echo _T("Database can't be reached. Please go back to enter the connection parameters again."); ?></p>
    <?php
}

// Display permissions checks
if ($conndb_ok && $supported_db === true) {
    if (!isset($install_plugin)) {
        ?>
        <h2><?php echo _T("Permissions on the base"); ?></h2>
        <?php
    }
    
    if (!$permsdb_ok) {
        $error_msg = $install->isInstall()
            ? _T("GALETTE hasn't got enough permissions on the database to continue the installation.")
            : _T("GALETTE hasn't got enough permissions on the database to continue the update.");
        
        renderMessageBox('error', $error_msg);
    }
    
    renderValidationList($result, $install);
}

// Auto-advance notification if all checks pass
if ($conndb_ok && $permsdb_ok && $supported_db) {
    ?>
    <div class="ui info message">
        <i class="check circle icon"></i>
        <?php echo _T("All database checks passed. Proceeding to next step..."); ?>
    </div>
    <script>
        // Auto-redirect after 1 second
        setTimeout(function() {
            document.forms[0].submit();
        }, 1000);
    </script>
    <?php
}

// Navigation buttons
if (!isset($install_plugin)) {
    renderFormNavigation(
        canAdvance: $conndb_ok && $permsdb_ok && $supported_db,
        canGoBack: true,
        showRetry: false,
        i18n: $i18n,
        hiddenInputs: ($conndb_ok && $permsdb_ok && $supported_db) ? ['install_dbperms_ok' => '1'] : []
    );
}

