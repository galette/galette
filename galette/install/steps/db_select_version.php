<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, database upgrade previous version selection step
 *
 * PHP version 5
 *
 * Copyright © 2013-2021 The Galette Team
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
 * @copyright 2013-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8 - 2013-07-21
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;

$versions = $install->getScripts();
$current = $install->getCurrentVersion($zdb);
$raw_current = $zdb->getDbVersion(true);
$last = '0.00';
?>
    <form action="installer.php" method="post" class="ui form">
        <p class="ui blue message"><?php echo _T("Select your previous Galette version below, and then click next."); ?></p>
<?php
if (count($versions) == 0) {
    ?>
        <p class="ui red message"><?php echo _T("No update script found!"); ?></p>
<?php
    if ($raw_current === GALETTE_DB_VERSION) {
        ?>
            <p class="ui orange message"><?php echo _T("It seems you already use latest Galette version!"); ?></p>
        <?php
    }

} else {
    if ($current !== false) {
        if ($current < 0.70) {
        ?>
            <p class="ui orange message"><?php echo _T("Previous version is older than 0.7. <strong>Make sure you select the right version</strong>."); ?></p>
        <?php
        } else {
        ?>
            <p class="ui green message"><?php echo _T("Your previous version should be selected and <strong>displayed in bold</strong>."); ?></p>
        <?php
        }
    }

    if ($raw_current === GALETTE_DB_VERSION) {
        ?>
            <p class="ui orange message"><?php echo _T("It seems you already use latest Galette version!<br/>Are you sure you want to upgrade?"); ?></p>
        <?php
    }
    ?>
        <h2><?php echo _T("Your current Galette version is..."); ?></h2>

        <ul class="leaders">
    <?php
    $is_current = false;
    $previous = null;
    foreach ($versions as $version) {
        ?>
            <li>
        <?php
        if ($is_current) {
            echo '<strong>';
        }
        ?>
                <span>
                    <label for="upgrade-<?php echo $version; ?>">
        <?php
        if ($last === '0.00') {
            echo str_replace(
                '%version',
                number_format($version, 2),
                _T("older than %version")
            );
        } elseif ($last != number_format($version - 0.01, 2)) {
            echo _T("comprised between") . " " .
                $last . " " . _T("and") . " " . number_format($version - 0.01, 2);
        } else {
            echo " " . number_format($version - 0.01, 2);
        }
        $last = $version;
        ?>
                    </label>
                </span>
                <span>
                <input type="radio" name="previous_version" value="<?php echo $previous ?? $version; ?>" id="upgrade-<?php echo $version; ?>"<?php if ($is_current) { echo ' checked="checked"'; }; ?> required/>
                </span>
        <?php
        if ($is_current) {
            echo '</strong>';
        }
        $is_current = $current == $version;
        ?>

            </li>
    <?php
        $previous = $version;
    }
    ?>
        </ul>
    <?php
}
?>

        <div class="ui section divider"></div>

<?php
if (count($versions) == 0) {
?>
        <div class="ui equal width grid">
            <div class="column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double left icon"></i> <?php echo _T("Back"); ?></button>
            </div>
            <div class="right aligned column">
                <input type="submit" class="ui icon button" name="abort_btn" value="<?php echo _T("Cancel"); ?>"/>
                <button type="submit" class="ui right labeled icon button"><i class="angle double right icon"></i> <?php echo _T("Next step"); ?></button>
            </div>
        </div>
<?php
} else {
?>
        <div class="ui equal width grid">
            <div class="column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double left icon"></i> <?php echo _T("Back"); ?></button>
            </div>
            <div class="right aligned column">
                <button type="submit" class="ui right labeled icon button"><i class="angle double right icon"></i> <?php echo _T("Next step"); ?></button>
            </div>
        </div>
    </form>
<?php
}
?>
