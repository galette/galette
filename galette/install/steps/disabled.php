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
    <div class="ui orange message">
        <div class="header">
            <?php echo _T("The installer is disabled."); ?>
        </div>
        <p><?php echo _T("For security reasons, Galette installer is disabled by default. To run an installation or an update, create an empty file at the following location on the server, then reload this page:"); ?></p>
        <p><code><?php echo htmlentities($install->getEnableInstallFilePath()); ?></code></p>
        <p><?php echo _T("Galette will remove this file automatically once the operation has succeeded."); ?></p>
    </div>

    <div class="ui section divider"></div>

    <div class="ui mobile reversed tablet reversed computer reversed equal width grid">
        <div class="right aligned column">
            <a href="installer.php" class="ui right labeled primary icon button"><i class="sync alt icon" aria-hidden="true"></i> <?php echo _T("I have created the file, reload"); ?></a>
        </div>
        <div class="left aligned column">
            <a href="<?php echo GALETTE_BASE_PATH; ?>" class="ui labeled icon button"><i class="home icon" aria-hidden="true"></i> <?php echo _T("Homepage"); ?></a>
        </div>
    </div>
