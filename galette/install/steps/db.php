<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, database step
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
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8 - 2013-01-09
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
?>
                <h2><?php echo _T("Database"); ?></h2>
                <p>
<?php
if ($install->getMode() === GaletteInstall::INSTALL) {
    echo _T("If it hadn't been made, create a database and a user for Galette.");
}
if ($install->isUpgrade()) {
    echo _T("Enter connection data for the existing database.");
    $install->loadExistingConfig($_POST, $error_detected);
} else {
    if (file_exists(GALETTE_CONFIG_PATH . 'config.inc.php')) {
        echo '<div id="warningbox">' . _T("It seems that you have already installed Galette once.<br/>All existing data will be removed if you keep going on using existing database!") . "</div>";
    }
}

//define default database port
$default_dbport = GaletteDb::MYSQL_DEFAULT_PORT;
if (!isset($_POST['install_dbtype']) || $_POST['install_dbtype'] == 'mysql') {
    $default_dbport = GaletteDb::MYSQL_DEFAULT_PORT;
} elseif ($_POST['install_dbtype'] == 'pgsql') {
    $default_dbport = GaletteDb::PGSQL_DEFAULT_PORT;
}
?><br />
            <?php echo _T("The needed permissions are CREATE, DROP, DELETE, UPDATE, SELECT and INSERT."); ?></p>
            <form action="installer.php" method="post">
                <fieldset class="cssform">
                    <legend class="ui-state-active ui-corner-top"><?php echo _T("Database"); ?></legend>
                    <p>
                        <label class="bline" for="install_dbtype"><?php echo _T("Database type:"); ?></label>
                        <select name="install_dbtype" id="install_dbtype">
                            <option value="mysql"<?php if ($install->getDbType() === GaletteDb::MYSQL) {echo ' selected="selected"'; } ?>>Mysql</option>
                            <option value="pgsql"<?php if ($install->getDbType() === GaletteDb::PGSQL) {echo ' selected="selected"'; } ?>>Postgresql</option>
                        </select>
                    </p>
                    <div id="install_dbconfig">
                        <p>
                            <label class="bline" for="install_dbhost"><?php echo _T("Host:"); ?></label>
                            <input type="text" name="install_dbhost" id="install_dbhost" value="<?php echo ($install->getDbHost() !== null) ? $install->getDbHost() : 'localhost'; ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbport"><?php echo _T("Port:"); ?></label>
                            <input type="text" name="install_dbport" id="install_dbport" value="<?php echo ($install->getDbPort() !== null) ? $install->getDbPort() : $default_dbport; ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbuser"><?php echo _T("User:"); ?></label>
                            <input type="text" name="install_dbuser" id="install_dbuser" value="<?php echo $install->getDbUser(); ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbpass"><?php echo _T("Password:"); ?></label>
                            <input type="password" name="install_dbpass" id="install_dbpass" value="" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbname"><?php echo _T("Database:"); ?></label>
                            <input type="text" name="install_dbname" id="install_dbname" value="<?php echo $install->getDbName(); ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbprefix"><?php echo _T("Table prefix:"); ?></label>
                            <input type="text" name="install_dbprefix" id="install_dbprefix" value="<?php echo ($install->getTablesPrefix() !== null) ? $install->getTablesPrefix() : 'galette_'; ?>" required/>
                        </p>
<?php
if ($install->isUpgrade()) {
    echo '<div id="warningbox">' .
        _T("(Indicate the CURRENT prefix of your Galette tables)") .
        '</div>';
}
?>

                    </div>
                </fieldset>
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="submit" id="btnback" name="stepback_btn" value="<?php echo _T("Back"); ?>" formnovalidate/>
                </p>
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
            </script>
