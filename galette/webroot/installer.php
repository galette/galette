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
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;

//set a flag saying we work from installer
//that way, in galette.inc.php, we'll only include relevant parts
$installer = true;
//specific logfile for installer
$logfile = 'galette_install';
define('GALETTE_BASE_PATH', '../');
define('GALETTE_THEME_DIR', './themes/default/');

require_once '../includes/galette.inc.php';

if (isset($session['lang'])) {
    $i18n = unserialize($session['lang']);
} else {
    $i18n = new Galette\Core\I18n();
}

require_once '../includes/i18n.inc.php';

//when upgrading, make sure that old objects in current session are destroyed
if ( defined('PREFIX_DB') && defined('NAME_DB') ) {
    unset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]);
}

$install = null;
if ( isset($session[md5(GALETTE_ROOT)]) && !isset($_GET['raz']) ) {
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
}

if ( isset($_POST['stepback_btn']) ) {
    $install->atPreviousStep();
} else if ( isset($_POST['install_permsok']) && $_POST['install_permsok'] == 1 ) {
    $install->atTypeStep();
} else if ( isset($_POST['install_type']) ) {
    $install->setMode($_POST['install_type']);
    $install->atDbStep();
} elseif ( isset($_POST['install_dbtype'])  ) {
    $install->setDbType($_POST['install_dbtype'], $error_detected);

    if ( empty($_POST['install_dbhost']) ) {
        $error_detected[] = _T("No host");
    }
    if ( empty($_POST['install_dbport']) ) {
        $error_detected[] = _T("No port");
    }
    if ( empty($_POST['install_dbuser']) ) {
        $error_detected[] = _T("No user name");
    }
    if ( empty($_POST['install_dbpass']) ) {
        $error_detected[] = _T("No password");
    }
    if ( empty($_POST['install_dbname']) ) {
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
} elseif ( isset($_POST['install_dbperms_ok']) ) {
    if ( $install->isInstall() ) {
        $install->atDbInstallStep();
    } elseif ( $install->isUpgrade() ) {
        $install->atVersionSelection();
    }
} elseif ( isset($_POST['previous_version']) ) {
    $install->setInstalledVersion($_POST['previous_version']);
    $install->atDbUpgradeStep();
} elseif ( isset($_POST['install_dbwrite_ok']) && $install->isInstall() ) {
    $install->atAdminStep();
} else if ( isset($_POST['install_dbwrite_ok']) && $install->isUpgrade() ) {
    $install->atGaletteInitStep();
} elseif ( isset($_POST['install_adminlogin'])
    && isset($_POST['install_adminpass'])
    && $install->isInstall()
) {

    if ( $_POST['install_adminlogin'] == '' ) {
        $error_detected[] = _T("No user name");
    }
    if ( strpos($_POST['install_adminlogin'], '@') != false ) {
        $error_detected[] = _T("The username cannot contain the @ character");
    }
    if ( $_POST['install_adminpass'] == '' ) {
        $error_detected[] = _T("No password");
    }
    if ( ! isset($_POST['install_passwdverified'])
        && strcmp(
            $_POST['install_adminpass'],
            $_POST['install_adminpass_verif']
        )
    ) {
        $error_detected[] = _T("Passwords mismatch");
    }
    if ( count($error_detected) == 0 ) {
        $install->setAdminInfos(
            $_POST['install_adminlogin'],
            $_POST['install_adminpass']
        );
        $install->atGaletteInitStep();
    }
} elseif ( isset($_POST['install_prefs_ok']) ) {
    $install->atEndStep();
}

if ( !$install->isEndStep()
    && ($install->postCheckDb() || $install->isDbCheckStep())
) {
    //if we have passed database configuration, define required constants
    initDbConstants($install);

    if ( $install->postCheckDb() ) {
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
        <link rel="stylesheet" type="text/css" href="./themes/default/galette.css"/>
        <link rel="stylesheet" type="text/css" href="./themes/default/install.css"/>
        <link rel="stylesheet" type="text/css" href="./themes/default/jquery-ui/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>.custom.css"/>
        <script type="text/javascript" src="./js/jquery/jquery-<?php echo JQUERY_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="./js/jquery/jquery-migrate-<?php echo JQUERY_MIGRATE_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="./js/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.widget.min.js"></script>
        <script type="text/javascript" src="./js//jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.button.min.js"></script>
        <script type="text/javascript" src="./js/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.tooltip.min.js"></script>
        <script type="text/javascript" src="./js/jquery/jquery.bgFade.js"></script>
        <script type="text/javascript" src="./js/common.js"></script>
        <link rel="shortcut icon" href="./themes/default/images/favicon.png" />
        <!--[if lt IE9]>
            <script type="text/javascript" src="./js/html5-ie.js"></script>
        <!endif]-->
    </head>
    <body>
        <section>
            <header>
                <h1 id="titre">
                    <?php echo _T("Galette installation") . ' - ' . $install->getStepTitle(); ?>
                </h1>
                <ul id="langs">
<?php
foreach ( $i18n->getList() as $langue ) {
    ?>
                    <li><a href="?pref_lang=<?php echo $langue->getID(); ?>"><img src="<?php echo $langue->getFlag(); ?>" alt="<?php echo $langue->getName(); ?>" lang="<?php echo $langue->getAbbrev(); ?>" class="flag"/></a></li>
    <?php
}
?>
                </ul>
            </header>
<?php
if ( count($error_detected) > 0 ) {
    ?>

            <div id="errorbox">
                <h1><?php echo _T("- ERROR -"); ?></h1>
                <ul>
    <?php
    foreach ( $error_detected as $error ) {
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
            <div>
<?php
if ( $install->isCheckStep() ) {
    include_once __DIR__ . '/../install/steps/check.php';
} else if ( $install->isTypeStep() ) {
    include_once __DIR__ . '/../install/steps/type.php';
} else if ( $install->isDbStep() ) {
    include_once __DIR__ . '/../install/steps/db.php';
} else if ( $install->isDbCheckStep() ) {
    include_once __DIR__ . '/../install/steps/db_checks.php';
} else if ( $install->isVersionSelectionStep() ) {
    include_once __DIR__ . '/../install/steps/db_select_version.php';
} else if ( $install->isDbinstallStep() || $install->isDbUpgradeStep() ) {
    include_once __DIR__ . '/../install/steps/db_install.php';
} else if ( $install->isAdminStep() ) {
    include_once __DIR__ . '/../install/steps/admin.php';
} else if ( $install->isGaletteInitStep()  ) {
    include_once __DIR__ . '/../install/steps/galette.php';
} else if ( $install->isEndStep() ) {
    include_once __DIR__ . '/../install/steps/end.php';
}
?>
            </div>
            <footer>
                <p><?php echo _T("Steps:"); ?></p>
                <ol>
                    <li<?php if( $install->isCheckStep() ) echo ' class="current"'; ?>><?php echo _T("Checks"); ?> - </li>
                    <li<?php if( $install->isTypeStep() ) echo ' class="current"'; ?>><?php echo _T("Installation mode"); ?> - </li>
                    <li<?php if( $install->isDbStep() ) echo ' class="current"'; ?>><?php echo _T("Database"); ?> - </li>
                    <li<?php if( $install->isDbCheckStep() ) echo ' class="current"'; ?>><?php echo _T("Database access/permissions"); ?> - </li>
<?php
if ( $install->isUpgrade() ) {
    ?>
                    <li<?php if( $install->isVersionSelectionStep() ) echo ' class="current"'; ?>><?php echo _T("Version selection"); ?> - </li>
                    <li<?php if( $install->isDbUpgradeStep() ) echo ' class="current"'; ?>><?php echo _T("Database upgrade"); ?> - </li>
    <?php
} else {
    ?>
                    <li<?php if( $install->isDbinstallStep() ) echo ' class="current"'; ?>><?php echo _T("Database installation"); ?> - </li>
    <?php
}

if ( !$install->isUpgrade() ) {
    ?>
                    <li<?php if( $install->isAdminStep() ) echo ' class="current"'; ?>><?php echo _T("Admin parameters"); ?> - </li>
    <?php
}
?>
                    <li<?php if( $install->isGaletteInitStep() ) echo ' class="current"'; ?>><?php echo _T("Galette initialisation"); ?> - </li>
                    <li<?php if( $install->isEndStep() ) echo ' class="current"'; ?>><?php echo _T("End!"); ?></li>
                </ol>
            </footer>
        </section>
        <a id="copyright" href="http://galette.eu/">Galette <?php echo GALETTE_VERSION; ?></a>
    </body>
</html>
<?php
if ( !$install->isEndStep() ) {
    $session[md5(GALETTE_ROOT)] = serialize($install);
}

if ( isset($profiler) ) {
    $profiler->stop();
}
?>
