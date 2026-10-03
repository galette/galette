<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Plugins;
use Galette\Core\Preferences;

/**
 * @var \Galette\Core\Install $install
 * @var \Galette\Core\Db $zdb
 * @var \Galette\Core\I18n $i18n
 */

$preferences = new Preferences($zdb);
$plugins = new Plugins();
$telemetry = new \Galette\Util\Telemetry(
    $zdb,
    $preferences,
    $plugins
);
?>
    <form action="installer.php" method="POST" class="ui form">
        <div class="ui stackable equal width grid">
            <div class="column">
                <div class="ui toggle checkbox tooltip" title="<?php echo _T("Send anonymous and imprecise data about your Galette instance"); ?>">
                    <input type="checkbox" name="send_telemetry" tabindex="0" class="hidden" checked="checked"/>
                    <label for="send_telemetry"><?php echo _T("Send telemetry information"); ?></label>
                </div>
            </div>
<?php
if (!$telemetry->isRegistered()) {
    ?>
            <div class="right aligned column">
                <a class="ui blue button" href="<?php echo GALETTE_TELEMETRY_URI; ?>reference?showmodal&uuid=<?php echo $telemetry->getRegistrationUuid(); ?>" title="<?php echo _T("Register your organization as a Galette user"); ?>" target="_blank">
                    <i class="id card icon" aria-hidden="true"></i>
                    <?php echo _T("Register"); ?>
                </a>
            </div>
    <?php
}
?>
        </div>
        <div class="ui info visible message">
            <p><?php echo _T("Telemetry data are <strong>anonymous</strong>; nothing about your organization or its members will be sent."); ?></p>
            <p>
                <?php echo _T("Also note that all data are sent over a <strong>HTTPS secured connection</strong>."); ?>
            </p>
            <div class="tdata">
            </div>
        </div>

        <div class="ui section divider"></div>

        <div class="ui equal width grid">
            <div class="right aligned column">
                <button type="submit" class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
                <input type="hidden" name="install_telemetry_ok" value="1"/>
            </div>
        </div>
    </form>

