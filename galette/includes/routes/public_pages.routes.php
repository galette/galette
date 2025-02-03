<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

use Galette\Controllers\Crud;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public', function (RouteCollectorProxy $app) use ($routeparser): void {
    //public members list
    $app->get(
        '/members/list[/{option:page|order}/{value:\d+|\w+}]',
        [Crud\MembersController::class, 'publicMembersList']
    )->setName('publicMembersList');

    //members list filtering
    $app->post(
        '/members/list/filter[/{from}]',
        [Crud\MembersController::class, 'filterPublicMembersList']
    )->setName('filterPublicMembersList');

    //public members gallery
    $app->get(
        '/members/gallery[/{option:page|order}/{value:\d+|\w+}]',
        [Crud\MembersController::class, 'publicMembersGallery']
    )->setName('publicMembersGallery');

    //members list filtering
    $app->post(
        '/members/gallery/filter[/{from}]',
        [Crud\MembersController::class, 'filterPublicMembersGallery']
    )->setName('filterPublicMembersGallery');


    $app->get(
        '/documents[/{option:page|order}/{value:\d+|\w+}]',
        [Crud\DocumentsController::class, 'publicList']
    )->setName('documentsPublicList');

    $app->get(
        '/{list|members}[/{option:page|order}/{value:\d+|\w+}]',
        function ($request, $response, ?string $option = null, ?string $value = null) use ($routeparser) {
            //list deprecated since 1.2.0
            //members deprecated since 0.9.3
            $args = [];
            if ($option !== null && $value !== null) {
                $args['option'] = $option;
                $args['value'] = $value;
            }
            return $response
                ->withStatus(301)
                ->withHeader('Location', $routeparser->urlFor('publicMembersList', $args));
        }
    );

    $app->get(
        '/{trombinoscope|trombi}',
        function ($request, $response) use ($routeparser) {
            //trombi deprecated since 1.2.0
            //trombinoscope deprecated since 0.9.3
            return $response
                ->withStatus(301)
                ->withHeader('Location', $routeparser->urlFor('publicMembersGallery'));
        }
    );
})->add(\Galette\Middleware\PublicPages::class);
