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
 * REFACTORED VERSION - System checks step
 *
 * This is an example of how check.php could be refactored to use
 * the new component functions.
 *
 * To use this:
 * 1. Backup original galette/install/steps/check.php
 * 2. Copy this file to galette/install/steps/check_refactored.php
 * 3. Test thoroughly
 * 4. When validated, replace check.php with this version
 *
 * @var \Galette\Core\Install $install
 * @var \Galette\Core\I18n $i18n
 */

// Load view helpers
require_once __DIR__ . '/../views/components.php';
require_once __DIR__ . '/../views/helpers.php';

// Perform checks
$php_ok = version_compare(PHP_VERSION, GALETTE_PHP_MIN, '>=');

$date_ok = false;
try {
    new \Safe\DateTime();
    $date_ok = true;
} catch (\Exception) {
    // Date settings not configured properly
}

$cm = new Galette\Core\CheckModules();
$modules_ok = $cm->isValid();

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

$perms_ok = true;
$permissions_details = [];
foreach ($files_need_rw as $label => $file) {
    $writable = is_writable($file);
    $permissions_details[] = [
        'message' => $label,
        'res' => $writable
    ];
    if (!$writable) {
        $perms_ok = false;
    }
}

$all_ok = $php_ok && $date_ok && $modules_ok && $perms_ok;
?>

<h2><?php echo _T("Welcome to the Galette Install!"); ?></h2>

<?php
// Overall status message
if ($all_ok) {
    renderMessageBox('success', _T("Galette requirements are met :)"));
} else {
    renderMessageBox('error', _T("Something went wrong. Please review all verifications below."));
}
?>

<h3><?php echo _T("System Requirements"); ?></h3>

<?php
// System checks with validation list
$system_checks = [
    [
        'message' => _T("PHP version") . ' (' . PHP_VERSION . ' >= ' . GALETTE_PHP_MIN . ')',
        'res' => $php_ok
    ],
    [
        'message' => _T("Date settings"),
        'res' => $date_ok
    ]
];

renderValidationList($system_checks, $install);
?>

<h3><?php echo _T("PHP Modules"); ?></h3>

<?php
if (!$modules_ok) {
    renderMessageBox(
        'error',
        _T("Some PHP modules are missing. Please install them or contact your support.") . '<br/>'
        . _T("More information on required modules may be found in the documentation."),
        false
    );
}

// Render modules check list
$modules_list = [];

// Add missing modules (errors)
foreach ($cm->getMissings() as $module) {
    $modules_list[] = [
        'message' => $module,
        'res' => false
    ];
}

// Add good modules (success)
foreach ($cm->getGoods() as $module) {
    $modules_list[] = [
        'message' => $module,
        'res' => true
    ];
}

// Add optional/recommended modules (warnings - shown but don't fail)
foreach ($cm->getShoulds() as $module) {
    $modules_list[] = [
        'message' => $module . ' ' . _T("(recommended)"),
        'res' => true // Show as OK but with warning text
    ];
}

renderValidationList($modules_list, $install);
?>

<h3><?php echo _T("Files permissions"); ?></h3>

<?php
renderValidationList($permissions_details, $install);

if (!$perms_ok) {
    ?>
    <article id="files_perms" class="ui orange message">
        <p class="ui small header"><?php echo _T("Files permissions are not OK!"); ?></p>
        <p>
            <?php
            if ($install->isInstall()) {
                echo _T("To work as expected, Galette needs write permission on files listed above.");
            } elseif ($install->isUpgrade()) {
                echo _T("In order to be updated, Galette needs write permission on files listed above.");
            }
            ?>
        </p>
        <p>
            <?php echo _T("Under UNIX/Linux, you can give the permissions using those commands"); ?><br />
            <code>chown <em><?php echo _T("apache_user"); ?></em> <em><?php echo _T("file_name"); ?></em><br />
            chmod 700 <em><?php echo _T("directory_name"); ?></em></code>
        </p>
        <p><?php echo _T("Under Windows, check these directories are not in Read-Only mode in their property panel."); ?></p>
    </article>
    <?php
}

// Navigation buttons
renderFormNavigation(
    canAdvance: $all_ok,
    canGoBack: false,
    showRetry: !$all_ok,
    i18n: $i18n,
    hiddenInputs: $all_ok ? ['install_permsok' => '1'] : []
);
?>

