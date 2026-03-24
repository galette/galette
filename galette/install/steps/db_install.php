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
 * REFACTORED VERSION - Database installation/upgrade step
 *
 * This version uses the new component functions and displays
 * the report in a modal.
 *
 * @var \Galette\Core\Install $install
 * @var \Galette\Core\Db $zdb
 * @var \Galette\Core\I18n $i18n
 */

// Load view helpers
require_once __DIR__ . '/../views/components.php';
require_once __DIR__ . '/../views/helpers.php';

// Execute the installation/upgrade scripts
$db_installed = $install->executeScripts($zdb);
$report = $install->getDbInstallReport();

// Display title (only for errors, success shows modal)
if (!$db_installed) {
    $msg = $install->isInstall()
        ? _T("Database has not been installed!")
        : _T("Database has not been upgraded!");

    renderMessageBox('error', $msg);

    // Show detailed report
    echo '<h3>' . _T("Installation report") . '</h3>';
    renderValidationList($report, $install);

    // Navigation
    renderFormNavigation(
        canAdvance: false,
        canGoBack: true,
        showRetry: true,
        i18n: $i18n
    );
} else {
    // Success - show report in modal and auto-advance
    $msg = $install->isInstall()
        ? _T("Database has been installed :)")
        : _T("Database has been upgraded :)");

    // Render the modal with the report
    renderDbReportModal($report, $install, $i18n, true);

    // Hidden form to auto-submit after modal closes
    ?>
    <form action="installer.php" method="POST" id="install-continue-form">
        <input type="hidden" name="install_dbwrite_ok" value="1"/>
    </form>
    
    <noscript>
        <!-- Fallback for no-JS users -->
        <div class="ui green message">
            <h3><?php echo $msg; ?></h3>
            <?php renderValidationList($report, $install); ?>
        </div>
        <?php
        renderFormNavigation(
            canAdvance: true,
            canGoBack: false,
            showRetry: false,
            i18n: $i18n,
            hiddenInputs: ['install_dbwrite_ok' => '1']
        );
        ?>
    </noscript>
    <?php
}
