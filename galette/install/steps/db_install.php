<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * @var \Galette\Core\Install $install
 * @var \Galette\Core\Db $zdb
 * @var \Galette\Core\I18n $i18n
 */

//ok, let's run the scripts!
$db_installed = $install->executeScripts($zdb);

if ($db_installed === false) {
    $msg = _T("Database has not been installed!");
    if ($install->isUpgrade()) {
        $msg = _T("Database has not been upgraded!");
    }
    echo '<p class="ui red message">' . $msg . '</p>';
} else {
    // early bypass
    //FIXME: breaks regular installer
    echo 'install_dbwrite_ok';
    return;
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
                <button type="submit" class="ui right labeled icon button"><i class="redo alternate double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Retry"); ?></button>
    <?php
}
?>
                <button type="submit" class="ui right labeled primary icon button"<?php echo !$db_installed ? ' disabled="disabled"' : ''; ?>><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
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
                    <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
                </div>
    <?php
}
?>
        </div>
    </form>
