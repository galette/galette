<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main Galette initialisation
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;

//set a flag saying we work from installer
//that way, in galette.inc.php, we'll only include relevant parts
$installer = true;
//specific logfile for installer
$logfile = 'galette_install';
define('GALETTE_BASE_PATH', '../');
define('GALETTE_TPL_SUBDIR', GALETTE_BASE_PATH . 'templates/default/');

require_once '../includes/galette.inc.php';

//when upgrading, make sure that old objects in current session are destoryed
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

    if (TYPE_DB != 'sqlite') {
        define('USER_DB', $install->getDbUser());
        define('PWD_DB', $install->getDbPass());
        define('HOST_DB', $install->getDbHost());
        define('PORT_DB', $install->getDbPort());
        define('NAME_DB', $install->getDbName());
        define('PREFIX_DB', $install->getTablesPrefix());
    }
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

    if ( $install->getDbType() != GaletteDb::SQLITE ) {
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
} elseif ( isset($_POST['install_dbwrite_ok']) ) {
    $install->atAdminStep();
} elseif ( isset($_POST['install_adminlogin'])
    && isset($_POST['install_adminpass'])
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

if ( !$install->isEndStep() && ($install->postCheckDb() || $install->isDbCheckStep()) ) {
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
        <link rel="stylesheet" type="text/css" href="<?php echo GALETTE_TPL_SUBDIR; ?>galette.css"/>
        <link rel="stylesheet" type="text/css" href="<?php echo GALETTE_TPL_SUBDIR; ?>install.css"/>
        <link rel="stylesheet" type="text/css" href="<?php echo GALETTE_TPL_SUBDIR; ?>jquery-ui/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>.custom.css"/>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-<?php echo JQUERY_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-migrate-<?php echo JQUERY_MIGRATE_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.widget.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.button.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.bgiframe.pack.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.bgFade.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/chili-1.7.pack.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.tooltip.pack.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/common.js"></script>
        <link rel="shortcut icon" href="<?php echo GALETTE_TPL_SUBDIR; ?>images/favicon.png" />
        <!--[if lt IE9]>
            <script type="text/javascript" src="{$scripts_dir}html5-ie.js"></script>
        <!endif]-->
    </head>
    <body>
        <section>
            <header>
                <h1 id="titre">
                    <img src="<?php echo GALETTE_TPL_SUBDIR; ?>images/galette.png" alt="[ Galette ]" />
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
    include_once 'steps/check.php';
} else if ( $install->isTypeStep() ) {
    include_once 'steps/type.php';
} else if ( $install->isDbStep() ) {
    include_once 'steps/db.php';
} else if ( $install->isDbCheckStep() ) {
    include_once 'steps/db_checks.php';
} else if ( $install->isVersionSelectionStep() ) {
    include_once 'steps/db_select_version.php';
} else if ( $install->isDbinstallStep() || $install->isDbUpgradeStep() ) {
    include_once 'steps/db_install.php';
} else if ( $install->isAdminStep() ) {
    include_once 'steps/admin.php';
} else if ( $install->isGaletteInitStep()  ) {
    include_once 'steps/galette.php';
} else if ( $install->isEndStep() ) {
    include_once 'steps/end.php';
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
?>
                    <li<?php if( $install->isAdminStep() ) echo ' class="current"'; ?>><?php echo _T("Admin parameters"); ?> - </li>
                    <li<?php if( $install->isGaletteInitStep() ) echo ' class="current"'; ?>><?php echo _T("Galette initialisation"); ?> - </li>
                    <li<?php if( $install->isEndStep() ) echo ' class="current"'; ?>><?php echo _T("End!"); ?></li>
                </ol>
            </footer>
        </section>
        <a id="copyright" href="http://galette.tuxfamily.org/">Galette <?php echo GALETTE_VERSION; ?></a>
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
