<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\Crud;
use Galette\Controllers\CsvController;
use Galette\Controllers\GaletteController;
use Galette\Controllers\PdfController;
use Galette\Entity\Adherent;
use Galette\Middleware\Authenticate;

/**
 * @var \Slim\App<\DI\Container> $app
 */

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
)->setName('csv-memberslist')->add(Authenticate::class);

//members list
$app->get(
    '/members[/{option:page|order}/{value:\d+|\w+}]',
    [Crud\MembersController::class, 'list']
)->setName('members')->add(Authenticate::class);

//members list filtering
$app->post(
    '/members/filter',
    [Crud\MembersController::class, 'filter']
)->setName('filter-memberslist')->add(Authenticate::class);

//members self card
$app->get(
    '/member/me',
    [Crud\MembersController::class, 'showMe']
)->setName('me')->add(Authenticate::class);

//members card
$app->get(
    '/member/{id:\d+}',
    [Crud\MembersController::class, 'show']
)->setName('member')->add(Authenticate::class);

$app->get(
    '/member/vcard/{id:\d+}',
    [Crud\MembersController::class, 'vcard']
)->setName('memberVCard')->add(Authenticate::class);

$app->get(
    '/member/edit/{id:\d+}',
    [Crud\MembersController::class, 'edit']
)->setName('editMember')->add(Authenticate::class);

$app->get(
    '/member/add',
    [Crud\MembersController::class, 'add']
)->setName('addMember')->add(Authenticate::class);

$app->get(
    '/member/add/child',
    [Crud\MembersController::class, 'addChild']
)->setName('addMemberChild')->add(Authenticate::class);

$app->post(
    '/subscribe/store',
    [Crud\MembersController::class, 'doSelfSubscribe']
)->setName('storeselfmembers');

$app->post(
    '/member/store',
    [Crud\MembersController::class, 'doAdd']
)->setName('doAddMember');

$app->post(
    '/member/store/child',
    [Crud\MembersController::class, 'doAddChild']
)->setName('doAddMemberChild')->add(Authenticate::class);

$app->post(
    '/member/store/{id:\d+}',
    [Crud\MembersController::class, 'doEdit']
)->setName('doEditMember');

$app->get(
    '/member/remove/{id:\d+}',
    [Crud\MembersController::class, 'confirmDelete']
)->setName('removeMember')->add(Authenticate::class);

$app->get(
    '/members/remove',
    [Crud\MembersController::class, 'confirmDelete']
)->setName('removeMembers')->add(Authenticate::class);

$app->post(
    '/member/remove' . '[/{id:\d+}]',
    [Crud\MembersController::class, 'delete']
)->setName('doRemoveMember')->add(Authenticate::class);

//advanced search page
$app->get(
    '/advanced-search',
    [Crud\MembersController::class, 'advancedSearch']
)->setName('advanced-search')->add(Authenticate::class);

//Batch actions on members list
$app->post(
    '/members/batch',
    [Crud\MembersController::class, 'handleBatch']
)->setName('batch-memberslist')->add(Authenticate::class);

//PDF members cards
$app->get(
    '/members/cards[/{' . Adherent::PK . ':\d+}]',
    [PdfController::class, 'membersCards']
)->setName('pdf-members-cards')->add(Authenticate::class);

//PDF members labels
$app->map(
    ['GET', 'POST'],
    '/members/labels',
    [PdfController::class, 'membersLabels']
)->setName('pdf-members-labels')->add(Authenticate::class);

//PDF adhesion form
$app->get(
    '/members/adhesion-form/{' . Adherent::PK . ':\d+}',
    [PdfController::class, 'adhesionForm']
)->setName('adhesionForm')->add(Authenticate::class);

//Empty PDF adhesion form
$app->get(
    '/members/empty-adhesion-form',
    [PdfController::class, 'adhesionForm']
)->setName('emptyAdhesionForm');

//mailing
$app->get(
    '/mailing',
    [Crud\MailingsController::class, 'add']
)->setName('mailing')->add(Authenticate::class);

$app->post(
    '/mailing',
    [Crud\MailingsController::class, 'doAdd']
)->setName('doMailing')->add(Authenticate::class);

$app->map(
    ['GET', 'POST'],
    '/mailing/preview[/{id:\d+}]',
    [Crud\MailingsController::class, 'preview']
)->setName('mailingPreview')->add(Authenticate::class);

$app->get(
    '/mailing/preview/{id:\d+}/attachment/{pos:\d+}',
    [Crud\MailingsController::class, 'previewAttachment']
)->setName('previewAttachment')->add(Authenticate::class);

$app->post(
    '/ajax/mailing/set-recipients',
    [Crud\MailingsController::class, 'setRecipients']
)->setName('mailingRecipients')->add(Authenticate::class);

$app->get(
    '/mailing/queue/{id:\d+}',
    [Crud\MailingsController::class, 'queue']
)->setName('mailingQueue')->add(Authenticate::class);

$app->post(
    '/ajax/mailing/process-queue',
    [Crud\MailingsController::class, 'processQueue']
)->setName('mailingProcessQueue')->add(Authenticate::class);

$app->get(
    '/reminders/queue',
    [Crud\MailingsController::class, 'remindersQueue']
)->setName('remindersQueue')->add(Authenticate::class);

$app->post(
    '/ajax/reminders/process-queue',
    [Crud\MailingsController::class, 'remindersProcessQueue']
)->setName('remindersProcessQueue')->add(Authenticate::class);

//reminders
$app->get(
    '/reminders',
    [GaletteController::class, 'reminders']
)->setName('reminders')->add(Authenticate::class);

$app->post(
    '/reminders',
    [GaletteController::class, 'doReminders']
)->setName('doReminders')->add(Authenticate::class);

$app->get(
    '/members/reminder-filter/{membership:nearly|late}/{mail:withmail|withoutmail}',
    [GaletteController::class, 'filterReminders']
)->setName('reminders-filter')->add(Authenticate::class);

$app->map(
    ['GET', 'POST'],
    '/attendance-sheet/details',
    [PdfController::class, 'attendanceSheetConfig']
)->setName('attendance_sheet_details')->add(Authenticate::class);

$app->post(
    '/attendance-sheet',
    [PdfController::class, 'attendanceSheet']
)->setName('attendance_sheet')->add(Authenticate::class);

$app->post(
    '/ajax/members[/{option:page|order}/{value:\d+}]',
    [Crud\MembersController::class, 'ajaxList']
)->setName('ajaxMembers')->add(Authenticate::class);

$app->post(
    '/ajax/group/members',
    [Crud\GroupsController::class, 'ajaxMembers']
)->setName('ajaxGroupMembers')->add(Authenticate::class);

$app->get(
    '/members/mass-change',
    [Crud\MembersController::class, 'massChange']
)->setName('masschangeMembers')->add(Authenticate::class);

$app->post(
    '/members/mass-change/validate',
    [Crud\MembersController::class, 'validateMassChange']
)->setName('masschangeMembersReview')->add(Authenticate::class);

$app->post(
    '/members/mass-change',
    [Crud\MembersController::class, 'doMassChange']
)->setName('massstoremembers')->add(Authenticate::class);

//Duplicate member
$app->get(
    '/members/duplicate/{' . Adherent::PK . ':\d+}',
    [Crud\MembersController::class, 'duplicate']
)->setName('duplicateMember')->add(Authenticate::class);

//saved searches
$app->map(
    ['GET', 'POST'],
    '/save-search',
    [Crud\SavedSearchesController::class, 'doAdd']
)->setName('saveSearch');

$app->get(
    '/saved-searches[/{option:page|order}/{value:\d+}]',
    [Crud\SavedSearchesController::class, 'list']
)->setName('searches')->add(Authenticate::class);

$app->get(
    '/search/remove/{id:\d+}',
    [Crud\SavedSearchesController::class, 'confirmDelete']
)->setName('removeSearch')->add(Authenticate::class);

$app->get(
    '/searches/remove',
    [Crud\SavedSearchesController::class, 'confirmDelete']
)->setName('removeSearches')->add(Authenticate::class);

$app->post(
    '/search/remove' . '[/{id:\d+}]',
    [Crud\SavedSearchesController::class, 'delete']
)->setName('doRemoveSearch')->add(Authenticate::class);

$app->get(
    '/save-search/{id}',
    [Crud\SavedSearchesController::class, 'load']
)->setName('loadSearch');
