<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Load a group
 *
 * This page can be loaded directly, or via ajax.
 * Via ajax, we do not have a full html page, but only
 * that will be displayed using javascript on another page
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @category  Plugins
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-01-19
 */

use Analog\Analog as Analog;

require_once 'includes/galette.inc.php';

$id = get_numeric_form_value(Galette\Entity\Group::PK, '');
if ( !$id ) {
    Analog::log(
        'Trying to display ajax_group.php without groups specified',
        Analog::INFO
    );
    die();
}

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff()
    && !$login->isGroupManager($id)
) {
    Analog::log(
        'Trying to display ajax_group.php without appropriate permissions',
        Analog::INFO
    );
    die();
}

// check for ajax mode
$ajax = ( isset($_POST['ajax']) && $_POST['ajax'] == 'true' ) ? true : false;

$group = new Galette\Entity\Group((int)$id);

if ( !isset($_POST['reorder']) ) {
    $groups = new Galette\Repository\Groups();

    $tpl->assign('ajax', $ajax);
    $tpl->assign('group', $group);
    $tpl->assign('groups', $groups->getList());

    if ( $ajax ) {
        $tpl->assign('mode', 'ajax');
        $tpl->display('group.tpl');
    } else {
        $tpl->assign('require_tabs', true);
        $content = $tpl->fetch('group.tpl');
        $tpl->assign('content', $content);
        $tpl->display('page.tpl');
    }
} else {
    //asking to reorder
    if ( isset($_POST['to']) ) {
        $group->setParentGroup((int)$_POST['to']);
        $group->store();
        echo json_encode(array('success' => 'true'));
    } else {
        Analog::log(
            'Trying to reorder without target specified',
            Analog::INFO
        );
        echo json_encode(array('success' => false));
        die();
    }
}
