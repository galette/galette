<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

//TODO: find a better way.
//Each route gets a level (superadmin, admin, staff, groupmanager or member) or a
//permission name ("domain:action"), checked through AccessControl.
//phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used on file inclusion
$core_acls = [
    // Main core rules.
    'impersonate'                       => 'superadmin',
    'unimpersonate'                     => 'member',
    //roles, with acls feature flag
    'roles'                             => 'admin',
    'storeRole'                         => 'admin',
    'editRole'                          => 'admin',
    'doEditRole'                        => 'admin',
    'removeRole'                        => 'admin',
    'doRemoveRole'                      => 'admin',
    'giveRole'                          => 'admin',
    'takeRole'                          => 'admin',
    'adminCredentials'                  => 'superadmin',
    'storeAdminCredentials'             => 'superadmin',
    'writeDarkCSS'                      => 'staff', //served to every visitor
    '/(.+)?admin(.+)?/i'                => 'superadmin',
    '/(.+)?[aA]dvancedConfig(.+)?/i'    => 'superadmin',
    '/(.+)?telemetry(.+)?/i'            => 'admin',
    'setRegistered'                     => 'admin',
    'authAttempts'                      => 'admin',
    'doAuthAttempts'                    => 'admin',
    '/(.+)?preferences(.+)?/i'          => 'admin',
    '/(.+)?(Core|Dynamic|List)Field(.+)?/i'  => 'admin', //dynamic fields are for admins only
    '/(.+)?removeSearch(.+)?/i'         => 'member',
    '/(.+)?remove(.+)?/i'               => 'staff', //per default, removal is limited to staff
    'advanced-search'                   => 'groupmanager',
    '/(.+)?search(.+)?/i'               => 'member',
    'testEmail'                         => 'admin',
    'testEmailConnection'               => 'admin',
    'dashboard'                         => 'member',
    'ajaxNews'                          => 'member', //dashboard news, displayed to whoever the dashboard is
    //every member manages their own second factor
    'two-factor-manage'                 => 'member',
    'two-factor-enrol'                  => 'member',
    'do-two-factor-enrol'               => 'member',
    'do-two-factor-disable'             => 'member',
    'do-two-factor-codes'               => 'member',
    'do-two-factor-reset'               => 'member:credentials',
    'sysinfos'                          => 'staff',
    'charts'                            => 'staff',
    '/(.+)?plugin(.+)?/i'               => 'admin',
    '/(.+)?mailing(.+)?/i'              => 'staff',
    'mailing'                           => 'mailing:send',
    'doMailing'                         => 'mailing:send',
    'mailingPreview'                    => 'mailing:send',
    'mailingRecipients'                 => 'mailing:send',
    'mailingQueue'                      => 'mailing:send',
    'mailingProcessQueue'               => 'mailing:send',
    '/(.+)?history(.+)?/i'              => 'staff',
    '/(.+)?import(.+)?/i'               => 'member:import',
    '/(.+)?export(.+)?/i'               => 'staff',
    // /Main core rule
    // Contributions rules
    'contributions'                     => 'member',
    'printContribution'                 => 'member',
    'myContributions'                   => 'member',
    'contributionMembers'               => 'groupmanager',
    //mass changes are for staff; the addContribution rule below would let groups managers in
    'massAddContributionsChooseType'    => 'contribution:mass-create',
    'massAddContributions'              => 'contribution:mass-create',
    'doMassAddContributions'            => 'contribution:mass-create',
    '/(.*)?addContribution/i'           => 'groupmanager',
    '/(at|de)tach_contribution/i'       => 'groupmanager',
    '/contributionDates/i'              => 'groupmanager',
    '/(.+)?contribution(.+)?/i'         => 'staff',
    '/(.*)?addTransaction/i'            => 'groupmanager',
    '/(.*)?editTransaction/i'           => 'groupmanager',
    '/doEditTransaction/i'              => 'staff',
    '/(.+)?transaction(.+)?/i'          => 'staff',
    // /Contributions rules
    // Members rules
    'me'                                => 'member',
    'member'                            => 'member',
    'pdf-members-cards'                 => 'member',
    'pdf-members-labels'                => 'member:print',
    'csv-memberslist'                   => 'member:export',
    'editMember'                        => 'member',
    'memberVCard'                       => 'member',
    '/(.+)?addMemberChild/i'            => 'member',
    //most of members routes are accessible to groups manager, including mass changes pages
    '/(.+)?member(.+)?/i'               => 'groupmanager',
    'ajaxGroupMembers'                  => 'staff',
    'duplicateMember'                   => 'member:manage', //and member:create, in controller
    'filterContributions'               => 'member',
    'adhesionForm'                      => 'member',
    'getDynamicFile'                    => 'member',
    // /Members rules
    // Groups rules
    'doAddGroup'                        => 'staff', //adding group is for staff only
    'pdf_groups'                        => 'group:export',
    '/(.+)?group(.+)?/i'                => 'groupmanager',
    // /Groups rules

    '/(.+)?text(.+)?/i'                 => 'staff',
    '/(.+)?status(.+)?/i'               => 'staff',
    '/(.+)?contributions?Types?(.+)?/i' => 'staff',
    '/(.+)?title(.+)?/i'                => 'staff',
    '/(.+)?reminder(.+)?/i'             => 'staff',
    '/(.+)?paymentType(.+)?/i'          => 'staff',
    '/(.+)?dynamicTranslation(.+)?/i'   => 'staff',
    'previewAttachment'                 => 'groupmanager',
    'getCsv'                            => 'staff',
    '/(store)?pdfModels/i'              => 'staff',
    'attendance_sheet_details'          => 'member:print',
    'attendance_sheet'                  => 'member:print',
    '/(.+)?document(.+)?/i'             => 'staff',
    '/(.+)?myScheduledPayments/i'       => 'member',
    '/(.+)?scheduledPayment(.+)?/i'     => 'staff'
];
