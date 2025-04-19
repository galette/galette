<?php
/**
 * Copyright © 2003-2025 The Galette Team
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

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;

try {
    $db_connected = $install->testDbConnexion();
} catch (Throwable $e) {
    $db_connected = $e;
}
$conndb_ok = true;
$permsdb_ok = true;
$supported_db = true;

if (!isset($show_form)) {
    $show_form = true;
}

if ($db_connected === true) {
    if (!isset($zdb)) {
        $zdb = new GaletteDb();
    }

    if (!$zdb->isEngineSUpported()) {
        $supported_db = false;
    }

    if ($supported_db) {
        /** FIXME: when tables already exists and DROP not allowed at this time
         * the showed error is about CREATE, whenever CREATE is allowed */
        //We delete the table if exists, no error at this time
        $zdb->dropTestTable();

        $results = $zdb->grantCheck($install->getMode());

        $result = array();
        $error = false;

        //test returned values
        if ($results['create'] instanceof Exception) {
            $result[] = array(
                'message' => _T("CREATE operation not allowed"),
                'debug' => $results['create']->getMessage(),
                'res' => false
            );
            $error = true;
        } elseif ($results['create'] != '') {
            $result[] = array(
                'message' => _T("CREATE operation allowed"),
                'res' => true
            );
        }

        if ($results['insert'] instanceof Exception) {
            $result[] = array(
                'message' => _T("INSERT operation not allowed"),
                'debug' => $results['insert']->getMessage(),
                'res' => false
            );
            $error = true;
        } elseif ($results['insert'] != '') {
            $result[] = array(
                'message' => _T("INSERT operation allowed"),
                'res' => true
            );
        }

        if ($results['update'] instanceof Exception) {
            $result[] = array(
                'message' => _T("UPDATE operation not allowed"),
                'debug' => $results['update']->getMessage(),
                'res' => false
            );
            $error = true;
        } elseif ($results['update'] != '') {
            $result[] = array(
                'message' => _T("UPDATE operation allowed"),
                'res' => true
            );
        }

        if ($results['select'] instanceof Exception) {
            $result[] = array(
                'message' => _T("SELECT operation not allowed"),
                'debug' => $results['select']->getMessage(),
                'res' => false
            );
            $error = true;
        } elseif ($results['select'] != '') {
            $result[] = array(
                'message' => _T("SELECT operation allowed"),
                'res' => true
            );
        }

        if ($results['delete'] instanceof Exception) {
            $result[] = array(
                'message' => _T("DELETE operation not allowed"),
                'debug' => $results['delete']->getMessage(),
                'res' => false
            );
            $error = true;
        } elseif ($results['delete'] != '') {
            $result[] = array(
                'message' => _T("DELETE operation allowed"),
                'res' => true
            );
        }

        if ($results['drop'] instanceof Exception) {
            $result[] = array(
                'message' => _T("DROP operation not allowed"),
                'debug' => $results['drop']->getMessage(),
                'res' => false
            );
            $error = true;
        } elseif ($results['drop'] != '') {
            $result[] = array(
                'message' => _T("DROP operation allowed"),
                'res' => true
            );
        }

        if ($install->isUpgrade()) {
            if ($results['alter'] instanceof Exception) {
                $result[] = array(
                    'message' => _T("ALTER operation not allowed"),
                    'debug' => $results['alter']->getMessage(),
                    'res' => false
                );
                $error = true;
            } elseif ($results['alter'] != '') {
                $result[] = array(
                    'message' => _T("ALTER operation allowed"),
                    'res' => true
                );
            }
        }

        if ($error) {
            $permsdb_ok = false;
        }
    }
}
?>

<?php
if (!isset($install_plugin)) {
?>
    <h2><?php echo _T("Check of the database"); ?></h2>
<?php
    echo '<p>' . _T("Database exists and connection parameters are OK.") . '</p>';
}

if ($supported_db === false) {
    echo '<p class="ui red message">' . _T("Incompatible database version.") .
        '<br/>' . $zdb->getUnsupportedMessage() . '</p>';
} elseif ($db_connected === true && $permsdb_ok === true) {
    if (!isset($install_plugin)) {
        echo '<p class="ui green message">' . _T("Connection to database successfull") .
            '<br/>' . _T("Permissions to database are OK.") . '</p>';

    } else {
        echo '<p class="ui green message">' . _T("Permissions to database are OK.") . '</p>';
    }
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
    if (!isset($install_plugin)) {
    ?>
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
        <?php
}
?>

    <div class="ui section divider"></div>

<?php
if (!isset($install_plugin)) {
?>
    <form action="installer.php" method="POST" class="ui form">
        <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
            <div class="right aligned column">
                <button type="submit"<?php if (!$conndb_ok || !$permsdb_ok) { echo ' disabled="disabled"'; } ?> class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
<?php
if ($conndb_ok && $permsdb_ok) {
?>

                <input type="hidden" name="install_dbperms_ok" value="1"/>
<?php
}
?>
            </div>
            <div class="left aligned column">
                <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
            </div>
        </div>
    </form>
<?php
}
?>
