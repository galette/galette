<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main Galette initialisation
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
 *
 * This file is part of Galette (http://galette.eu).
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
 *
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
use Analog\Analog;
use Analog\Handler;
use Analog\Handler\LevelName;

//set a flag saying we work from installer
//that way, in galette.inc.php, we'll only include relevant parts
$installer = true;
define('GALETTE_ROOT', __DIR__ . '/../');
define('GALETTE_MODE', 'INSTALL');

// check PHP modules
require_once GALETTE_ROOT . '/vendor/autoload.php';
require_once GALETTE_ROOT . 'config/versions.inc.php';

if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<') || !extension_loaded('intl')) {
    header('location: compat_test.php');
    die(1);
}

//specific logfile for installer
$logfile = 'galette_install';
define('GALETTE_BASE_PATH', '../');
define('GALETTE_THEME_DIR', './themes/default/');

require_once '../includes/galette.inc.php';

session_start();
$session_name = 'galette_install_' . str_replace('.', '_', GALETTE_VERSION);
$session = &$_SESSION['galette'][$session_name];

$app =  new \Galette\Core\SlimApp();
require_once '../includes/dependencies.php';

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

/**
 * Initialize database constants to connect
 *
 * @param Install $install Installer
 *
 * @return void
 */
function initDbConstants($install)
{
    define('TYPE_DB', $install->getDbType());
    define('PREFIX_DB', $install->getTablesPrefix());
    define('USER_DB', $install->getDbUser());
    define('PWD_DB', $install->getDbPass());
    define('HOST_DB', $install->getDbHost());
    define('PORT_DB', $install->getDbPort());
    define('NAME_DB', $install->getDbName());
}

if ($install->isStepPassed(GaletteInstall::STEP_TYPE)) {
    define('GALETTE_LOGGER_CHECKED', true);

    $log_path = GALETTE_LOGS_PATH . $logfile . '.log';
    $galette_run_log = LevelName::init(Handler\File::init($log_path));
    Analog::handler($galette_run_log);
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
    $install->atGaletteInitStep();
} elseif (isset($_POST['install_adminlogin'])
    && isset($_POST['install_adminpass'])
    && $install->isInstall()
) {
    if ($_POST['install_adminlogin'] == '') {
        $error_detected[] = _T("No user name");
    }
    if (strpos($_POST['install_adminlogin'], '@') != false) {
        $error_detected[] = _T("The username cannot contain the @ character");
    }
    if ($_POST['install_adminpass'] == '') {
        $error_detected[] = _T("No password");
    }
    if (!isset($_POST['install_passwdverified'])
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
        $install->atGaletteInitStep();
    }
} elseif (isset($_POST['install_prefs_ok'])) {
    $install->atEndStep();
}

if (!$install->isEndStep()
    && ($install->postCheckDb() || $install->isDbCheckStep())
) {
    //if we have passed database configuration, define required constants
    initDbConstants($install);

    if ($install->postCheckDb()) {
        //while before check db, connection is not checked
        $zdb = new GaletteDb();
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $i18n->getAbbrev(); ?>">
    <head>
        <title><?php echo _T("Galette Installation") . ' - ' . $install->getStepTitle(); ?></title>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width" />
        <link rel="stylesheet" type="text/css" href="./assets/css/galette-main.bundle.min.css" />
        <link rel="stylesheet" type="text/css" href="./themes/default/install.css"/>
        <link rel="stylesheet" type="text/css" href="/assets/semantic/semantic.min.css" />
        <link rel="stylesheet" type="text/css" href="/themes/default/galette-ng.css" />
        <script type="text/javascript" src="./assets/js/galette-main.bundle.min.js"></script>
        <script type="text/javascript" src="/assets/semantic/semantic.min.js"></script>
        <script type="text/javascript" src="/js/common-ng.js"></script>
        <link rel="shortcut icon" href="./themes/default/images/favicon.png" />
    </head>
    <body class="pushable">
        <header id="top-navbar" class="ui fixed menu bgcolor">
            <div class="ui container">
                <div class="header item">
                    <!-- <img src="/logo" width="129" height="60" alt="[ Galette ]" class="logo" /> -->
                    <span><?php echo _T("Galette installation") ?></span>
                </div>
                <div class="language ui dropdown right item">
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
        <div class="pusher">
            <div id="main" class="ui container">
                <div class="ui basic segment">
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
                    <h1 class="ui block center aligned header">
                        <?php echo $install->getStepTitle(); ?>
                    </h1>
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
} elseif ($install->isGaletteInitStep()) {
    include_once __DIR__ . '/../install/steps/galette.php';
} elseif ($install->isEndStep()) {
    include_once __DIR__ . '/../install/steps/end.php';
}
?>
                <div class="ui tablet stackable mini eight steps">
                    <div class="step<?php if ($install->isCheckStep()) echo ' active'; ?>">
                        <i class="tasks icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Checks"); ?></div>
                        </div>
                    </div>
                    <div class="step<?php if ($install->isTypeStep()) echo ' active'; ?>">
                        <i class="check double icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Installation mode"); ?></div>
                        </div>
                    </div>
                    <div class="step<?php if ($install->isDbStep()) echo ' active'; ?>">
                        <i class="database icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Database"); ?></div>
                        </div>
                    </div>
                    <div class="step<?php if ($install->isDbCheckStep()) echo ' active'; ?>">
                        <i class="shield alt icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Database access/permissions"); ?></div>
                        </div>
                    </div>
<?php
if ($install->isUpgrade()) {
    ?>
                    <div class="step<?php if ($install->isVersionSelectionStep()) echo ' active'; ?>">
                        <i class="tag icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Version selection"); ?></div>
                        </div>
                    </div>
                    <div class="step<?php if ($install->isDbUpgradeStep()) echo ' active'; ?>">
                        <i class="sync alt icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Database upgrade"); ?></div>
                        </div>
                    </div>
    <?php
} else {
    ?>
                    <div class="step<?php if ($install->isDbinstallStep()) echo ' active'; ?>">
                        <i class="spinner icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Database installation"); ?></div>
                        </div>
                    </div>
    <?php
}

if (!$install->isUpgrade()) {
    ?>
                    <div class="step<?php if ($install->isAdminStep()) echo ' active'; ?>">
                        <i class="user icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Admin parameters"); ?></div>
                        </div>
                    </div>
    <?php
}
?>
                    <div class="step<?php if ($install->isGaletteInitStep()) echo ' active'; ?>">
                        <i class="cogs icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("Galette initialisation"); ?></div>
                        </div>
                    </div>
                    <div class="step<?php if ($install->isEndStep()) echo ' active'; ?>">
                        <i class="flag checkered icon"></i>
                        <div class="content">
                            <div class="title"><?php echo _T("End!"); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="ui basic center aligned segment">
                <div class="row">
                    <nav class="ui horizontal bulleted link list">
                        <a href="https://galette.eu" class="item">
                            <i class="icon globe europe"></i>
                            <?php echo _T("Website"); ?>
                        </a>
                        <a href="https://doc.galette.eu" class="item">
                            <i class="icon book"></i>
                           <?php echo _T("Documentation"); ?>
                        </a>
                        <a href="https://twitter.com/galette_soft" class="item">
                            <i class="icon twitter"></i>
                            @galette_soft
                        </a>
                        <a href="https://framapiaf.org/@galette" class="item">
                            <i class="icon mastodon"></i>
                            @galette
                        </a>
                    </nav>
                </div>
                <div class="row">
                    <nav class="ui horizontal bulleted link list">
                        <a id="copyright" href="https://galette.eu/" class="item">
                            <i class="icon cookie bite"></i>
                            Galette <?php echo GALETTE_DISPLAY_VERSION; ?>
                        </a>
                    </nav>
                </div>
            </footer>
        </div>
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
