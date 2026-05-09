<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

define('GALETTE_ROOT', __DIR__ . '/../'); //@phpstan-ignore theCodingMachineSafe.function
require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';
require_once GALETTE_ROOT . 'includes/sys_config/paths.inc.php';

$phpok = !version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<'); //@phpstan-ignore booleanNot.alwaysTrue
$php_message = PHP_VERSION;
if (!$phpok) { //@phpstan-ignore booleanNot.alwaysFalse
    $php_message .= sprintf(' (%s minimum required)', GALETTE_PHP_MIN);
} else {
    require_once GALETTE_ROOT . '/vendor/autoload.php';
    $cm = new Galette\Core\CheckModules(false);
    $cm->doCheck(false); //do not load with translations!
}

$compat_ok = $phpok //@phpstan-ignore booleanAnd.leftAlwaysTrue
        && isset($cm) //@phpstan-ignore isset.variable
        && $cm->isValid();

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Galette compatibility tests</title>
        <link rel="stylesheet" type="text/css" href="./assets/css/galette-main.bundle.min.css" />
        <link rel="stylesheet" type="text/css" href="./themes/default/ui/semantic.min.css" />
        <link rel="shortcut icon" href="./themes/default/images/favicon.png" />
    </head>
    <body class="pushable">
        <div class="pusher">
            <main id="main" class="ui container">
                <div class="ui basic segment">
                    <div class="ui basic center aligned fitted segment">
                        <img class="icon" width="200" alt="" src="./themes/default/images/galette.webp"/>
                    </div>
                    <h1 class="ui block center aligned header">Compatibility tests</h1>
                    <div class="ui segment">
                        <div class="text ui container">
                            <?php echo $compat_ok ? '<p class="ui green center aligned message">Everything is OK :)</p>' : '<p class="ui red center aligned message">Something is wrong :(</p>'; ?>
                            <ul class="leaders">
                                <li>
                                    <span>PHP <strong class="<?php echo ($phpok) ? 'Ok' : 'Missing';  // @phpstan-ignore ternary.alwaysTrue?>"><?php echo $php_message; ?></strong></span>
                                    <span><i class="ui <?php echo ($phpok) ? 'green check' : 'red times'; // @phpstan-ignore ternary.alwaysTrue?> icon"></i></span>
                                </li>
                <?php
                if (isset($cm)) { // @phpstan-ignore isset.variable
                    echo $cm->toHtml(false);
                } ?>
                            </ul>
                <?php
                if ($phpok && isset($cm) && $cm->isValid()) { // @phpstan-ignore isset.variable,booleanAnd.leftAlwaysTrue
                    echo '<p class="ui center aligned message">You can now <a href="./installer.php">install Galette</a></p>';
                } ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
