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

use Galette\Repository\Members;
use Galette\Filters\MembersList;

$showPublicPages = function ($request, $response, $next) use ($container) {
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

$app->group(__('/public', 'routes'), function () {
    //public members list
    $this->get(
        __('/members', 'routes') . '[/{option:' . __('page', 'routes') . '|' . __('order', 'routes') . '}/{value:\d+}]',
        function ($request, $response) {
            $option = null;
            if (isset($args['option'])) {
                $option = $args['option'];
            }
            $value = null;
            if (isset($args['value'])) {
                $value = $args['value'];
            }

            if (isset($this->session->public_filter_members)) {
                $filters = $this->session->public_filter_members;
            } else {
                $filters = new MembersList();
            }

            if ($option !== null) {
                switch ($option) {
                    case __('page', 'routes'):
                        $filters->current_page = (int)$value;
                        break;
                    case __('order', 'routes'):
                        $filters->orderby = $value;
                        break;
                }
            }

            $m = new Members();
            $members = $m->getPublicList(false, null);

            $this->session->public_filter_members = $filters;

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
    )->setName('publicMembers');

    //members list filtering
    $this->post(
        __('/members/filter', 'routes') . '[/{from}]',
        function ($request, $response, $args) {
            $post = $request->getParsedBody();
            if (isset($this->session->public_filter_members)) {
                $filters = $this->session->public_filter_members;
            } else {
                $filters = new MembersList();
            }

            //reintialize filters
            if (isset($post['clear_filter'])) {
                $filters->reinit();
            } else {
                //number of rows to show
                if (isset($post['nbshow'])) {
                    $filters->show = $post['nbshow'];
                }
            }

            $this->session->public_filter_members = $filters;

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('publicMembers'));
        }
    )->setName('filterPublicMemberslist');

    //public trombinoscope
    $this->get(
        __('/trombinoscope', 'routes'),
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
    )->setName('publicTrombinoscope');
})->add($showPublicPages);
