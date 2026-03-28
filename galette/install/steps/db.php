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

/**
 * @var GaletteInstall $install
 * @var \Galette\Core\I18n $i18n
 */
?>
    <form action="installer.php" method="post" class="ui form">
<?php
echo '<div class="ui blue message">';
if ($install->getMode() === GaletteInstall::INSTALL) {
    echo '<p>' . _T("If it hadn't been made, create a database and a user for Galette.") . '</p>';
}
echo '<p>' . _T("The needed permissions are CREATE, DROP, DELETE, UPDATE, SELECT and INSERT.") . '</p></div>';
if ($install->configurationFileExists() && !$install->isUpgrade()) {
    echo '<div class="ui orange message"><p>' . _T("It seems that you have already installed Galette once.<br/>All existing data will be removed if you keep going on using existing database!") . '</p></div>';
}

//define default database port
$default_dbport = $install->getDbType() === GaletteDb::PGSQL ? GaletteDb::PGSQL_DEFAULT_PORT : GaletteDb::MYSQL_DEFAULT_PORT;
?>
<?php
if ($install->configurationFileExists() && $install->isUpgrade()) {
    ?>
        <div class="ui large buttons config_choice">
            <a href="#" class="existing_config ui<?php echo ($_POST['config_choice'] ?? 'existing_config') === 'existing_config' ? ' positive' : ''; ?> button">
                Use the existing configuration file
            </a>
            <div class="or" data-text="<?php echo _T("or"); ?>"></div>
            <a href="#" class="new_config ui<?php echo ($_POST['config_choice'] ?? '') === 'new_config' ? ' positive' : ''; ?> button">
                Enter your configuration
            </a>
        </div>
        <div class="ui tab basic fitted segment<?php echo ($_POST['config_choice'] ?? 'existing_config') === 'existing_config' ? ' active' : ''; ?>" data-tab="existing">
            <div class="ui blue message">
                <p><?php echo _T("Use your superadmin credentials to retrieve database connection values from existing configuration file."); ?></p>
            </div>
            <div class="field">
                <div class="ui left icon input">
                    <i class="user icon" aria-hidden="true"></i><label for="login" class="visually-hidden"><?php echo _T("Username:"); ?></label>
                    <input type="text" name="login" id="login" placeholder="<?php echo _T("Username:"); ?>" required="required"/>
                </div>
            </div>
            <div class="field">
                <div class="ui left icon input">
                    <i class="lock icon" aria-hidden="true"></i><label for="password" class="visually-hidden"><?php echo _T("Password:"); ?></label>
                    <input type="password" name="password" id="password" placeholder="<?php echo _T("Password:"); ?>" required="required"/>
                </div>
            </div>
            <input type="hidden" name="ident" value="1" />
        </div>
    <?php
}
?>
        <div class="ui tab basic fitted segment<?php echo ($_POST['config_choice'] ?? '') === 'new_config' || !$install->isUpgrade() ? ' active' : ''; ?>" data-tab="new">
<?php
if ($install->configurationFileExists() && $install->isUpgrade()) {
?>
            <div class="ui blue message">
                <p>
                    <?php echo _T("Enter connection data for the existing database."); ?>
                </p>
            </div>
            <div id="install_dbconfig">
<?php
}
?>
        <div class="inline required field">
            <label for="install_dbtype"><?php echo _T("Database type:"); ?></label>
            <select name="install_dbtype" id="install_dbtype" class="ui dropdown nochosen"<?php echo $install->isUpgrade() ? ' disabled="disabled"' : ''; ?>>
                <option value="mysql"<?php echo $install->getDbType() === GaletteDb::MYSQL ? ' selected="selected"' : ''; ?>>Mysql</option>
                <option value="pgsql"<?php echo $install->getDbType() === GaletteDb::PGSQL ? ' selected="selected"' : ''; ?>>Postgresql</option>
            </select>
        </div>
            <div class="inline required field">
                <label for="install_dbhost"><?php echo _T("Host:"); ?></label>
                <input type="text" name="install_dbhost" id="install_dbhost" value="<?php echo htmlspecialchars($_POST['install_dbhost'] ?? $install->getDbHost() ?? $install->isUpgrade() ? '' : 'localhost', ENT_QUOTES, 'UTF-8'); ?>" placeholder="localhost" required/>
            </div>
            <div class="inline required field">
                <label for="install_dbport"><?php echo _T("Port:"); ?></label>
                <input type="text" name="install_dbport" id="install_dbport" value="<?php echo (int)($_POST['install_dbport'] ?? $install->getDbPort() ?? $install->isUpgrade() ? '' : $default_dbport); ?>" placeholder="<?php echo $default_dbport; ?>" required/>
            </div>
            <div class="inline required field">
                <label for="install_dbuser"><?php echo _T("User:"); ?></label>
                <input type="text" name="install_dbuser" id="install_dbuser" value="<?php echo htmlspecialchars($_POST['install_dbuser'] ?? $install->getDbUser() ?? '', ENT_QUOTES, 'UTF-8'); ?>" required/>
            </div>
            <div class="inline required field">
                <label for="install_dbpass"><?php echo _T("Password:"); ?></label>
                <input type="password" name="install_dbpass" id="install_dbpass" value="" required/>
            </div>
            <div class="inline required field">
                <label for="install_dbname"><?php echo _T("Database:"); ?></label>
                <input type="text" name="install_dbname" id="install_dbname" value="<?php echo htmlspecialchars($_POST['install_dbname'] ?? $install->getDbName() ?? '', ENT_QUOTES, 'UTF-8'); ?>" required/>
            </div>
            <div class="inline required field">
                <label for="install_dbprefix"><?php echo _T("Table prefix:"); ?></label>
                <input type="text" name="install_dbprefix" id="install_dbprefix" value="<?php echo $install->getTablesPrefix() ?? 'galette_'; ?>" required<?php echo $install->isUpgrade() ? ' disabled="disabled"' : ''; ?>/>
            </div>
        </div>
        </div>
        <div class="ui section divider"></div>
        <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
            <div class="right aligned column">
                <button type="submit" class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
            </div>
            <div class="left aligned column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
            </div>
<?php
if ($install->configurationFileExists() && $install->isUpgrade()) {
?>
            <input type="hidden" name="config_choice" value="<?php echo ($_POST['config_choice'] ?? 'existing_config') === 'existing_config' ? 'existing_config' : 'new_config'; ?>" />
<?php
}
?>
        </div>
    </form>
    <script type="text/javascript">
        $(function(){
            $('#install_dbtype').change(function(){
                var _db = $(this).val();
                var _port = null;
                if ( _db === 'pgsql' ) {
                    _port = <?php echo GaletteDb::PGSQL_DEFAULT_PORT; ?>;
                } else if ( _db === 'mysql' ) {
                    _port = <?php echo GaletteDb::MYSQL_DEFAULT_PORT; ?>;
                }
                $('#install_dbport').val(_port);
            });
        });

        /* Conditional buttons on db upgrade, Manage required fields */
        const _existing_config = $('a.existing_config');
        const _new_config = $('a.new_config');

        if (_existing_config || _new_config) {
            const _input_choice = $('input[name="config_choice"]');
            let _current_tab = $('.ui.tab.active').data('tab');
            let _inactive_tab = _current_tab === 'existing' ? 'new' : 'existing';

            const _disableRequiredFields = function (tab) {
                $('.ui.tab[data-tab="' + tab + '"] input, .ui.tab[data-tab="' + tab + '"] select').prop('required', false);
            };

            const _toggleRequiredFields = function (active_tab) {
                const inactive_tab = active_tab === 'existing' ? 'new' : 'existing';
                $('.ui.tab[data-tab="' + active_tab + '"] input, .ui.tab[data-tab="' + active_tab + '"] select').prop('required', true);
                _disableRequiredFields(inactive_tab);
                $('input[name="config_choice"]').val(active_tab + '_config'),
                _current_tab = active_tab;
            };

            const _handleConfigClick = function (targetTab, activeConfig, inactiveConfig) {
                return function(event) {
                    event.preventDefault();
                    $.tab('change tab', targetTab);
                    _toggleRequiredFields(targetTab);
                    activeConfig.addClass('positive');
                    activeConfig.blur();
                    inactiveConfig.removeClass('positive');
                };
            };

            $(document).ready(function() {
                _disableRequiredFields(_inactive_tab);
            });

            _existing_config.on('click', _handleConfigClick('existing', _existing_config, _new_config));
            _new_config.on('click', _handleConfigClick('new', _new_config, _existing_config));
        }
    </script>
