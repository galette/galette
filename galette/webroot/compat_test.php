<?php

/**
 * Copyright © 2003-2024 The Galette Team
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
 */

define('GALETTE_ROOT', __DIR__ . '/../');
require_once GALETTE_ROOT . 'config/versions.inc.php';
require_once GALETTE_ROOT . 'config/paths.inc.php';

$phpok = !version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<');
$php_message = PHP_VERSION;
if (!$phpok) {
    $php_message .= sprintf(' (%s minimum required)', GALETTE_PHP_MIN);
} else {
    require_once GALETTE_ROOT . '/vendor/autoload.php';
    $cm = new Galette\Core\CheckModules(false);
    $cm->doCheck(false); //do not load with translations!
}
?>
<html>
    <head>
        <title>Galette compatibility tests</title>
        <link rel="stylesheet" type="text/css" href="./assets/css/galette-main.bundle.min.css" />
        <link rel="stylesheet" type="text/css" href="./themes/default/ui/semantic.min.css" />
        <link rel="shortcut icon" href="./themes/default/images/favicon.png" />
    </head>
    <body class="pushable">
        <div class="pusher">
            <div id="main" class="ui container">
                <div class="ui basic segment">
                    <div class="ui basic center aligned fitted segment">
                        <img class="icon" alt="[ Galette ]" src="./themes/default/images/galette.png"/>
                    </div>
                    <h1 class="ui block center aligned header">Compatibility tests</h1>
                    <div class="ui segment">
                        <div id="main" class="text ui container">
                <?php
                if (
                    !$phpok
                    || !isset($cm)
                    || !$cm->isValid()
                ) {
                    echo '<p class="ui red center aligned message">Something is wrong :(</p>';
                } else {
                    echo '<p class="ui green center aligned message">Everything is OK :)</p>';
                }
                ?>
                            <ul class="leaders">
                                <li>
                                    <span>PHP <strong class="<?php echo ($phpok) ? 'Ok' : 'Missing'; ?>"><?php echo $php_message; ?></strong></span>
                                    <span><i class="ui <?php echo ($phpok) ? 'green check' : 'red times'; ?> icon"></i></span>
                                </li>
                <?php
                if (isset($cm)) {
                    echo $cm->toHtml(false);
                }
                ?>
                            </ul>
                <?php
                if ($phpok && isset($cm) && $cm->isValid()) {
                    echo '<p class="ui center aligned message">You can now <a href="./installer.php">install Galette</a></p>';
                }
                ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
