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

?>
<?php
$php_ok = true;
$class = 'install-';
$php_modules_class = '';
$files_perms_class = '';

// check required PHP version...
if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<')) {
    $php_ok = false;
}

// check date settings
$date_ok = false;
if (!version_compare(PHP_VERSION, '5.2.0', '<')) {
    try {
        $test_date = new DateTime();
        $date_ok = true;
    } catch (Throwable $e) {
        //do nothing
    }
}

// check PHP modules
$cm = new Galette\Core\CheckModules();
$modules_ok = $cm->isValid();

// check file permissions
$perms_ok = true;
$files_need_rw = array(
    _T("Compilation")       => GALETTE_COMPILE_DIR,
    _T("Photos")            => GALETTE_PHOTOS_PATH,
    _T("Cache")             => GALETTE_CACHE_DIR,
    _T("Temporary images")   => GALETTE_TEMPIMAGES_PATH,
    _T("Configuration")     => GALETTE_CONFIG_PATH,
    _T("Exports")           => GALETTE_EXPORTS_PATH,
    _T("Imports")           => GALETTE_IMPORTS_PATH,
    _T("Logs")              => GALETTE_LOGS_PATH,
    _T("Attachments")       => GALETTE_ATTACHMENTS_PATH,
    _T("Files")             => GALETTE_FILES_PATH
);

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
if ($perms_ok && $modules_ok && $php_ok && $date_ok) {
    echo '<p class="ui green message">' . _T("Galette requirements are met :)") . '</p>';
}

if (!$date_ok) {
    echo '<p class="ui red message">' . _T("Your PHP date settings are not correct. Maybe you've missed the timezone settings that is mandatory since PHP 5.3?") . '</p>';
}
?>
    <ul class="leaders">
        <li>
            <span><?php echo _T("PHP version"); ?> (<?php echo PHP_VERSION . ' >= ' . GALETTE_PHP_MIN; ?>)</span>
            <span><?php echo $install->getValidationImage($php_ok == true); ?></span>
        </li>
        <li>
            <span><?php echo _T("Date settings"); ?></span>
            <span><?php echo $install->getValidationImage($date_ok == true); ?></span>
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
    $writable = is_writable($file);
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
    } else if ($install->isUpgrade()) {
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
if (!$perms_ok || !$modules_ok || !$php_ok || !$date_ok) {
    ?>
        <form action="installer.php" method="post" class="ui form">
            <button type="submit" class="ui right labeled primary icon button"><i class="sync alt icon" aria-hidden="true"></i> <?php echo _T("Retry"); ?></button>
        </form>
    <?php
} else {
    ?>
        <form action="installer.php" method="POST" class="ui form">
            <button type="submit" class="ui right labeled primary icon button"><i class="angle double right icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
            <input type="hidden" name="install_permsok" value="1"/>
        </form>
    <?php
}
    ?>
        </div>
    </div>
