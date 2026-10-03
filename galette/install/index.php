<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

// check required PHP version...
if (version_compare(PHP_VERSION, '5.3', '>')) { // @phpstan-ignore if.alwaysTrue
    header('location: ../webroot/installer.php');
    die();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Galette compatibility check</title>
    </head>
    <body>
        <h1>Galette configuration check</h1>
        <h2 class="error">Not compatible :(</h2>
    </body>
</html>

