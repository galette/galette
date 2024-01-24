<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

use Galette\Controllers\Crud;
use Galette\Controllers\CsvController;
use Galette\Controllers\GaletteController;
use Galette\Controllers\PdfController;
use Galette\Entity\Adherent;

//self subscription
$app->get(
    '/subscribe',
    [Crud\MembersController::class, 'selfSubscribe']
)->setName('subscribe');

//members list CSV export
$app->map(
    ['GET', 'POST'],
    '/members/export/csv',
    [CsvController::class, 'membersExport']
)->setName('csv-memberslist')->add($authenticate);

//members list
$app->get(
    '/members[/{option:page|order}/{value:\d+|\w+}]',
    [Crud\MembersController::class, 'list']
)->setName('members')->add($authenticate);

//members list filtering
$app->post(
    '/members/filter',
    [Crud\MembersController::class, 'filter']
)->setName('filter-memberslist')->add($authenticate);

//members self card
$app->get(
    '/member/me',
    [Crud\MembersController::class, 'showMe']
)->setName('me')->add($authenticate);

//members card
$app->get(
    '/member/{id:\d+}',
    [Crud\MembersController::class, 'show']
)->setName('member')->add($authenticate);

$app->get(
    '/member/edit/{id:\d+}',
    [Crud\MembersController::class, 'edit']
)->setName('editMember')->add($authenticate);

$app->get(
    '/member/add',
    [Crud\MembersController::class, 'add']
)->setName('addMember')->add($authenticate);

$app->get(
    '/member/add/child',
    [Crud\MembersController::class, 'addChild']
)->setName('addMemberChild')->add($authenticate);

$app->post(
    '/subscribe/store',
    [Crud\MembersController::class, 'doSelfSubscribe']
)->setName('storeselfmembers');

$app->post(
    '/member/store',
    [Crud\MembersController::class, 'doAdd']
)->setName('doAddMember');

$app->post(
    '/member/store/{id:\d+}',
    [Crud\MembersController::class, 'doEdit']
)->setName('doEditMember');

$app->get(
    '/member/remove/{id:\d+}',
    [Crud\MembersController::class, 'confirmDelete']
)->setName('removeMember')->add($authenticate);

$app->get(
    '/members/remove',
    [Crud\MembersController::class, 'confirmDelete']
)->setName('removeMembers')->add($authenticate);

$app->post(
    '/member/remove' . '[/{id:\d+}]',
    [Crud\MembersController::class, 'delete']
)->setName('doRemoveMember')->add($authenticate);

//advanced search page
$app->get(
    '/advanced-search',
    [Crud\MembersController::class, 'advancedSearch']
)->setName('advanced-search')->add($authenticate);

//Batch actions on members list
$app->post(
    '/members/batch',
    [Crud\MembersController::class, 'handleBatch']
)->setName('batch-memberslist')->add($authenticate);

//PDF members cards
$app->get(
    '/members/cards[/{' . Adherent::PK . ':\d+}]',
    [PdfController::class, 'membersCards']
)->setName('pdf-members-cards')->add($authenticate);

//PDF members labels
$app->map(
    ['GET', 'POST'],
    '/members/labels',
    [PdfController::class, 'membersLabels']
)->setName('pdf-members-labels')->add($authenticate);

//PDF adhesion form
$app->get(
    '/members/adhesion-form/{' . Adherent::PK . ':\d+}',
    [PdfController::class, 'adhesionForm']
)->setName('adhesionForm')->add($authenticate);

//Empty PDF adhesion form
$app->get(
    '/members/empty-adhesion-form',
    [PdfController::class, 'adhesionForm']
)->setName('emptyAdhesionForm');

//mailing
$app->get(
    '/mailing',
    [Crud\MailingsController::class, 'add']
)->setName('mailing')->add($authenticate);

$app->post(
    '/mailing',
    [Crud\MailingsController::class, 'doAdd']
)->setName('doMailing')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    '/mailing/preview[/{id:\d+}]',
    [Crud\MailingsController::class, 'preview']
)->setName('mailingPreview')->add($authenticate);

$app->get(
    '/mailing/preview/{id:\d+}/attachment/{pos:\d+}',
    [Crud\MailingsController::class, 'previewAttachment']
)->setName('previewAttachment')->add($authenticate);

$app->post(
    '/ajax/mailing/set-recipients',
    [Crud\MailingsController::class, 'setRecipients']
)->setName('mailingRecipients')->add($authenticate);

//reminders
$app->get(
    '/reminders',
    [GaletteController::class, 'reminders']
)->setName('reminders')->add($authenticate);

$app->post(
    '/reminders',
    [GaletteController::class, 'doReminders']
)->setName('doReminders')->add($authenticate);

$app->get(
    '/members/reminder-filter/{membership:nearly|late}/{mail:withmail|withoutmail}',
    [GaletteController::class, 'filterReminders']
)->setName('reminders-filter')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    '/attendance-sheet/details',
    [PdfController::class, 'attendanceSheetConfig']
)->setName('attendance_sheet_details')->add($authenticate);

$app->post(
    '/attendance-sheet',
    [PdfController::class, 'attendanceSheet']
)->setName('attendance_sheet')->add($authenticate);

$app->post(
    '/ajax/members[/{option:page|order}/{value:\d+}]',
    [Crud\MembersController::class, 'ajaxList']
)->setName('ajaxMembers')->add($authenticate);

$app->post(
    '/ajax/group/members',
    [Crud\GroupsController::class, 'ajaxMembers']
)->setName('ajaxGroupMembers')->add($authenticate);

$app->get(
    '/members/mass-change',
    [Crud\MembersController::class, 'massChange']
)->setName('masschangeMembers')->add($authenticate);

$app->post(
    '/members/mass-change/validate',
    [Crud\MembersController::class, 'validateMassChange']
)->setName('masschangeMembersReview')->add($authenticate);

$app->post(
    '/members/mass-change',
    [Crud\MembersController::class, 'doMassChange']
)->setName('massstoremembers')->add($authenticate);

//Duplicate member
$app->get(
    '/members/duplicate/{' . Adherent::PK . ':\d+}',
    [Crud\MembersController::class, 'duplicate']
)->setName('duplicateMember')->add($authenticate);

//saved searches
$app->map(
    ['GET', 'POST'],
    '/save-search',
    [Crud\SavedSearchesController::class, 'doAdd']
)->setName('saveSearch');

$app->get(
    '/saved-searches[/{option:page|order}/{value:\d+}]',
    [Crud\SavedSearchesController::class, 'list']
)->setName('searches')->add($authenticate);

$app->get(
    '/search/remove/{id:\d+}',
    [Crud\SavedSearchesController::class, 'confirmDelete']
)->setName('removeSearch')->add($authenticate);

$app->get(
    '/searches/remove',
    [Crud\SavedSearchesController::class, 'confirmDelete']
)->setName('removeSearches')->add($authenticate);

$app->post(
    '/search/remove' . '[/{id:\d+}]',
    [Crud\SavedSearchesController::class, 'delete']
)->setName('doRemoveSearch')->add($authenticate);

$app->get(
    '/save-search/{id}',
    [Crud\SavedSearchesController::class, 'load']
)->setName('loadSearch');
