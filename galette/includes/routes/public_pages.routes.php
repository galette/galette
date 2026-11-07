<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\Crud;
use Galette\Middleware\PublicPages;
use Slim\Routing\RouteCollectorProxy;

/**
 * @var \Slim\App<\DI\Container> $app
 */

$app->group('/public', function (RouteCollectorProxy $app): void {
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
        '/staff/list[/{option:page|order}/{value:\d+|\w+}]',
        [Crud\MembersController::class, 'publicStaffList']
    )->setName('publicStaffList');

    //public staff members gallery
    $app->get(
        '/staff/gallery[/{option:page|order}/{value:\d+|\w+}]',
        [Crud\MembersController::class, 'publicStaffGallery']
    )->setName('publicStaffGallery');

    $app->get(
        '/documents[/{option:page|order}/{value:\d+|\w+}]',
        [Crud\DocumentsController::class, 'publicList']
    )->setName('documentsPublicList');

    //former public members list URLs
    $app->get(
        '/{orig:list|members}[/{option:page|order}/{value:\d+|\w+}]',
        [Crud\MembersController::class, 'legacyPublicMembersList']
    )->setName('publicMembersListLegacy');

    //former public members gallery URLs
    $app->get(
        '/{orig:trombinoscope|trombi}',
        [Crud\MembersController::class, 'legacyPublicMembersGallery']
    )->setName('publicMembersGalleryLegacy');
})->add(PublicPages::class);
