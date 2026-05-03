<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Core\Install as GaletteInstall;

/**
 * @var GaletteInstall $install
 * @var \Galette\Core\I18n $i18n
 */

?>
<form action="installer.php" method="POST" class="ui form">
    <div class="ui two stackable cards">
            <div class="ui fluid card">
                <div class="content">
                    <div class="ui medium header">
                        <div class="ui radio checkbox">
                            <input type="radio" name="install_type" value="<?php echo GaletteInstall::INSTALL; ?>"<?php echo ($install->isInstall() || !$install->isUpgrade()) ? ' checked="checked"' : ''; ?> id="install"/>
                            <label for="install"><?php echo _T("New installation"); ?></label>
                        </div>
                    </div>
                    <ul>
                        <li><?php echo _T("you're installing Galette for the first time"); ?>,</li>
                        <li><?php echo _T("you wish to erase an older version of Galette without keeping your data"); ?>.</li>
                    </ul>
                </div>
            </div>
            <div class="ui fluid card">
                <div class="content">
                    <div class="ui medium header">
                        <div class="ui radio checkbox">
                            <input type="radio" name="install_type" value="<?php echo GaletteInstall::UPDATE; ?>"<?php echo $install->isUpgrade() ? ' checked="checked"' : ''; ?> id="update"/>
                            <label for="update"><?php echo _T("Update"); ?></label>
                        </div>
                    </div>
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
            <button type="submit" class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
        </div>
        <div class="left aligned column">
            <button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'right' : 'left'; ?> icon" aria-hidden="true"></i> <?php echo _T("Back"); ?></button>
        </div>
    </div>
</form>
