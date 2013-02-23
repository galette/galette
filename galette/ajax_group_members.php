<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Display groups members or managers from ajax
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
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-10-29
 */

use Analog\Analog as Analog;
use Galette\Repository\Members as Members;

require_once 'includes/galette.inc.php';

$ids = $_POST['persons'];
$mode = $_POST['person_mode'];
if ( !$ids || !$mode ) {
    Analog::log(
        'Trying to display ajax_group_members.php without persons or mode specified',
        Analog::INFO
    );
    die();
}

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() ) {
    Analog::log(
        'Trying to display ajax_group_members.php without appropriate permissions',
        Analog::INFO
    );
    die();
}

$m = new Members;
$persons = $m->getArrayList($ids);

$tpl->assign('persons', $persons);
$tpl->assign('person_mode', $mode);

$tpl->display('group_persons.tpl');
