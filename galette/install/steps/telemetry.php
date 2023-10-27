<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, telemetry
 *
 * PHP version 5
 *
 * Copyright © 2023 The Galette Team
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
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2023-10-27
 */

use Galette\Core\Plugins;
use Galette\Core\Preferences;

$preferences = new Preferences($zdb);
$plugins = new Plugins();
$telemetry = new \Galette\Util\Telemetry(
    $zdb,
    $preferences,
    $plugins
);
?>
                <form action="installer.php" method="POST" class="ui form">
                    <div class="ui segment">
                        <div class="content field">
                            <div class="ui text container">
                                <div class="ui toggle checkbox tooltip" title="<?php echo _T("Send anonymous and imprecise data about your Galette instance"); ?>">
                                    <input type="checkbox" name="send_telemetry" tabindex="0" class="hidden" checked="checked"/>
                                    <label><?php echo _T("Send telemetry information"); ?></label>
                                </div>
<?php
if (!$telemetry->isRegistered()) {
?>
                                <a class="ui button right floated" href="<?php echo GALETTE_TELEMETRY_URI; ?>reference?showmodal&uuid=<?php echo $telemetry->getRegistrationUuid(); ?>" title="<?php echo _T("Register your organization as a Galette user"); ?>" target="_blank">
                                    <i class="id card icon"></i>
                                    <?php echo _T("Register"); ?>
                                </a>
<?php
}
?>
                                <div class="ui message scrolling content">
                                    <p><?php echo _T("Telemetry data are <strong>anonymous</strong>; nothing about your organization or its members will be sent."); ?></p>
                                    <p>
                                        <?php echo _T("Also note tha all data is sent over a <strong>HTTPS secured connection</strong>."); ?>
                                    </p>
                                    <div class="tdata">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ui mobile tablet computer reversed equal width grid">
                        <div class="right aligned column">
                            <button type="submit" class="ui right labeled icon button"><i class="angle double right icon"></i> <?php echo _T("Next step"); ?></button>
                            <input type="hidden" name="install_telemetry_ok" value="1"/>
                        </div>
                    </div>
                </form>

