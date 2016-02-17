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

use Galette\Core\Picture;
use Galette\Repository\Members;
use Galette\Entity\Adherent;
use Galette\Entity\Required;
use Galette\Entity\DynamicFields;
use Galette\Entity\FieldsConfig;
use Galette\Filters\MembersList;
use Galette\Repository\Groups;
use \Analog\Analog;

$showPublicPages = function ($request, $response, $next) use ($container, &$session) {
    $login = $container->login;
    $preferences = $container->preferences;

    if (!$preferences->showPublicPages($login)) {
        $this->flash->addMessage('error', _T("Unauthorized"));

        return $response
            ->withStatus(403)
            ->withHeader(
                'Location',
                $this->router->pathFor('slash')
            );
    }

    return $next($request, $response);
};

$app->group('/public', function () {
    //public members list
    $this->get(
        '/members',
        function ($request, $response) {
            if (isset($this->session['public_filters']['members'])) {
                $filters = unserialize($this->session['public_filters']['members']);
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

            $session = $this->session;
            $session['public_filters']['members'] = serialize($filters);
            $this->session = $session;

            //assign pagination variables to the template and add pagination links
            $filters->setSmartyPagination($this->router, $this->view->getSmarty(), false);

            // display page
            $this->view->render(
                $response,
                'liste_membres.tpl',
                array(
                    'page_title'    => _T("Members list"),
                    'members'       => $members,
                    'nb_members'    => $m->getCount(),
                    'filters'       => $filters
                )
            );
            return $response;
        }
    )->setName('public_members');

    //public trombinoscope
    $this->get(
        '/trombinoscope',
        function ($request, $response) {
            $m = new Members();
            $members = $m->getPublicList(true, null);

            // display page
            $this->view->render(
                $response,
                'trombinoscope.tpl',
                array(
                    'page_title'                => _T("Trombinoscope"),
                    'additionnal_html_class'    => 'trombinoscope',
                    'members'                   => $members,
                    'time'                      => time()
                )
            );
            return $response;
        }
    )->setName('public_trombinoscope');
})->add($showPublicPages);
