<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

declare(strict_types=1);

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
use Galette\Core\Login;

/**
 * @var GaletteInstall $install
 * @var GaletteDb $zdb
 * @var \Galette\Core\I18n $i18n
 * @var array<string, mixed> $session
 */

// Perform initialization (config file + objects)
$install->reinitReport();

$config_file_ok = $install->writeConfFile();
$objects_ok = $install->initObjects($i18n, $zdb, new Login($zdb, $i18n));

$init_ok = ($config_file_ok === true && $objects_ok === true);

// Clear session only if initialization succeeded
if ($init_ok) {
    $session[md5(GALETTE_ROOT)] = null;
    unset($session[md5(GALETTE_ROOT)]);
}
?>

<?php if ($init_ok): ?>
    <p class="ui green message">
        <i class="big green check circle icon" aria-hidden="true"></i>
    <?php
    if ($install->isInstall()) {
        echo _T("Galette has been successfully installed!");
    }
    if ($install->isUpgrade()) {
        echo _T("Galette has been successfully updated!");
    }
    ?>
    </p>

    <ul class="leaders">
    <?php foreach ($install->getInitializationReport() as $r): ?>
        <li>
            <span><?php echo $r['message']; ?></span>
            <span><?php echo $install->getValidationImage($r['res']); ?></span>
        </li>
    <?php endforeach; ?>
    </ul>

    <div class="ui section divider"></div>

    <form action="<?php echo GALETTE_BASE_PATH; ?>" method="get">
        <div class="ui equal width grid">
            <div class="right aligned column">
                <button type="submit" class="ui right labeled primary icon button"><i class="home icon" aria-hidden="true"></i> <?php echo _T("Homepage"); ?></button>
            </div>
        </div>
    </form>

<?php else: ?>
    <p class="ui red message"><?php echo _T("An error occurred :("); ?></p>

    <ul class="leaders">
    <?php foreach ($install->getInitializationReport() as $r): ?>
        <li>
            <span><?php echo $r['message']; ?></span>
            <span><?php echo $install->getValidationImage($r['res']); ?></span>
        </li>
    <?php endforeach; ?>
    </ul>

    <div class="ui section divider"></div>

    <form action="installer.php" method="POST" class="ui form">
        <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
            <div class="right aligned column">
                <button type="submit" class="ui right labeled icon button"><i class="redo alternate icon" aria-hidden="true"></i> <?php echo _T("Retry"); ?></button>
            </div>
            <div class="left aligned column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
            </div>
        </div>
    </form>
<?php endif; ?>


