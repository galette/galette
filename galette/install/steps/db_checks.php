<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Db as GaletteDb;

/**
 * @var \Galette\Core\Install $install
 * @var \Galette\Core\I18n $i18n
 */

try {
    $db_connected = $install->testDbConnexion();
} catch (Throwable $e) {
    $db_connected = $e;
}
$conndb_ok = true;
$permsdb_ok = true;
$supported_db = true;
$result = [];

if ($db_connected === true) {
    $zdb ??= new GaletteDb();
    $db_checks = $install->checkDbPermissions($zdb);
    $supported_db = $db_checks['supported'];
    $permsdb_ok = $db_checks['ok'];
    $result = $db_checks['checks'];
}
?>
    <h2><?php echo _T("Check of the database"); ?></h2>
<?php
echo '<p>' . _T("Database exists and connection parameters are OK.") . '</p>';

if ($supported_db === false) {
    echo '<p class="ui red message">' . _T("Incompatible database version.")
        . '<br/>' . $zdb->getUnsupportedMessage() . '</p>'; // @phpstan-ignore variable.undefined
} elseif ($db_connected === true && $permsdb_ok === true) {
    echo '<p class="ui green message">' . _T("Connection to database successfull")
        . '<br/>' . _T("Permissions to database are OK.") . '</p>';
}
if ($db_connected !== true) {
    $conndb_ok = false;
    echo '<div class="ui red message">';
    echo '<div class="ui small header">' . _T("Unable to connect to the database") . '</div>';
    echo '<p>' . $db_connected->getMessage() . '</p>';
    echo '</div>';
}

if (!$conndb_ok) {
    ?>
        <p><?php echo _T("Database can't be reached. Please go back to enter the connection parameters again."); ?></p>
    <?php
} elseif ($supported_db === true) {
    ?>
        <h2><?php echo _T("Permissions on the base"); ?></h2>
    <?php
    if (!$permsdb_ok) {
        echo '<div class="ui red message">';
        echo '<h1>';
        if ($install->isInstall()) {
            echo _T("GALETTE hasn't got enough permissions on the database to continue the installation.");
        } elseif ($install->isUpgrade()) {
            echo _T("GALETTE hasn't got enough permissions on the database to continue the update.");
        }
        echo '</h1>';
        echo '</div>';
    }
    ?>
        <ul class="leaders">
        <?php
        foreach ($result as $r) {
            ?>
            <li>
                <span><?php echo $r['message'] ?></span>
                <span><?php echo $install->getValidationImage($r['res']); ?></span>
            </li>
            <?php
        } ?>
        </ul>
        <?php
}
?>

    <div class="ui section divider"></div>

    <form action="installer.php" method="POST" class="ui form">
        <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
            <div class="right aligned column">
                <button type="submit"<?php echo (!$conndb_ok || !$permsdb_ok) ? ' disabled="disabled"' : ''; ?> class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
                <?php echo ($conndb_ok && $permsdb_ok) ? '<input type="hidden" name="install_dbperms_ok" value="1"/>' : ''; ?>
            </div>
            <div class="left aligned column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
            </div>
        </div>
    </form>
