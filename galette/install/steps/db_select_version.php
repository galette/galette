<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, database upgrade previous version selection step
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8 - 2013-07-21
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;

$versions = $install->getScripts();
$last = '0.00';
?>
            <h2><?php echo _T("Previous version selection"); ?></h2>
            <p><?php echo _T("Select your previous Galette version below, and then click next."); ?></p>
<?php
if ( count($versions) == 0 ) {
    ?>
            <p id="errorbox"><?php echo _T("No update script found!"); ?></p>
    <?php
} else {
    ?>
            <form action="installer.php" method="post">
                <fieldset class="cssform">
                    <legend class="ui-state-active ui-corner-top"><?php echo _T("You current Galette version is..."); ?></legend>
 
                    <ul class="leaders">
    <?php
    foreach ( $versions as $version ) {
        ?>
                    <li>
                        <span>
                            <label for="upgrade-<?php echo $version; ?>">
        <?php
        if ( $last != number_format($version - 0.01, 2) ) {
            echo _T("comprised between") . " " .
                $last . " " . _T("and") . " " . number_format($version - 0.01, 2);
        } else {
            echo " " . number_format($version - 0.01, 2);
        }
        $last = $version;
        ?>
                            </label>
                        </span>
                        <span>
                            <input type="radio" name="previous_version" value="<?php echo $version; ?>" id="upgrade-<?php echo $version; ?>" required/>
                        </span>
                    </li>
    <?php
    }
    ?>
                    </ul>
                </fieldset>
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="submit" id="btnback" name="stepback_btn" value="<?php echo _T("Back"); ?>" formnovalidate/>
                </p>
            </form>
    <?php
}
?>
