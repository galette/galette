<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Public pages routes
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

use Galette\Core\Picture as Picture;
use Galette\Repository\Members as Members;
use Galette\Entity\Adherent as Adherent;
use Galette\Entity\Required as Required;
use Galette\Entity\DynamicFields as DynamicFields;
use Galette\Entity\FieldsConfig as FieldsConfig;
use Galette\Filters\MembersList as MembersList;
use Galette\Repository\Groups as Groups;
use \Analog\Analog as Analog;

//public members list
$app->get(
    '/public/members',
    function () use ($app, &$session) {
        if ( isset($session['public_filters']['members']) ) {
            $filters = unserialize($session['public_filters']['members']);
        } else {
            $filters = new MembersList();
        }

        /*// Filters
        if (isset($_GET['page'])) {
            $filters->current_page = (int)$_GET['page'];
        }

        if ( isset($_GET['clear_filter']) ) {
            $filters->reinit();
        }

        //numbers of rows to display
        if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
            $filters->show = $_GET['nbshow'];
        }

        // Sorting
        if ( isset($_GET['tri']) ) {
            $filters->orderby = $_GET['tri'];
        }*/


        $m = new Members();
        $members = $m->getPublicList(false, null);

        $session['public_filters']['members'] = serialize($filters);

        $smarty = $app->view()->getInstance();

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($smarty);

        $app->render(
            'liste_membres.tpl',
            array(
                'page_title'    => _T("Members list"),
                'members'       => $members,
                'nb_members'    => $m->getCount(),
                'filters'       => $filters
            )
        );
    }
)->name('public_members');

//public trombinoscope
$app->get(
    '/public/trombinoscope',
    function () use ($app) {
        $m = new Members('trombinoscope_');
        $members = $m->getPublicList(true, null);

        $app->render(
            'trombinoscope.tpl',
            array(
                'page_title'                => _T("Trombinoscope"),
                'additionnal_html_class'    => 'trombinoscope',
                'members'                   => $members,
                'time'                      => time()
            )
        );
    }
)->name('public_trombinoscope');
