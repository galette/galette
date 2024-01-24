<?php
/**
 * Copyright © 2003-2024 The Galette Team
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

//ok, let's run the scripts!
$db_installed = $install->executeScripts($zdb);

if ($db_installed === false) {
    $msg = _T("Database has not been installed!");
    if ($install->isUpgrade()) {
        $msg = _T("Database has not been upgraded!");
    }
    echo '<p class="ui red message">' . $msg . '</p>';
} else {
    $msg = _T("Database has been installed :)");
    if ($install->isUpgrade()) {
        $msg = _T("Database has been upgraded :)");
    }
    echo '<p class="ui green message">' . $msg . '</p>';
}
?>
    <ul class="leaders">
<?php
foreach ($install->getDbInstallReport() as $r) {
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
if (!$db_installed) {
?>
                <button type="submit" class="ui right labeled icon button"><i class="redo alternate double right icon" aria-hidden="true"></i> <?php echo _T("Retry"); ?></button>
<?php
}
?>
                <button type="submit" class="ui right labeled primary icon button"<?php if (!$db_installed) { echo ' disabled="disabled"'; } ?>><i class="angle double right icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
<?php
if ($db_installed) {
?>
                <input type="hidden" name="install_dbwrite_ok" value="1"/>
<?php
}
?>
            </div>
<?php
if (!$db_installed) {
    //once DB is installed, that does not make sense to go back
    ?>
                <div class="column">
                    <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double left icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
                </div>
    <?php
}
?>
        </div>
    </form>
