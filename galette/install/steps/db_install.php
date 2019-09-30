<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, database initialization/upgrade
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

//ok, let's run the scripts!
$db_installed = $install->executeScripts($zdb);
?>
                <div class="ui segment">
                    <div class="content field">
                        <div class="ui text container">
<?php
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
                        </div>
                    </div>
                </div>

                <form action="installer.php" method="POST" class="ui form">
                    <div class="ui mobile tablet computer reversed equal width grid">
                        <div class="right aligned column">
<?php
if (!$db_installed) {
?>
                            <button type="submit" class="ui right labeled icon button"><i class="redo alternate double right icon"></i> <?php echo _T("Retry"); ?></button>
<?php
}
?>
                            <button type="submit" class="ui right labeled icon button"<?php if (!$db_installed) { echo ' disabled="disabled"'; } ?>><i class="angle double right icon"></i> <?php echo _T("Next step"); ?></button>
<?php
if ($db_installed) {
?>
                            <input type="hidden" name="install_dbwrite_ok" value="1"/>
<?php
}
?>
                        </div>
                        <div class="left aligned column">
<?php
if (!$db_installed) {
    //once DB is installed, that does not make sense to go back
?>
                            <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double left icon"></i> <?php echo _T("Back"); ?></button>
<?php
}
?>
                        </div>
                    </div>
                </form>
