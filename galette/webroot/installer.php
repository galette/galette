<?php

/**
 * Copyright © 2003-2025 The Galette Team
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
$installer = true;
define('GALETTE_ROOT', __DIR__ . '/../');
define('GALETTE_INSTALLER', true);

// check PHP modules
require_once GALETTE_ROOT . '/vendor/autoload.php';
require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';

if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<') || !extension_loaded('intl')) {
    header('location: compat_test.php');
    die(1);
}

//specific logfile for installer
$logfile = 'galette_install';
define('GALETTE_BASE_PATH', '../');

require_once __DIR__ . '/../includes/galette.inc.php';

session_start();
$session_name = 'galette_install_' . str_replace('.', '_', GALETTE_VERSION);
$session = &$_SESSION['galette'][$session_name];

$gapp = new \Galette\Core\SlimApp();
$app = $gapp->getApp();
require_once __DIR__ . '/../includes/dependencies.php';

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

$error_detected = array();

if ($install->isStepPassed(GaletteInstall::STEP_TYPE)) {
    define('GALETTE_LOGGER_CHECKED', true);

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

    if ($install->postCheckDb()) {
        try {
            $zdb = new GaletteDb();
        } catch (Throwable $e) {
            if (!$install->isDbCheckStep()) {
                throw $e;
            }
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
    if (strpos($_POST['install_adminlogin'], '@')) {
        $error_detected[] = _T("The username cannot contain the @ character");
    }
    if ($_POST['install_adminpass'] == '') {
        $error_detected[] = _T("No password");
    }
    if (
        !isset($_POST['install_passwdverified'])
        && strcmp(
            $_POST['install_adminpass'],
            $_POST['install_adminpass_verif']
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
        $preferences = new Preferences($zdb);
        $plugins = new Plugins();
        $telemetry = new Telemetry(
            $zdb,
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

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $i18n->getAbbrev(); ?>"<?php if ($i18n->isRtl()) { ?> dir="rtl"<?php } ?>>
    <head>
        <title><?php echo _T("Galette Installation") . ' - ' . $install->getStepDetail('title'); ?></title>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width" />
        <link rel="stylesheet" type="text/css" href="./themes/default/ui/semantic<?php if ($i18n->isRtl()) { ?>.rtl<?php } ?>.min.css" />
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
                        <img class="icon" alt="[ Galette ]" src="./themes/default/images/galette.png"/>
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
                                <div class="step<?php if ($install->isCheckStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_CHECK)) { echo ' disabled'; } ?>">
                                    <i class="tasks icon<?php if ($install->isStepPassed(GaletteInstall::STEP_CHECK)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Checks"); ?></div>
                                    </div>
                                </div>
                                <div class="step<?php if ($install->isTypeStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_TYPE)) { echo ' disabled'; } ?>">
                                    <i class="question icon<?php if ($install->isStepPassed(GaletteInstall::STEP_TYPE)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Installation mode"); ?></div>
                                    </div>
                                </div>
                                <div class="step<?php if ($install->isDbStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_DB)) { echo ' disabled'; } ?>">
                                    <i class="database icon<?php if ($install->isStepPassed(GaletteInstall::STEP_DB)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database"); ?></div>
                                    </div>
                                </div>
                                <div class="step<?php if ($install->isDbCheckStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_DB_CHECKS)) { echo ' disabled'; } ?>">
                                    <i class="key icon<?php if ($install->isStepPassed(GaletteInstall::STEP_DB_CHECKS)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database access and permissions"); ?></div>
                                    </div>
                                </div>
<?php
if ($install->isUpgrade()) {
    ?>
                                <div class="step<?php if ($install->isVersionSelectionStep()) {echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_VERSION)) {echo ' disabled'; } ?>">
                                    <i class="tag icon<?php if ($install->isStepPassed(GaletteInstall::STEP_VERSION)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Version selection"); ?></div>
                                    </div>
                                </div>
                                <div class="step<?php if ($install->isDbUpgradeStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_DB_UPGRADE)) { echo ' disabled'; } ?>">
                                    <i class="sync alt icon<?php if ($install->isStepPassed(GaletteInstall::STEP_DB_UPGRADE)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database upgrade"); ?></div>
                                    </div>
                                </div>
    <?php
} else {
    ?>
                                <div class="step<?php if ($install->isDbinstallStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_DB_INSTALL)) { echo ' disabled'; } ?>">
                                    <i class="spinner icon<?php if ($install->isStepPassed(GaletteInstall::STEP_DB_INSTALL)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Database installation"); ?></div>
                                    </div>
                                </div>
    <?php
}

if (!$install->isUpgrade()) {
    ?>
                                <div class="step<?php if ($install->isAdminStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_ADMIN)) { echo ' disabled'; } ?>">
                                    <i class="user icon<?php if ($install->isStepPassed(GaletteInstall::STEP_ADMIN)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Admin parameters"); ?></div>
                                    </div>
                                </div>
    <?php
}
?>
                                <div class="step<?php if ($install->isTelemetryStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_TELEMETRY)) { echo ' disabled'; } ?>">
                                    <i class="chart bar icon<?php if ($install->isStepPassed(GaletteInstall::STEP_TELEMETRY)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Telemetry"); ?></div>
                                    </div>
                                </div>
                                <div class="step<?php if ($install->isGaletteInitStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_GALETTE_INIT)) { echo ' disabled'; } ?>">
                                    <i class="cogs icon<?php if ($install->isStepPassed(GaletteInstall::STEP_GALETTE_INIT)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("Galette initialization"); ?></div>
                                    </div>
                                </div>
                                <div class="step<?php if ($install->isEndStep()) { echo ' active'; } elseif (!$install->isStepPassed(GaletteInstall::STEP_END)) { echo ' disabled'; } ?>">
                                    <i class="flag checkered icon<?php if ($install->isStepPassed(GaletteInstall::STEP_END)) { echo ' green'; } ?>"></i>
                                    <div class="content">
                                        <div class="title"><?php echo _T("End!"); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="twelve wide column">
<?php
if ($install->isCheckStep()) {
    include_once __DIR__ . '/../install/steps/check.php';
} elseif ($install->isTypeStep()) {
    include_once __DIR__ . '/../install/steps/type.php';
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
