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

$versions = $install->getScripts();
$current = $install->getCurrentVersion($zdb);
$raw_current = $zdb->getDbVersion(true);
$last = '0.00';
?>
    <form action="installer.php" method="post" class="ui form">
<?php
if ($raw_current === GALETTE_DB_VERSION && !isset($_POST['force_select_version'])) {
    ?>
    <div class="ui orange message">
        <div class="header">
            <?php echo _T("It seems you already use latest Galette version!"); ?>
        </div>
        <p>
            <?php echo _T("Are you sure you want to upgrade?"); ?>
        </p>
        <p class="center aligned">
            <a  class="ui primary button" href="<?php echo GALETTE_BASE_PATH; ?>"><?php echo _T('Home'); ?></a>
            <button class="ui button" type="submit" name="force_select_version"><?php echo _T('Update'); ?></button>
        </p>
    </div>
    <?php
} else {
    if ($raw_current === GALETTE_DB_VERSION) {
        ?>
    <p class="ui orange message"><?php echo _T("It seems you already use latest Galette version!"); ?></p>
        <?php
    }
?>
        <p class="ui blue message"><?php echo _T("Select your previous Galette version below, and then click next."); ?></p>
<?php
if (count($versions) == 0) {
    ?>
        <p class="ui red message"><?php echo _T("No update script found!"); ?></p>
<?php
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
                <span class="ui radio checkbox">
                    <input type="radio" name="previous_version" value="<?php echo $previous ?? 0; ?>" id="upgrade-<?php echo $version; ?>"<?php if ($is_current) { echo ' checked="checked"'; } ?> required/>
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
        <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
            <div class="right aligned column">
                <input type="submit" class="ui icon button" name="abort_btn" value="<?php echo _T("Cancel"); ?>"/>
                <button type="submit" class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
            </div>
            <div class="left aligned column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php if ($i18n->isRtl()) { ?>right<?php } else { ?>left<?php } ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
            </div>
        </div>
<?php
} else {
?>
        <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
            <div class="right aligned column">
                <button type="submit" class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
            </div>
            <div class="left aligned column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
            </div>
        </div>
    </form>
<?php
}
}
?>
