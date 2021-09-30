<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Public pages routes
 *
 * PHP version 5
 *
 * Copyright © 2014-2020 The Galette Team
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
 * @copyright 2014-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Controllers\Crud;

$showPublicPages = function ($request, $response, $next) use ($container) {
    $login = $container->get('login');
    $preferences = $container->get('preferences');

    if (!$preferences->showPublicPages($login)) {
        $this->get('flash')->addMessage('error', _T("Unauthorized"));

        return $response
            ->withStatus(403)
            ->withHeader(
                'Location',
                $this->get('router')->pathFor('slash')
            );
    }

    return $next($request, $response);
};

$app->group('/public', function () {
    //public members list
    $this->get(
        '/{type:list|trombi}[/{option:page|order}/{value:\d+}]',
        [Crud\MembersController::class, 'publicList']
    )->setName('publicList');

    //members list filtering
    $this->post(
        '/{type:list|trombi}/filter[/{from}]',
        [Crud\MembersController::class, 'filterPublicList']
    )->setName('filterPublicList');

    $this->get(
        '/members[/{option:page|order}/{value:\d+}]',
        function ($request, $response) {
            $args = ['type' => 'list'];
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->get('router')->pathFor('publicList', $args));
        }
    );

    $this->get(
        '/trombinoscope',
        function ($request, $response) {
            $args = ['type' => 'trombi'];
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->get('router')->pathFor('publicList', $args));
        }
    );
})->add($showPublicPages);
