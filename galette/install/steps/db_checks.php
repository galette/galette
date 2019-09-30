<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, database checks step
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
 * @since     Available since 0.8 - 2013-01-11
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;

$db_connected = $install->testDbConnexion();
$conndb_ok = true;
$permsdb_ok = true;

if (!isset($show_form)) {
    $show_form = true;
}

if ($db_connected === true) {
    if (!isset($zdb)) {
        $zdb = new GaletteDb();
    }

    /** FIXME: when tables already exists and DROP not allowed at this time
    the showed error is about CREATE, whenever CREATE is allowed */
    //We delete the table if exists, no error at this time
    $zdb->dropTestTable();

    $results = $zdb->grantCheck($install->getMode());

    $result = array();
    $error = false;

    //test returned values
    if ($results['create'] instanceof Exception) {
        $result[] = array(
            'message'   => _T("CREATE operation not allowed"),
            'debug'     => $results['create']->getMessage(),
            'res'       => false
        );
        $error = true;
    } elseif ($results['create'] != '') {
        $result[] = array(
            'message'   => _T("CREATE operation allowed"),
            'res'       => true
        );
    }

    if ($results['insert'] instanceof Exception) {
        $result[] = array(
            'message'   => _T("INSERT operation not allowed"),
            'debug'     => $results['insert']->getMessage(),
            'res'       => false
        );
        $error = true;
    } elseif ($results['insert'] != '') {
        $result[] = array(
            'message'   => _T("INSERT operation allowed"),
            'res'       => true
        );
    }

    if ($results['update'] instanceof Exception) {
        $result[] = array(
            'message'   => _T("UPDATE operation not allowed"),
            'debug'     => $results['update']->getMessage(),
            'res'       => false
        );
        $error = true;
    } elseif ($results['update'] != '') {
        $result[] = array(
            'message'   => _T("UPDATE operation allowed"),
            'res'       => true
        );
    }

    if ($results['select'] instanceof Exception) {
        $result[] = array(
            'message'   => _T("SELECT operation not allowed"),
            'debug'     => $results['select']->getMessage(),
            'res'       => false
        );
        $error = true;
    } elseif ($results['select'] != '') {
        $result[] = array(
            'message'   => _T("SELECT operation allowed"),
            'res'       => true
        );
    }

    if ($results['delete'] instanceof Exception) {
        $result[] = array(
            'message'   => _T("DELETE operation not allowed"),
            'debug'     => $results['delete']->getMessage(),
            'res'       => false
        );
        $error = true;
    } elseif ($results['delete'] != '') {
        $result[] = array(
            'message'   => _T("DELETE operation allowed"),
            'res'       => true
        );
    }

    if ($results['drop'] instanceof Exception) {
        $result[] = array(
            'message'   => _T("DROP operation not allowed"),
            'debug'     => $results['drop']->getMessage(),
            'res'       => false
        );
        $error = true;
    } elseif ($results['drop'] != '') {
        $result[] = array(
            'message'   => _T("DROP operation allowed"),
            'res'       => true
        );
    }

    if ($install->isUpgrade()) {
        if ($results['alter'] instanceof Exception) {
            $result[] = array(
                'message'   => _T("ALTER operation not allowed"),
                'debug'     => $results['alter']->getMessage(),
                'res'       => false
            );
            $error = true;
        } elseif ($results['alter'] != '') {
            $result[] = array(
                'message'   => _T("ALTER operation allowed"),
                'res'       => true
            );
        }
    }

    if ($error) {
        $permsdb_ok = false;
    }
}
?>
    <div class="ui segment">
        <div class="content field">
            <div class="ui text container">


<?php
if ($db_connected === true && $permsdb_ok === true) {
    if (!isset($install_plugin)) {
        echo '<p class="ui green message">' . _T("Connection to database successfull") .
            '<br/>' . _T("Permissions to database are OK.") . '</p>';
    } else {
         echo '<p class="ui green message">' . _T("Permissions to database are OK.") . '</p>';
    }
}

if (!isset($install_plugin)) {
?>
                <h2><?php echo _T("Check of the database"); ?></h2>
<?php
}

if ($db_connected !== true) {
    $conndb_ok = false;
    echo '<div class="ui red message">';
    echo '<h1>' . _T("Unable to connect to the database") . '</h1>';
    echo '<p class="debuginfos">' . $db_connected->getMessage() . '<span>' .
        $db_connected->getTraceAsString() . '</span></p>';
    echo '</div>';
}

if (!$conndb_ok) {
    ?>
                <p><?php echo _T("Database can't be reached. Please go back to enter the connection parameters again."); ?></p>
    <?php
} else {
    if (!isset($install_plugin)) {
    ?>
                <p><?php echo _T("Database exists and connection parameters are OK."); ?></p>
                <h2><?php echo _T("Permissions on the base"); ?></h2>
    <?php
    }
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
        }
        ?>
                </ul>
            </div>
        </div>
    </div>
        <?php
}

if (!isset($install_plugin)) {
?>
    <form action="installer.php" method="POST" class="ui form">
        <div class="ui mobile tablet computer reversed equal width grid">
            <div class="right aligned column">
                <button type="submit"<?php if (!$conndb_ok || !$permsdb_ok) { echo ' disabled="disabled"'; } ?> class="ui right labeled icon button"><i class="angle double right icon"></i> <?php echo _T("Next step"); ?></button>
<?php
if ($conndb_ok && $permsdb_ok) {
?>

                <input type="hidden" name="install_dbperms_ok" value="1"/>
<?php
}
?>
            </div>
            <div class="left aligned column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double left icon"></i> <?php echo _T("Back"); ?></button>
            </div>
        </div>
    </form>
<?php
}
?>
