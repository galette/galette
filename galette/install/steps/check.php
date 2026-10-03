<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

?>
<?php
$php_ok = true;
$class = 'install-';
$files_perms_class = '';

/**
 * @var \Galette\Core\Install $install
 * @var \Galette\Core\I18n $i18n
 */

// check required PHP version...
if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<')) { //@phpstan-ignore if.alwaysFalse
    $php_ok = false;
}

// check date settings
$date_ok = false;
try {
    new \Safe\DateTime();
    $date_ok = true;
} catch (\Exception) { // @phpstan-ignore catch.neverThrown (can be thrown depending on PHP configuration)
    //do nothing
}

// check PHP modules
$cm = new Galette\Core\CheckModules();
$modules_ok = $cm->isValid();

// check file permissions
$perms_ok = true;
$files_need_rw = [
    _T("Photos")            => GALETTE_PHOTOS_PATH,
    _T("Cache")             => str_replace(GALETTE_VERSION, '', GALETTE_CACHE_DIR),
    _T("Temporary images")  => GALETTE_TEMPIMAGES_PATH,
    _T("Configuration")     => GALETTE_CONFIG_PATH,
    _T("Exports")           => GALETTE_EXPORTS_PATH,
    _T("Imports")           => GALETTE_IMPORTS_PATH,
    _T("Logs")              => GALETTE_LOGS_PATH,
    _T("Attachments")       => GALETTE_ATTACHMENTS_PATH,
    _T("Files")             => GALETTE_FILES_PATH
];

$files_perms_class = $class . 'ok';

foreach ($files_need_rw as $label => $file) {
    $writable = is_writable($file);
    if (!$writable) {
        $perms_ok = false;
    }
}
?>
    <h2><?php echo _T("Welcome to the Galette Install!"); ?></h2>
<?php
if ($perms_ok && $modules_ok && $php_ok && $date_ok) { // @phpstan-ignore booleanAnd.rightAlwaysTrue,booleanAnd.rightAlwaysTrue
    echo '<p class="ui green message">' . _T("Galette requirements are met :)") . '</p>';
}

if (!$date_ok) { // @phpstan-ignore booleanNot.alwaysFalse
    echo '<p class="ui red message">' . _T("Your PHP date settings are not correct. Maybe you've missed the timezone settings that is mandatory since PHP 5.3?") . '</p>';
}
?>
    <ul class="leaders">
        <li>
            <span><?php echo _T("PHP version"); ?> (<?php echo PHP_VERSION . ' >= ' . GALETTE_PHP_MIN; ?>)</span>
            <span><?php echo $install->getValidationImage($php_ok); ?></span>
        </li>
        <li>
            <span><?php echo _T("Date settings"); ?></span>
            <span><?php echo $install->getValidationImage($date_ok); ?></span>
        </li>
    </ul>

    <h3><?php echo _T("PHP Modules"); ?></h3>
<?php
if (!$modules_ok) {
    echo '<p class="ui red message">' . _T("Some PHP modules are missing. Please install them or contact your support.<br/>More information on required modules may be found in the documentation.") . '</p>';
}
?>
    <ul class="leaders">
        <?php echo $cm->toHtml(); ?>
    </ul>

    <h3><?php echo _T("Files permissions"); ?></h3>
    <ul class="leaders">
<?php
foreach ($files_need_rw as $label => $file) {
    ?>
        <li>
            <span><?php echo $label ?></span>
            <span><?php echo $install->getValidationImage(is_writable($file)); ?></span>
        </li>
    <?php
}
?>
    </ul>
<?php
if (!$perms_ok) {
    ?>
        <article id="files_perms" class="ui orange message <?php echo $files_perms_class; ?>">
            <p class="ui small header"><?php echo _T("Files permissions are not OK!"); ?></p>
            <p>
    <?php
    if ($install->isInstall()) {
        echo _T("To work as excpected, Galette needs write permission on files listed above.");
    } elseif ($install->isUpgrade()) {
        echo _T("In order to be updated, Galette needs write permission on files listed above.");
    }
    ?>
            </p>
            <p><?php echo _T("Under UNIX/Linux, you can give the permissions using those commands"); ?><br />
                <code>chown <em><?php echo _T("apache_user"); ?></em> <em><?php echo _T("file_name"); ?></em><br />chmod 700 <em><?php echo _T("directory_name"); ?></em></code>
            </p>
            <p><?php echo _T("Under Windows, check these directories are not in Read-Only mode in their property panel."); ?></p>
        </article>
    <?php
}
?>
    <div class="ui section divider"></div>
    <div class="ui equal width grid">
        <div class="right aligned column">
    <?php
    if (!$perms_ok || !$modules_ok || !$php_ok || !$date_ok) { // @phpstan-ignore booleanNot.alwaysFalse,booleanNot.alwaysFalse
        ?>
        <form action="installer.php" method="post" class="ui form">
            <button type="submit" class="ui right labeled primary icon button"><i class="sync alt icon" aria-hidden="true"></i> <?php echo _T("Retry"); ?></button>
        </form>
        <?php
    } else {
        ?>
        <form action="installer.php" method="POST" class="ui form">
            <button type="submit" class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
            <input type="hidden" name="install_permsok" value="1"/>
        </form>
        <?php
    } ?>
        </div>
    </div>
