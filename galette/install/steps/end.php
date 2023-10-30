<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, da end :)
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

use Galette\Core\Install as GaletteInstall;

$session[md5(GALETTE_ROOT)] = null;
unset($session[md5(GALETTE_ROOT)]);
?>
    <p class="ui green message">
<?php
if ($install->isInstall()) {
    echo _T("Galette has been successfully installed!");
}
if ($install->isUpgrade()) {
    echo _T("Galette has been successfully updated!");
}
?>
    </p>

    <div class="ui section divider"></div>

    <form action="<?php echo GALETTE_BASE_PATH; ?>" method="get">
        <div class="ui equal width grid">
            <div class="right aligned column">
                <button type="submit" class="ui right labeled icon button"><i class="home icon"></i> <?php echo _T("Homepage"); ?></button>
            </div>
        </div>
    </form>
