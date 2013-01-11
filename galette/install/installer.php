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
if ( isset($session[md5(GALETTE_ROOT)]) ) {
    $install = unserialize($session[md5(GALETTE_ROOT)]);
} else {
    $install = new GaletteInstall();
}

if ( isset($_POST['stepback_btn']) ) {
    $install->atPreviousStep();
} else if ( isset($_POST['install_permsok']) && $_POST['install_permsok'] == 1 ) {
    $install->atTypeStep();
} else if ( isset($_POST['install_type']) ) {
    $install->setMode($_POST['install_type']);
    $install->atDbStep();
}

$error_detected = false;

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
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.widget.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.button.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.bgiframe.pack.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.bgFade.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/chili-1.7.pack.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.tooltip.pack.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/common.js"></script>
        <link rel="shortcut icon" href="<?php echo GALETTE_TPL_SUBDIR; ?>images/favicon.png" />
<?php /*<script type="text/javascript">
            $(function() {
<?php
if ($step == '1') { ?>
                $('#pref_lang').change(function() {
                    this.form.submit();
                });
    <?php
}
?>
            });
        </script>*/ ?>
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
            <div>
<?php
if ( $install->isCheckStep() ) {
    include_once 'steps/check.php';
} else if ( $install->isTypeStep() ) {
    include_once 'steps/type.php';
} else if ( $install->isDbStep() ) {
    include_once 'steps/db.php';
}
?>
            </div>
            <footer>
                <p><?php echo _T("Steps:"); ?></p>
                <ol>
                    <?php /*<li<?php if( $step == '1') echo ' class="current"'; ?>><?php echo _T("Language"); ?> - </li>*/ ?>
                    <li<?php if( $install->isCheckStep() ) echo ' class="current"'; ?>><?php echo _T("Checks"); ?> - </li>
                    <li<?php if( $install->isTypeStep() ) echo ' class="current"'; ?>><?php echo _T("Installation mode"); ?> - </li>
                    <li<?php if( $install->isDbStep() ) echo ' class="current"'; ?>><?php echo _T("Database"); ?> - </li>
                    <?php /*<li<?php if( $step == 'i5' || $step == 'u5' ) echo ' class="current"'; ?>><?php echo _T("Access to the database"); ?> - </li>
                    <li<?php if( $step == 'i6' || $step == 'u6' ) echo ' class="current"'; ?>><?php echo _T("Access permissions to database"); ?> - </li>
                    <li<?php if( $step == 'i7' || $step == 'u7' ) echo ' class="current"'; ?>><?php echo _T("Tables Creation/Update"); ?> - </li>
                    <li<?php if( $step == 'i8' || $step == 'u8' ) echo ' class="current"'; ?>><?php echo _T("Admin parameters"); ?> - </li>
                    <li<?php if( $step == 'i9' || $step == 'u9' ) echo ' class="current"'; ?>><?php echo _T("Saving the parameters"); ?> - </li>
                    <li<?php if( $step == 'i10' || $step == 'u10' ) echo ' class="current"'; ?>><?php echo _T("End!"); ?></li> */ ?>
                </ol>
            </footer>
        </section>
        <a id="copyright" href="http://galette.tuxfamily.org/">Galette <?php echo GALETTE_VERSION; ?></a>
    </body>
</html>
<?php
$session[md5(GALETTE_ROOT)] = serialize($install);
?>
