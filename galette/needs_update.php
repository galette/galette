<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Page that informs admin Galette need to be updated
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-02-05
 */

//set a flag saying we work from installer
//that way, in galette.inc.php, we'll only include relevant parts
$installer = true;
if ( !defined('GALETTE_ROOT') ) {
    define('GALETTE_ROOT', __DIR__ . '/');
}

require_once GALETTE_ROOT . 'includes/galette.inc.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $i18n->getAbbrev(); ?>">
    <head>
        <title><?php echo _T("Galette needs update!"); ?></title>
        <meta charset="UTF-8"/>
        <link rel="stylesheet" type="text/css" href="templates/default/galette.css"/>
        <link rel="stylesheet" type="text/css" href="templates/default/jquery-ui/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>.custom.css"/>
        <script type="text/javascript" src="includes/jquery/jquery-<?php echo JQUERY_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="includes/jquery/jquery-migrate-<?php echo JQUERY_MIGRATE_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.widget.min.js"></script>
        <script type="text/javascript" src="includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.button.min.js"></script>
        <script type="text/javascript" src="includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.position.min.js"></script>
        <script type="text/javascript" src="includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.tooltip.min.js"></script>
        <script type="text/javascript" src="includes/jquery/jquery.bgFade.js"></script>
        <script type="text/javascript" src="includes/common.js"></script>
        <!--[if lte IE 9]>
            <script type="text/javascript" src="{$scripts_dir}html5-ie.js"></script>
        <!endif]-->
    </head>
    <body class="notup2date">
        <div id="errorbox">
            <h1><?php echo _T("Galette needs update!"); ?></h1>
            <p><?php echo _T("Your Galette database is not present, or not up to date."); ?></p>
            <p><em><?php echo _T("Please run install or upgrade procedure (check the documentation)"); ?></em></p>
    </body>
</html>
