<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main routes
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Core\Picture;
use Galette\Core\SysInfos;
use Galette\Entity\Adherent;

//main route
$app->get(
    '/',
    function () use ($app, $baseRedirect) {
        $baseRedirect($app);
    }
)->name('slash');

//logo route
$app->get(
    '/logo',
    function () use ($logo) {
        $logo->display();
    }
)->name('logo');

//photo route
$app->get(
    '/photo/:id',
    function ($id) use ($app, $login) {
        /** FIXME: we load entire member here... No need to do so! */
        $deps = array(
            'groups'    => false,
            'dues'      => false
        );
        $adh = new Adherent((int)$id, $deps);

        $picture = null;
        if ( $login->isAdmin()
            || $login->isStaff()
            || $adh->appearsInMembersList()
            || $login->login == $adh->login
        ) {
            $picture = $adh->picture;
        } else {
            $picture = new Picture();
        }
        $picture->display();
    }
)->name('photo');

//system informations
$app->get(
    '/sysinfos',
    function () use ($app) {
        $sysinfos = new SysInfos();
        $sysinfos->grab();

        $app->render(
            'sysinfos.tpl',
            array(
                'page_title'    => _T("System informations"),
                'rawinfos'      => $sysinfos->getRawData()
            )
        );
    }
)->name('sysinfos');
