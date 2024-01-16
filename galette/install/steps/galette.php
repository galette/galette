<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, Galette initialisation
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
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
$objects_ok = $install->initObjects($i18n, $zdb, new Login($zdb, $i18n));

if ($config_file_ok === true && $objects_ok === true) {
    echo '<p class="ui green message">' . _T("Configuration file created!") .
        '<br/>' . _T("Data initialized.") . '</p>';
} else {
    echo '<p class="ui red message">' . _T("An error occurred :(") . '</p>';
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

    <div class="ui section divider"></div>

    <form action="installer.php" method="POST" class="ui form">
        <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
            <div class="right aligned column">
<?php
if (!$config_file_ok || !$objects_ok) {
?>
                <button type="submit" class="ui right labeled icon button"><i class="redo alternate double right icon" aria-hidden="true"></i> <?php echo _T("Retry"); ?></button>
<?php
}
?>
                <button type="submit" class="ui right labeled primary icon button"<?php if (!$config_file_ok || !$objects_ok) { echo ' disabled="disabled"'; } ?>><i class="angle double right icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
<?php
if ($config_file_ok && $objects_ok) {
?>
                <input type="hidden" name="install_prefs_ok" value="1"/>
<?php
}
?>
            </div>
<?php
if (!$config_file_ok || !$objects_ok) {
    //once DB is installed, that does not make sense to go back
    ?>
                <div class="left aligned column">
                    <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double left icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
                </div>
                <?php
}
?>
        </div>
    </form>
