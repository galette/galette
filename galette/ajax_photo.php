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

use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() ) {
    Analog::log(
        'Trying to display ajax_members.php without appropriate permissions',
        Analog::INFO
    );
    die();
}

if ( !isset($_POST)
    || !isset($_POST['member_id'])
    || !isset($_POST['file'])
    || !isset($_POST['filename'])
    || !isset($_POST['filesize'])
) {
    die('Required argument not present.');
}

$mid = $_POST['member_id'];
$fsize = $_POST['filesize'];
$fname = $_POST['filename'];
$tmpname = GALETTE_TEMPIMAGES_PATH . 'ajax_upload_' . $fname;

$temp = explode('base64,', $_POST['file']);
$mime = str_replace('data:', '', trim($temp[0], ';'));
$raw_file = base64_decode($temp[1]);

//write temporary file
$fp = fopen($tmpname, 'w');
fwrite($fp, $raw_file);
fclose($fp);

$adh = new Galette\Entity\Adherent((int)$mid);

$ret = array();
$res = $adh->picture->store(
    array(
        'name' => $fname,
        'tmp_name' => $tmpname, 
        'size' => $fsize
    ),
    true
);
if ( $res < 0 ) {
    $ret['result'] = false;
    $ret['message'] = $adh->picture->getErrorMessage($res);
} else {
    $ret['result'] = true;
}

echo json_encode($ret);
