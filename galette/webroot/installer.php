<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Galette;
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
/** @var Plugins $plugins */

session_start(); //@phpstan-ignore theCodingMachineSafe.function
$session_name = 'galette_install_' . str_replace('.', '_', GALETTE_VERSION);
$session = &$_SESSION['galette'][$session_name];

$gapp = new \Galette\Core\SlimApp($plugins);
$app = $gapp->getApp(); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used on file inclusion

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

//Installation mode is deduced from files, not asked to the user:
// - no config file       => fresh install
// - config file present  => update (credentials read from the config file)
$config_exists = file_exists(GALETTE_CONFIG_PATH . 'config.inc.php');
if ($install->getMode() === null) {
    $install->setMode($config_exists ? GaletteInstall::UPDATE : GaletteInstall::INSTALL);
}
//The installer is disabled unless the enable file is present (fail-safe). This
//applies to both install and update. The check is dropped once database checks
//have been passed, so the end/init screens keep rendering even after the enable
//file has been removed automatically on success.
$install_disabled = !$install->isInstallEnabled()
    && !$install->isStepPassed(GaletteInstall::STEP_DB_CHECKS);

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

if ($install_disabled) {
    //installer is disabled: do not process any step transition
    $install->atCheckStep();
} elseif (isset($_POST['stepback_btn'])) {
    $install->atPreviousStep();
} elseif (isset($_POST['install_permsok']) && $_POST['install_permsok'] == 1) {
    if ($install->isUpgrade()) {
        //read credentials from the existing config file, no need to ask again
        if ($install->loadExistingConfigForUpdate($error_detected)) {
            $install->atDbCheckStep();
            $install->initDbConstants();
        } else {
            //configuration file unreadable/incomplete, fall back to asking
            $install->atDbStep();
        }
    } else {
        $install->atDbStep();
    }
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
        //try to detect installed version from database to skip manual selection
        $detected = isset($zdb) ? $install->getCurrentVersion($zdb) : false;
        if ($detected !== false) {
            $install->setInstalledVersion((string)$detected);
            $install->atDbUpgradeStep();
        } else {
            $install->atVersionSelection();
        }
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
            (string)$_POST['install_adminpass'],
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
    $install->atGaletteInitStep();
} elseif (isset($_POST['install_prefs_ok'])) {
    $install->atEndStep();
}

/** @var \Galette\Core\I18n $i18n */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $i18n->getWebID(); ?>"<?php echo $i18n->isRtl() ? ' dir="rtl"' : ''; ?>>
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
                    <span class="visually-hidden"><?php echo _T("Choose your language"); ?></span>
                    <span><?php echo $i18n->getName(); ?></span>
                    <i class="icon dropdown" aria-hidden="true"></i>
                    <div class="menu">
<?php
foreach ($i18n->getList() as $langue) {
    if ($langue->getID() === $i18n->getID()) {
        ?>
                        <a href="?ui_pref_lang=<?php echo $langue->getID(); ?>"
                           class="item tooltip"
                           data-html="<?php echo sprintf(_T('Current locale \'%1$s\''), $langue->getName()); ?>"
                           data-position="left center"
                           aria-current="true"
                        >
                            <span
                                <?php if ($langue->isRtl()) {
                                    ?>dir="rtl"<?php
                                } ?>
                                lang="<?php echo $langue->getWebID(); ?>"
                            >
                                    <?php echo $langue->getName(); ?>
                            </span>
                        </a>
        <?php
    } else {
        ?>
                        <a href="?ui_pref_lang=<?php echo $langue->getID(); ?>"
                           class="item tooltip"
                           data-html="<?php echo sprintf(_T('Switch locale to \'%1$s\''), $langue->getName()); ?>"
                           data-position="left center"
                        >
                            <span
                                <?php if ($langue->isRtl()) {
                                    ?>dir="rtl"<?php
                                } ?>
                                lang="<?php echo $langue->getWebID(); ?>"
                            >
                                    <?php echo $langue->getName(); ?>
                            </span>
                        </a>
        <?php
    }
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
                        <img class="icon" width="200" alt="" src="./themes/default/images/galette.webp"/>
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
                            <div class="ui stackable mini vertical steps fluid">
                                <div
                                    class="step<?php echo $install->isCheckStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_CHECK) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isCheckStep() && !$install->isStepPassed(GaletteInstall::STEP_CHECK)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="tasks icon<?php echo $install->isStepPassed(GaletteInstall::STEP_CHECK) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Checks"); ?></div>
                                    </div>
                                </div>
                                <div
                                    class="step<?php echo $install->isDbStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_DB) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isDbStep() && !$install->isStepPassed(GaletteInstall::STEP_DB)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="database icon<?php echo $install->isStepPassed(GaletteInstall::STEP_DB) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database"); ?></div>
                                    </div>
                                </div>
                                <div
                                    class="step<?php echo $install->isDbCheckStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_DB_CHECKS) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isDbCheckStep() && !$install->isStepPassed(GaletteInstall::STEP_DB_CHECKS)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="key icon<?php echo $install->isStepPassed(GaletteInstall::STEP_DB_CHECKS) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database access and permissions"); ?></div>
                                    </div>
                                </div>
<?php
if ($install->isUpgrade()) {
    ?>
                                <div
                                    class="step<?php echo $install->isVersionSelectionStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_VERSION) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isVersionSelectionStep() && !$install->isStepPassed(GaletteInstall::STEP_VERSION)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="tag icon<?php echo $install->isStepPassed(GaletteInstall::STEP_VERSION) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Version selection"); ?></div>
                                    </div>
                                </div>
                                <div
                                    class="step<?php echo $install->isDbUpgradeStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_DB_UPGRADE) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isDbUpgradeStep() && !$install->isStepPassed(GaletteInstall::STEP_DB_UPGRADE)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="sync alt icon<?php echo $install->isStepPassed(GaletteInstall::STEP_DB_UPGRADE) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database upgrade"); ?></div>
                                    </div>
                                </div>
    <?php
} else {
    ?>
                                <div
                                    class="step<?php echo $install->isDbinstallStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_DB_INSTALL) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isDbinstallStep() && !$install->isStepPassed(GaletteInstall::STEP_DB_INSTALL)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="spinner icon<?php echo $install->isStepPassed(GaletteInstall::STEP_DB_INSTALL) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database installation"); ?></div>
                                    </div>
                                </div>
    <?php
}

if (!$install->isUpgrade()) {
    ?>
                                <div
                                    class="step<?php echo $install->isAdminStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_ADMIN) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isAdminStep() && !$install->isStepPassed(GaletteInstall::STEP_ADMIN)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="user icon<?php echo $install->isStepPassed(GaletteInstall::STEP_ADMIN) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Admin parameters"); ?></div>
                                    </div>
                                </div>
    <?php
}
?>
                                <div
                                    class="step<?php echo $install->isTelemetryStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_TELEMETRY) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isTelemetryStep() && !$install->isStepPassed(GaletteInstall::STEP_TELEMETRY)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="chart bar icon<?php echo $install->isStepPassed(GaletteInstall::STEP_TELEMETRY) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Telemetry"); ?></div>
                                    </div>
                                </div>
                                <div
                                    class="step<?php echo $install->isGaletteInitStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_GALETTE_INIT) ? ' disabled' : '') ?>"
                                    <?php if (!$install->isGaletteInitStep() && !$install->isStepPassed(GaletteInstall::STEP_GALETTE_INIT)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="cogs icon<?php echo $install->isStepPassed(GaletteInstall::STEP_GALETTE_INIT) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Galette initialization"); ?></div>
                                    </div>
                                </div>
                                <div
                                    class="step<?php echo $install->isEndStep() ? ' active' : (!$install->isStepPassed(GaletteInstall::STEP_END) ? ' disabled' : ''); ?>"
                                    <?php if (!$install->isEndStep() && !$install->isStepPassed(GaletteInstall::STEP_END)) {
                                        echo 'aria-disabled="true"';
                                    } ?>
                                >
                                    <i class="flag checkered icon<?php echo $install->isStepPassed(GaletteInstall::STEP_END) ? ' green' : ''; ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("End!"); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="twelve wide column">
<?php
if ($install_disabled) {
    include_once __DIR__ . '/../install/steps/disabled.php';
} elseif ($install->isCheckStep()) {
    include_once __DIR__ . '/../install/steps/check.php';
} elseif ($install->isDbStep()) {
    include_once __DIR__ . '/../install/steps/db.php';
} elseif ($install->isDbCheckStep()) {
    include_once __DIR__ . '/../install/steps/db_checks.php';
} elseif ($install->isVersionSelectionStep()) {
    include_once __DIR__ . '/../install/steps/db_select_version.php';
} elseif ($install->isDbinstallStep() || $install->isDbUpgradeStep()) {
    include_once __DIR__ . '/../install/steps/db_install.php';
} elseif ($install->isAdminStep()) {
    include_once __DIR__ . '/../install/steps/admin.php';
} elseif ($install->isTelemetryStep()) {
    include_once __DIR__ . '/../install/steps/telemetry.php';
} elseif ($install->isGaletteInitStep()) {
    include_once __DIR__ . '/../install/steps/galette.php';
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
                                Galette <?php echo Galette::gitVersion(false); ?>
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
