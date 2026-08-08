<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\Crud;
use Galette\Controllers\PdfController;
use Galette\Middleware\Authenticate;

/**
 * @var \Slim\App<\DI\Container> $app
 */

$app->get(
    '/groups',
    [Crud\GroupsController::class, 'list']
)->setName('groups')->add(Authenticate::class);

$app->post(
    '/groups/reorder',
    [Crud\GroupsController::class, 'reorderList']
)->setName('reorderGroups')->add(Authenticate::class);

$app->post(
    '/group/add',
    [Crud\GroupsController::class, 'doAdd']
)->setName('doAddGroup')->add(Authenticate::class);

$app->get(
    '/group/edit/{id:\d+}',
    [Crud\GroupsController::class, 'edit']
)->setName('editGroup')->add(Authenticate::class);

$app->post(
    '/group/edit/{id:\d+}',
    [Crud\GroupsController::class, 'doEdit']
)->setName('doEditGroup')->add(Authenticate::class);

$app->post(
    '/group/{id:\d+}/persons/{mode:members|managers}',
    [Crud\GroupsController::class, 'storePersons']
)->setName('storeGroupPersons')->add(Authenticate::class);

$app->get(
    '/group/remove/{id:\d+}',
    [Crud\GroupsController::class, 'confirmDelete']
)->setName('removeGroup')->add(Authenticate::class);

$app->post(
    '/group/remove/{id:\d+}',
    [Crud\GroupsController::class, 'delete']
)->setName('doRemoveGroup')->add(Authenticate::class);

$app->get(
    '/pdf/groups[/{id:\d+}]',
    [PdfController::class, 'group']
)->setName('pdf_groups')->add(Authenticate::class);

$app->post(
    '/ajax/group',
    [Crud\GroupsController::class, 'getGroup']
)->setName('ajax_group')->add(Authenticate::class);

$app->post(
    '/ajax/group/{id:\d+}/persons/{mode:members|managers}',
    [Crud\GroupsController::class, 'ajaxPersons']
)->setName('ajaxGroupPersons')->add(Authenticate::class);

$app->post(
    '/ajax/groups',
    [Crud\GroupsController::class, 'simpleList']
)->setName('ajax_groups')->add(Authenticate::class);

$app->post(
    '/ajax/groups/reorder',
    [Crud\GroupsController::class, 'reorder']
)->setName('ajax_groups_reorder')->add(Authenticate::class);
