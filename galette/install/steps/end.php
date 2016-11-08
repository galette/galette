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
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8 - 2013-01-09
 */

use Galette\Core\Install as GaletteInstall;

$session[md5(GALETTE_ROOT)] = null;
unset($session[md5(GALETTE_ROOT)]);
?>
                <h2><?php echo $install->getStepTitle(); ?></h2>
                <p><?php
if ( $install->isInstall() ) {
    echo _T("Galette has been successfully installed!");
}
if ( $install->isUpgrade() ) {
    echo _T("Galette has been successfully updated!");
}
?></p>
                <div id="errorbox"><?php echo _T("To secure the system, please delete the install directory"); ?></div>
                <form action="<?php echo GALETTE_BASE_PATH; ?>" method="get">
                    <p id="btn_box">
                        <input type="submit" id="backhome" class="button" value="<?php echo _T("Homepage"); ?>"/>
                    </p>
                </form>
