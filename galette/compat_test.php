<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Compatibility tests
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-02-03
 */

$modules = array();
$modules['SimpleXML'] = (extension_loaded('SimpleXML')) ? 'Ok' : 'Missing';
$modules['gd'] = (extension_loaded('SimpleXML')) ? 'Ok' : 'Missing';
$modules['sql'] = (extension_loaded('pdo_mysql') || $extension_loaded('pdo_pgsql') || extension_loaded('pdo_sqlite')) ? 'Ok' : 'Missing';
$modules['mbstring'] = (extension_loaded('SimpleXML')) ? 'Ok' : 'Missing';

$phpok = !version_compare(PHP_VERSION, '5.3.7', '<');
?>
<html>
    <head>
        <title>Galette compatibility tests</title>
        <link rel="stylesheet" type="text/css" href="templates/default/galette.css"/>
        <style type="text/css">
            h1 {
                margin-top: .5em;
                text-align: center;
            }
            h2 {
                font-size: 1.2em;
                text-align: center;
            }
            div {
                width: 20em;
                margin: 0 auto;
            }
            ul {
                list-style-type: none;
                margin: 0;
                padding: 0;
            }
            span.Missing, span.Ok {
                font-weight: bold;
                float: right;
            }
            .Missing {
                color: red;
            }
            .Ok {
                color: green;
            }
        </style>
    </head>
    <body>
        <h1>
            <img src="templates/default/images/galette.png"/>
            <br/>Compatibility tests
        </h1>
<?php
if ( !$phpok
    || $modules['SimpleXML'] === 'Missing'
    || $modules['gd'] === 'Missing'
    || $modules['sql'] === 'Missing'
    || $modules['mbstring'] === 'Missing'
) {
            echo '<h2 class="Missing">Something is wrong :(</h2>';
} else {
            echo '<h2 class="Ok">Everything is OK :)</h2>';
}
?>
        <div>
            <ul>
                <li>PHP version: <span class="<?php echo ($phpok) ? 'Ok' : 'Missing'; ?>"><?php echo PHP_VERSION; ?></span></li>
<?php
foreach ( $modules as $mod=>$present ) {
    ?>
                <li>
                    <?php echo $mod . ': <span class="' . $present . '">' . $present . '</span>'; ?>
                </li>
    <?php
}
?>
            </ul>
        </div>
    </body>
</html>
