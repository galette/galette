<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\Crud;
use Galette\Controllers\PdfController;

/**
 * @var \Slim\App<\DI\Container> $app
 * @var \Slim\Routing\RouteParser $routeparser
 * @var \Galette\Middleware\Authenticate $authenticate
 */

$app->get(
    '/groups',
    [Crud\GroupsController::class, 'list']
)->setName('groups')->add($authenticate);

$app->post(
    '/groups/reorder',
    [Crud\GroupsController::class, 'reorderList']
)->setName('reorderGroups')->add($authenticate);

$app->get(
    '/group/add/{name}',
    [Crud\GroupsController::class, 'doAdd']
)->setName('add_group')->add($authenticate);

$app->get(
    '/group/edit/{id:\d+}',
    [Crud\GroupsController::class, 'edit']
)->setName('editGroup')->add($authenticate);

$app->post(
    '/group/edit/{id:\d+}',
    [Crud\GroupsController::class, 'doEdit']
)->setName('doEditGroup')->add($authenticate);

$app->get(
    '/group/remove/{id:\d+}',
    [Crud\GroupsController::class, 'confirmDelete']
)->setName('removeGroup')->add($authenticate);

$app->post(
    '/group/remove/{id:\d+}',
    [Crud\GroupsController::class, 'delete']
)->setName('doRemoveGroup')->add($authenticate);

$app->get(
    '/pdf/groups[/{id:\d+}]',
    [PdfController::class, 'group']
)->setName('pdf_groups')->add($authenticate);

$app->post(
    '/ajax/group',
    [Crud\GroupsController::class, 'getGroup']
)->setName('ajax_group')->add($authenticate);

$app->post(
    '/ajax/unique-groupname',
    [Crud\GroupsController::class, 'checkUniqueness']
)->setName('ajax_groupname_unique')->add($authenticate);

$app->post(
    '/ajax/groups',
    [Crud\GroupsController::class, 'simpleList']
)->setName('ajax_groups')->add($authenticate);

$app->post(
    '/ajax/groups/reorder',
    [Crud\GroupsController::class, 'reorder']
)->setName('ajax_groups_reorder')->add($authenticate);
