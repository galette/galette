<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, installation type step
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.8 - 2013-01-09
 */

use Galette\Core\Install as GaletteInstall;
?>
<form action="installer.php" method="POST" class="ui form">
    <div class="ui two stackable cards">
            <div class="ui fluid card">
                <div class="content">
                    <h2>
                        <input type="radio" name="install_type" value="<?php echo GaletteInstall::INSTALL; ?>"<?php if ($install->isInstall() || !$install->isUpgrade()) { echo ' checked="checked"'; } ?> id="install"/>
                        <label for="install"><?php echo _T("New installation"); ?></label>
                    </h2>
                    <ul>
                        <li><?php echo _T("you're installing Galette for the first time"); ?>,</li>
                        <li><?php echo _T("you wish to erase an older version of Galette without keeping your data"); ?>.</li>
                    </ul>
                </div>
            </div>
            <div class="ui fluid card">
                <div class="content">
                    <h2>
                        <input type="radio" name="install_type" value="<?php echo GaletteInstall::UPDATE; ?>"<?php if ($install->isUpgrade()) { echo ' checked="checked"'; } ?> id="update"/>
                        <label for="update"><?php echo _T("Update"); ?></label>
                    </h2>
                    <ul>
                    <li><?php echo _T("you already have installed Galette, and you want to upgrade to the latest version"); ?>.</li>
                    </ul>
                    <p class="ui orange message"><?php echo _T("Warning: Don't forget to backup your current database."); ?></p>
                </div>
            </div>
    </div>
    <div class="ui section divider"></div>
    <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
        <div class="right aligned column">
            <button type="submit" class="ui right labeled primary icon button"><i class="angle double right icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
        </div>
        <div class="left aligned column">
            <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double left icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
        </div>
    </div>
</form>
