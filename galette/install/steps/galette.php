<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
use Galette\Core\Login;

/**
 * @var GaletteInstall $install
 * @var GaletteDb $zdb
 * @var \Galette\Core\I18n $i18n
 */

$install->reinitReport();

$config_file_ok = $install->writeConfFile();
$objects_ok = $install->initObjects($i18n, $zdb, new Login($zdb, $i18n));

if ($config_file_ok === true && $objects_ok === true) {
    echo '<p class="ui green message">' . _T("Configuration file created!")
        . '<br/>' . _T("Data initialized.") . '</p>';
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
                <button type="submit" class="ui right labeled icon button"><i class="redo alternate double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Retry"); ?></button>
    <?php
}
?>
                <button type="submit" class="ui right labeled primary icon button"<?php echo (!$config_file_ok || !$objects_ok) ? ' disabled="disabled"' : ''; ?>><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
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
                    <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
                </div>
                <?php
}
?>
        </div>
    </form>
