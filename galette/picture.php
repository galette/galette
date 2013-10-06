<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Displays a picture
 *
 * PHP version 5
 *
 * Copyright © 2004-2013 The Galette Team
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
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: picture.php 877 2011-06-01 06:08:18Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

/** @ignore */
require_once 'includes/galette.inc.php';

//we do not check if user is logged to display main logo
if (  isset($_GET['logo']) && $_GET['logo'] == 'true' ) {
    $logo->display();
} else {
    if ( isset($_GET['print_logo'])
        && $_GET['print_logo'] == 'true'
    ) {//displays the logo for printing
        $print_logo = new Galette\Core\PrintLogo();
        $print_logo->display();
    } else { //displays the picture
        $id_adh = (int)$_GET['id_adh'];
        $deps = array(
            'picture'   => true,
            'groups'    => false,
            'dues'      => false
        );

        //if loggedin user is a group manager, we have to check
        //he manages a group requested member belongs to.
        if ( $login->isGroupManager() ) {
            $deps['groups'] = true;
        }

        $adh = new Galette\Entity\Adherent($id_adh, $deps);

        $is_manager = false;
        if ( !$login->isAdmin() && !$login->isStaff() && $login->isGroupManager() ) {
            $groups = $adh->groups;
            foreach ( $groups as $group ) {
                if ( $login->isGroupManager($group->getId()) ) {
                    $is_manager = true;
                    break;
                }
            }
        }

        $picture = null;
        if ( $login->isAdmin()
            || $login->isStaff()
            || $adh->appearsInMembersList()
            || $login->login == $adh->login
            || $is_manager
        ) {
            $picture = $adh->picture; //new Picture($id_adh);
        } else {
            $picture = new Galette\Core\Picture();
        }
        $picture->display();
    }
}

if ( isset($profiler) ) {
    $profiler->stop();
}
