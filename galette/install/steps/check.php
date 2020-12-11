<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, check step
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
 * @since     Available since 0.8 - 2013-01-09
 */

?>
                <h2><?php echo _T("Welcome to the Galette Install!"); ?></h2>
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

if ($perms_ok && $modules_ok && $php_ok && $date_ok) {
    echo '<p id="infobox">' . _T("Galette requirements are met :)") . '</p>';
}

if (!$date_ok) {
    echo '<p class="error">' . _T("Your PHP date settings are not correct. Maybe you've missed the timezone settings that is mandatory since PHP 5.3?") . '</p>';
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
    echo '<p class="error">' . _T("Some PHP modules are missing. Please install them or contact your support.<br/>More information on required modules may be found in the documentation.") . '</p>';
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
        <article id="files_perms" class="<?php echo $files_perms_class; ?>">
            <div>
        <h4 class="error"><?php echo _T("Files permissions are not OK!"); ?></h4>
        <p><?php
    if ($install->isInstall()) {
        echo _T("To work as excpected, Galette needs write permission on files listed above.");
    } else if ($install->isUpgrade()) {
        echo _T("In order to be updated, Galette needs write permission on files listed above.");
    }
        ?></p>
        <p><?php echo _T("Under UNIX/Linux, you can give the permissions using those commands"); ?><br />
            <code>chown <em><?php echo _T("apache_user"); ?></em> <em><?php echo _T("file_name"); ?></em><br />chmod 700 <em><?php echo _T("directory_name"); ?></em></code>
        </p>
        <p><?php echo _T("Under Windows, check these directories are not in Read-Only mode in their property panel."); ?></p>
            </div>
        </article>
    <?php
}

if (!$perms_ok || !$modules_ok || !$php_ok || !$date_ok) {
    ?>
                <form action="installer.php" method="post">
                    <p id="btn_box">
                        <button type="submit"><?php echo _T("Retry"); ?> <i class="fas fa-sync-alt"></i></button>
                    </p>
                </form>
    <?php
} else {
    ?>
        <form action="installer.php" method="POST">
            <p id="btn_box">
                <button type="submit"><?php echo _T("Next step"); ?> <i class="fas fa-forward"></i></button>
                <input type="hidden" name="install_permsok" value="1"/>
            </p>
        </form>
    <?php
}
