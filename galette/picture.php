<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Displays a picture
 *
 * PHP version 5
 *
 * Copyright © 2005-2010 The Galette Team
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
 * @category  Classes
 * @package   Main
 *
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

/** @ignore */
require_once 'includes/galette.inc.php';

//we do not check if user is logged to display main logo
if (  isset($_GET['logo']) && $_GET['logo'] == 'true' ) {
    $logo->display();
} else {
    if ( !$login->isLogged() ) {
        header("location: index.php");
        die();
    }

    if ( isset($_GET['print_logo'])
        && $_GET['print_logo'] == 'true'
    ) {//displays the logo for printing
        include_once WEB_ROOT . 'classes/print_logo.class.php';
        $print_logo = new PrintLogo();
        $print_logo->display();
    } else { //displays the picture
        if ( !$login->isAdmin() ) {
            /** FIXME: these should not be fired when
            accessing from public pages */
            $id_adh = $login->id;
        } else {
            $id_adh = $_GET['id_adh'];
        }

        $picture = new Picture($id_adh);
        $picture->display();
    }
}
?>