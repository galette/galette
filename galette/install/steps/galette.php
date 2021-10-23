<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, Galette initialisation
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8 - 2013-01-12
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
use Galette\Core\Login;

$results = array();
$oks = array();
$errs = array();
$install->reinitReport();

$config_file_ok = $install->writeConfFile();
include_once GALETTE_CONFIG_PATH . 'config.inc.php';

$objects_ok = $install->initObjects($i18n, $zdb, new Login($zdb, $i18n));
?>
                <h2><?php echo $install->getStepTitle(); ?></h2>
<?php

if ($config_file_ok === true && $objects_ok === true) {
    echo '<p id="infobox">' . _T("Configuration file created!") .
        '<br/>' . _T("Data initialized.") . '</p>';
} else {
    echo '<p id="errorbox">' . _T("An error occurred :(") . '</p>';
}
?>
                <ul class="leaders">
<?php
foreach ($install->getInitializationReport() as $r) {
    ?>
                    <li>
                        <span><?php echo $r['message']; ?></span>
                        <span><?php echo $install->getValidationImage($r['res']); ?></span>
                    </li>
    <?php
}
?>
                </ul>
                <form action="installer.php" method="POST">
                    <p id="btn_box">
<?php
if (!$config_file_ok || !$objects_ok) {
    ?>
                        <button type="submit"><?php echo _T("Retry"); ?> <i class="fas fa-sync-alt"></i></button>
    <?php
}
?>

                        <button type="submit"<?php if (!$config_file_ok || !$objects_ok) { echo ' disabled="disabled"'; } ?>><?php echo _T("Next step"); ?> <i class="fas fa-forward"></i></button>
<?php
if ($config_file_ok && $objects_ok) {
    ?>
                        <input type="hidden" name="install_prefs_ok" value="1"/>
    <?php
}

if (!$config_file_ok || !$objects_ok) {
    //once DB is installed, that does not make sense to go back
    ?>
                        <button type="submit" id="btnback" name="stepback_btn" formnovalidate><i class="fas fa-backward"></i> <?php echo _T("Back"); ?></button>
    <?php
}
?>
                    </p>
                </form>
