<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups related routes
 *
 * PHP version 5
 *
 * Copyright © 2014-2020 The Galette Team
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
 *
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     0.8.2dev 2014-11-27
 */

use Galette\Controllers\Crud;
use Galette\Controllers\PdfController;

$app->get(
    '/groups[/{id:\d+}]',
    [Crud\GroupsController::class, 'list']
)->setName('groups')->add($authenticate);

$app->get(
    '/group/add/{name}',
    [Crud\GroupsController::class, 'doAdd']
)->setName('add_group')->add($authenticate);

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
