<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Install $install */

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
                <button type="submit" class="ui right labeled primary icon button"><i class="home icon" aria-hidden="true"></i> <?php echo _T("Homepage"); ?></button>
            </div>
        </div>
    </form>
