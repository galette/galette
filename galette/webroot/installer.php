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

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
use Analog\Analog;
use Analog\Handler;
use Analog\Handler\LevelName;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Util\Telemetry;

//set a flag saying we work from installer
//that way, in galette.inc.php, we'll only include relevant parts
$installer = true; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used on file inclusion
define('GALETTE_ROOT', __DIR__ . '/../'); //@phpstan-ignore theCodingMachineSafe.function
define('GALETTE_INSTALLER', true); //@phpstan-ignore theCodingMachineSafe.function

// check PHP modules
require_once GALETTE_ROOT . '/vendor/autoload.php';
require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';

if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<') || !extension_loaded('intl')) { //@phpstan-ignore booleanOr.leftAlwaysFalse
    header('location: compat_test.php');
    die(1);
}

//specific logfile for installer
$logfile = 'galette_install';
define('GALETTE_BASE_PATH', '../'); //@phpstan-ignore theCodingMachineSafe.function

require_once __DIR__ . '/../includes/galette.inc.php';
require_once __DIR__ . '/../install/orchestrator.php';
require_once __DIR__ . '/../install/views/components.php';
require_once __DIR__ . '/../install/views/helpers.php';
/** @var Plugins $plugins */

session_start(); //@phpstan-ignore theCodingMachineSafe.function
$session_name = 'galette_install_' . str_replace('.', '_', GALETTE_VERSION);
$session = &$_SESSION['galette'][$session_name];

$gapp = new \Galette\Core\SlimApp($plugins);
$app = $gapp->getApp(); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used on file inclusion
/** @var \DI\Container $container */
$container = $app->getContainer(); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- available to included files

if (isset($_POST['abort_btn'])) {
    if (isset($session[md5(GALETTE_ROOT)])) {
        unset($session[md5(GALETTE_ROOT)]);
    }
    header('location: ' . GALETTE_BASE_PATH);
}

$install = null;
if (isset($session[md5(GALETTE_ROOT)]) && !isset($_GET['raz'])) {
    $install = unserialize($session[md5(GALETTE_ROOT)]);
} else {
    $install = new GaletteInstall();
}

$error_detected = [];
$stepResult = null; // Will hold StepResult for new system steps

if ($install->isStepPassed(GaletteInstall::STEP_TYPE)) {
    define('GALETTE_LOGGER_CHECKED', true); //@phpstan-ignore theCodingMachineSafe.function

    $log_path = GALETTE_LOGS_PATH . $logfile . '.log';
    $galette_run_log = LevelName::init(Handler\File::init($log_path));
    Analog::handler($galette_run_log);
}

if (
    !$install->isEndStep()
    && ($install->postCheckDb())
) {
    //if we have passed database configuration, define required constants
    $install->initDbConstants();

    try {
        $zdb = new GaletteDb();
    } catch (Throwable $e) {
        if (!$install->isDbCheckStep()) {
            throw $e;
        }
    }
}

if (isset($_POST['stepback_btn'])) {
    $install->atPreviousStep();
} elseif (isset($_POST['install_permsok']) && $_POST['install_permsok'] == 1) {
    $install->atTypeStep();
} elseif (isset($_POST['install_type'])) {
    $install->setMode($_POST['install_type']);
    $install->atDbStep();
} elseif (isset($_POST['install_dbtype'])) {
    $install->setDbType($_POST['install_dbtype'], $error_detected);

    if (empty($_POST['install_dbhost'])) {
        $error_detected[] = _T("No host");
    }
    if (empty($_POST['install_dbport'])) {
        $error_detected[] = _T("No port");
    }
    if (empty($_POST['install_dbuser'])) {
        $error_detected[] = _T("No user name");
    }
    if (empty($_POST['install_dbpass'])) {
        $error_detected[] = _T("No password");
    }
    if (empty($_POST['install_dbname'])) {
        $error_detected[] = _T("No database name");
    }

    if (count($error_detected) == 0) {
        $install->setDsn(
            $_POST['install_dbhost'],
            $_POST['install_dbport'],
            $_POST['install_dbname'],
            $_POST['install_dbuser'],
            $_POST['install_dbpass']
        );
        $install->setTablesPrefix(
            $_POST['install_dbprefix']
        );
        $install->atDbCheckStep();
        $install->initDbConstants();
    }
} elseif (isset($_POST['install_dbperms_ok'])) {
    if ($install->isInstall()) {
        $install->atDbInstallStep();
    } elseif ($install->isUpgrade()) {
        $install->atVersionSelection();
    }
} elseif (isset($_POST['previous_version'])) {
    $install->setInstalledVersion($_POST['previous_version']);
    $install->atDbUpgradeStep();
} elseif (isset($_POST['install_dbwrite_ok']) && $install->isInstall()) {
    $install->atAdminStep();
} elseif (isset($_POST['install_dbwrite_ok']) && $install->isUpgrade()) {
    $install->atTelemetryStep();
} elseif (
    isset($_POST['install_adminlogin'])
    && isset($_POST['install_adminpass'])
    && $install->isInstall()
) {
    if ($_POST['install_adminlogin'] == '') {
        $error_detected[] = _T("No user name");
    }
    if (strpos((string)$_POST['install_adminlogin'], '@')) {
        $error_detected[] = _T("The username cannot contain the @ character");
    }
    if ($_POST['install_adminpass'] == '') {
        $error_detected[] = _T("No password");
    }
    if (
        !isset($_POST['install_passwdverified'])
        && strcmp(
            $_POST['install_adminpass'],
            (string)$_POST['install_adminpass_verif']
        )
    ) {
        $error_detected[] = _T("Passwords mismatch");
    }
    if (count($error_detected) == 0) {
        $install->setAdminInfos(
            $_POST['install_adminlogin'],
            $_POST['install_adminpass']
        );
        $install->atTelemetryStep();
    }
} elseif (isset($_POST['install_telemetry_ok'])) {
    if (isset($_POST['send_telemetry'])) {
        $preferences = new Preferences($zdb); // @phpstan-ignore variable.undefined ($zdb is defined since postCheckDb step, and we're at telemetry.)
        $plugins = new Plugins();
        $telemetry = new Telemetry(
            $zdb, // @phpstan-ignore variable.undefined ($zdb is defined since postCheckDb step, and we're at telemetry.)
            $preferences,
            $plugins
        );
        try {
            $telemetry->send();
        } catch (Throwable $e) {
            Analog::log($e->getMessage(), Analog::ERROR);
        }
    }
    // Skip GaletteInitStep - initialization is now done in EndStep
    $install->atEndStep();
}

// Execute new system steps if applicable
if (shouldUseNewSystem($install)) {
    $stepClassName = getStepClassName($install);
    if ($stepClassName !== null) {
        try {
            // Gather data for step execution
            $stepData = [];

            // For DatabaseCheckStep and DatabaseInstallStep, we need db connection
            if (($install->isDbCheckStep() || $install->isDbinstallStep() || $install->isDbUpgradeStep()) && isset($zdb)) {
                $stepData['zdb'] = $zdb;
            }

            // Execute the step
            $result = executeStep($stepClassName, $stepData, $install);

            // Check if auto-advance is needed
            if ($result === null || !$result->requiresDisplay()) {
                // Step doesn't need display - prepare auto-advance
                if ($result !== null) {
                    $stepResult = $result; // Store for auto-advance rendering
                } else {
                    // Create a default success result
                    $stepResult = \Galette\Core\Installation\StepResult::success(
                        [_T("Step completed successfully")],
                        false
                    );
                }
            } else {
                // Step needs display - store result for view
                $stepResult = $result;
            }
        } catch (\Exception $e) {
            // Handle step execution errors
            \Analog\Analog::log(
                'Error executing step: ' . $e->getMessage(),
                \Analog\Analog::ERROR
            );
            $error_detected[] = _T("An error occurred during installation: ") . $e->getMessage();
        }
    }
}

/** @var \Galette\Core\I18n $i18n */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $i18n->getAbbrev(); ?>"<?php echo $i18n->isRtl() ? ' dir="rtl"' : ''; ?>>
    <head>
        <title><?php echo _T("Galette Installation") . ' - ' . $install->getStepDetail('title'); ?></title>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width" />
        <link rel="stylesheet" type="text/css" href="./themes/default/ui/semantic<?php echo $i18n->isRtl() ? '.rtl' : ''; ?>.min.css" />
        <link rel="shortcut icon" href="./themes/default/images/favicon.png" />
        <script type="text/javascript" src="./assets/js/jquery.min.js"></script>
    </head>
    <body class="pushable">
        <a href="#main-content" class="skiptocontent visually-hidden focusable"><?php echo _T("Skip to content"); ?></a>
        <header id="top-navbar" class="ui fixed menu bgcolor">
            <div class="ui wide container">
                <div class="header item">
                    <span><?php echo _T("Galette installation") ?></span>
                </div>
                <div class="ui right item">
                    <a class="circular ui basic mini icon button" href="<?php echo $i18n->getDocumentationBaseUrl(); ?><?php echo $install->getStepDetail('documentation'); ?>" target="_blank" data-position="left center" title="<?php echo _T("Read the manual"); ?>">
                        <i class="question icon"></i>
                        <span class="visually-hidden"><?php echo _T("Read the manual"); ?></span>
                    </a>
                </div>
                <div class="language ui dropdown navigation item">
                    <i class="icon language" aria-hidden="true"></i>
                    <span><?php echo $i18n->getAbbrev(); ?></span>
                    <i class="icon dropdown" aria-hidden="true"></i>
                    <div class="menu">
<?php
foreach ($i18n->getList() as $langue) {
    ?>
                        <a href="?ui_pref_lang=<?php echo $langue->getID(); ?>" lang="<?php echo $langue->getAbbrev(); ?>" class="item"><?php echo $langue->getName(); ?> <span>(<?php echo $langue->getAbbrev(); ?>)</span></a>
    <?php
}
?>
                    </div>
                </div>
            </div>
        </header>
        <main class="pusher">
            <section id="main" class="ui wide container">
                <div class="ui basic segment">
                    <div class="ui basic center aligned fitted segment">
                        <img class="icon" width="200" alt="[ Galette ]" src="./themes/default/images/galette.webp"/>
                    </div>
                    <a id="main-content" tabindex="-1"></a>
                    <h1 class="ui block center aligned header">
                        <?php echo $install->getStepDetail('title'); ?>
                    </h1>
<?php
if (count($error_detected) > 0) {
    ?>
                    <div id="errorbox" class="ui red message">
                        <h1><?php echo _T("- ERROR -"); ?></h1>
                        <ul>
    <?php
    foreach ($error_detected as $error) {
        ?>
                            <li><?php echo $error; ?></li>
        <?php
    }
    ?>
                        </ul>
                    </div>
    <?php
}
?>
                    <div class="ui mobile reversed stackable two column grid">
                        <div class="four wide column">
<?php renderStepProgress($install, $i18n); ?>
                        </div>
                        <div class="twelve wide column">
<?php
// Check if we need to render auto-advance
if ($stepResult !== null && !$stepResult->requiresDisplay()) {
    // Check if this is DatabaseInstallStep that needs a modal
    $stepData = $stepResult->getData();
    if (isset($stepData['show_report_modal']) && $stepData['show_report_modal'] === true) {
        // Special case: show modal with report
        $report = $stepResult->getReport();
        $nextAction = getNextStepAction($install);
        ?>
        <div class="ui success message">
            <i class="database icon"></i>
            <?php
            echo $install->isInstall()
                ? _T("Database has been installed :)")
                : _T("Database has been upgraded :)");
            ?>
        </div>
        
        <!-- Modal will show detailed report -->
        <?php
        require_once __DIR__ . '/../install/views/components.php';
        renderDbReportModal($report, $install, $i18n, true);
        ?>
        
        <!-- Hidden form for advancing to next step -->
        <form id="install-continue-form" method="POST" action="installer.php" style="display: none;">
            <input type="hidden" name="<?php echo htmlspecialchars($nextAction); ?>" value="1"/>
        </form>
        
        <noscript>
            <!-- Fallback without JavaScript -->
            <form method="POST" action="installer.php">
                <input type="hidden" name="<?php echo htmlspecialchars($nextAction); ?>" value="1"/>
                <button type="submit" class="ui primary button">
                    <?php echo _T("Continue"); ?>
                </button>
            </form>
        </noscript>
        <?php
    } else {
        // Normal auto-advance: show notification and redirect
        $nextAction = getNextStepAction($install);
        $autoAdvanceData = getAutoAdvanceData($install, $stepResult);
        renderAutoAdvance($stepResult, $nextAction, $autoAdvanceData);
    }
} elseif ($install->isCheckStep()) {
    // New system step with display OR old system if not refactored
    if (shouldUseNewSystem($install) && $stepResult !== null) {
        // Pass StepResult to view
        include_once __DIR__ . '/../install/steps/check.php';
    } else {
        // Old system fallback
        include_once __DIR__ . '/../install/steps/check.php';
    }
} elseif ($install->isTypeStep()) {
    include_once __DIR__ . '/../install/steps/type.php';
} elseif ($install->isDbStep()) {
    include_once __DIR__ . '/../install/steps/db.php';
} elseif ($install->isDbCheckStep()) {
    if (shouldUseNewSystem($install) && $stepResult !== null) {
        // Check for auto-advance was already handled above
        include_once __DIR__ . '/../install/steps/db_checks.php';
    } else {
        include_once __DIR__ . '/../install/steps/db_checks.php';
    }
} elseif ($install->isVersionSelectionStep()) {
    include_once __DIR__ . '/../install/steps/db_select_version.php';
} elseif ($install->isDbinstallStep() || $install->isDbUpgradeStep()) {
    if (shouldUseNewSystem($install) && $stepResult !== null) {
        include_once __DIR__ . '/../install/steps/db_install.php';
    } else {
        include_once __DIR__ . '/../install/steps/db_install.php';
    }
} elseif ($install->isAdminStep()) {
    include_once __DIR__ . '/../install/steps/admin.php';
} elseif ($install->isTelemetryStep()) {
    include_once __DIR__ . '/../install/steps/telemetry.php';
} elseif ($install->isEndStep()) {
    include_once __DIR__ . '/../install/steps/end.php';
}
?>
                        </div>
                    </div>
                </div>
                <footer class="ui basic center aligned segment">
                    <div class="row">
                        <nav class="ui horizontal bulleted link list">
                            <a id="copyright" href="https://galette.eu/" class="item">
                                <i class="icon cookie bite"></i>
                                Galette <?php echo GALETTE_DISPLAY_VERSION; ?>
                            </a>
                            <a href="https://doc.galette.eu" class="item">
                                <i class="icon book"></i>
                               <?php echo _T("Documentation"); ?>
                            </a>
                            <a href="https://framapiaf.org/@galette" class="item">
                                <i class="icon mastodon"></i>
                                @galette
                            </a>
                        </nav>
                    </div>
                </footer>
            </section>
        </main>
        <script type="text/javascript" src="./assets/js/galette-main.bundle.min.js"></script>
        <script type="text/javascript" src="./themes/default/ui/semantic.min.js"></script>
    </body>
</html>
<?php
if (!$install->isEndStep()) {
    $session[md5(GALETTE_ROOT)] = serialize($install);
}

if (isset($profiler)) {
    $profiler->stop();
}
?>
