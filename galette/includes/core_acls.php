<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

//TODO: find a better way.
//Each route gets a level (superadmin, admin, staff, groupmanager or member) or a
//permission name ("domain:action"), checked through AccessControl. Permissions separated
//with a pipe ("domain:action|domain:other") grant access when any of them is granted.
//phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used on file inclusion
$core_acls = [
    // Main core rules.
    'impersonate'                       => 'superadmin',
    'unimpersonate'                     => 'member',
    //roles
    'roles'                             => 'role:manage',
    'storeRole'                         => 'role:manage',
    'editRole'                          => 'role:manage',
    'doEditRole'                        => 'role:manage',
    'removeRole'                        => 'role:manage',
    'doRemoveRole'                      => 'role:manage',
    'giveRole'                          => 'role:manage',
    'takeRole'                          => 'role:manage',
    'adminCredentials'                  => 'superadmin',
    'storeAdminCredentials'             => 'superadmin',
    'writeDarkCSS'                      => 'staff', //served to every visitor
    '/(.+)?admin(.+)?/i'                => 'superadmin',
    '/(.+)?[aA]dvancedConfig(.+)?/i'    => 'superadmin',
    '/(.+)?telemetry(.+)?/i'            => 'config:preferences',
    'setRegistered'                     => 'config:preferences',
    'authAttempts'                      => 'security:manage',
    'doAuthAttempts'                    => 'security:manage',
    '/(.+)?preferences(.+)?/i'          => 'config:preferences',
    '/(.+)?(Core|Dynamic|List)Field(.+)?/i'  => 'config:fields',
    //removal of references, exports and scheduled payments, before the removal rule below
    'removeStatus'                      => 'config:references',
    'doRemoveStatus'                    => 'config:references',
    'removeTitle'                       => 'config:references',
    'doRemoveTitle'                     => 'config:references',
    'removePaymentType'                 => 'config:references',
    'doRemovePaymentType'               => 'config:references',
    'removeContributionType'            => 'config:references',
    'doRemoveContributionType'          => 'config:references',
    'removeMember'                      => 'member:delete',
    'removeMembers'                     => 'member:delete',
    'doRemoveMember'                    => 'member:delete',
    'removeCsv'                         => 'data:export',
    'doRemoveCsv'                       => 'data:export',
    'removeScheduledPayment'            => 'contribution:schedule',
    'removeScheduledPayments'           => 'contribution:schedule',
    'doRemoveScheduledPayment'          => 'contribution:schedule',
    '/(.+)?removeSearch(.+)?/i'         => 'member',
    '/(.+)?remove(.+)?/i'               => 'staff', //per default, removal is limited to staff
    'advanced-search'                   => 'groupmanager',
    '/(.+)?search(.+)?/i'               => 'member',
    'testEmail'                         => 'config:preferences',
    'testEmailConnection'               => 'config:preferences',
    'dashboard'                         => 'member',
    'ajaxNews'                          => 'member', //dashboard news, displayed to whoever the dashboard is
    //every member manages their own second factor
    'two-factor-manage'                 => 'member',
    'two-factor-enrol'                  => 'member',
    'do-two-factor-enrol'               => 'member',
    'do-two-factor-disable'             => 'member',
    'do-two-factor-codes'               => 'member',
    'do-two-factor-reset'               => 'member:credentials',
    'sysinfos'                          => 'system:info',
    'charts'                            => 'system:info',
    'plugins'                           => 'plugin:manage',
    'pluginsActivation'                 => 'plugin:manage',
    'pluginInitDb'                      => 'plugin:manage',
    'pluginInfo'                        => 'plugin:manage',
    '/(.+)?plugin(.+)?/i'               => 'admin',
    'mailings'                          => 'mailing:history',
    'mailings_filter'                   => 'mailing:history',
    'removeMailing'                     => 'mailing:history',
    'doRemoveMailing'                   => 'mailing:history',
    '/(.+)?mailing(.+)?/i'              => 'staff',
    'mailing'                           => 'mailing:send',
    'doMailing'                         => 'mailing:send',
    //sent mailings are previewed from history, or by their sender
    'mailingPreview'                    => 'mailing:send|mailing:history',
    'previewAttachment'                 => 'mailing:send|mailing:history',
    'mailingRecipients'                 => 'mailing:send',
    'mailingQueue'                      => 'mailing:send',
    'mailingProcessQueue'               => 'mailing:send',
    '/(.+)?history(.+)?/i'              => 'history:manage',
    '/(.+)?import(.+)?/i'               => 'member:import',
    '/(.+)?export(.+)?/i'               => 'data:export',
    // /Main core rule
    // Contributions rules
    'contributions'                     => 'member',
    'printContribution'                 => 'member',
    'myContributions'                   => 'member',
    //members dropdown of contributions, transactions, members (parent) and roles forms
    'contributionMembers'               => 'contribution:create|contribution:edit|transaction:create|transaction:edit|member:manage',
    //contributions types, before the addContribution and editContribution rules below
    '/(.+)?contributions?Types?(.+)?/i' => 'config:references',
    //mass changes are for staff; the addContribution rule below would let groups managers in
    'massAddContributionsChooseType'    => 'contribution:mass-create',
    'massAddContributions'              => 'contribution:mass-create',
    'doMassAddContributions'            => 'contribution:mass-create',
    'masschangeContributions'           => 'contribution:edit',
    'masschangeContributionsReview'     => 'contribution:edit',
    'massstorecontributions'            => 'contribution:edit',
    '/(.*)?addContribution/i'           => 'contribution:create',
    '/(at|de)tach_contribution/i'       => 'transaction:attach',
    '/contributionDates/i'              => 'contribution:create|contribution:edit',
    '/(do)?editContribution/i'          => 'contribution:edit',
    //contributions and transactions share batch, export and removal routes; type is checked in controllers
    'batch-contributionslist'           => 'contribution:edit|contribution:delete|contribution:export|transaction:delete|transaction:export',
    'csv-contributionslist'             => 'contribution:export|transaction:export',
    'removeContribution'                => 'contribution:delete|transaction:delete',
    'removeContributions'               => 'contribution:delete|transaction:delete',
    'doRemoveContribution'              => 'contribution:delete|transaction:delete',
    '/(.+)?contribution(.+)?/i'         => 'staff',
    '/(.*)?addTransaction/i'            => 'transaction:create',
    //attaching contributions happens on transaction edit page
    'editTransaction'                   => 'transaction:create|transaction:edit|transaction:attach',
    'doEditTransaction'                 => 'transaction:edit',
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
    'ajaxGroupMembers'                  => 'group:edit', //persons are restricted to accessible members
    'duplicateMember'                   => 'member:manage', //and member:create, in controller
    'filterContributions'               => 'member',
    'adhesionForm'                      => 'member',
    'getDynamicFile'                    => 'member',
    // /Members rules
    // Groups rules
    'doAddGroup'                        => 'group:create',
    'removeGroup'                       => 'group:delete',
    'doRemoveGroup'                     => 'group:delete',
    'pdf_groups'                        => 'group:export',
    '/(.+)?group(.+)?/i'                => 'group:read',
    // /Groups rules

    '/(.+)?text(.+)?/i'                 => 'config:templates',
    '/(.+)?status(.+)?/i'               => 'config:references',
    '/(.+)?title(.+)?/i'                => 'config:references',
    '/(.+)?reminder(.+)?/i'             => 'contribution:remind',
    '/(.+)?paymentType(.+)?/i'          => 'config:references',
    '/(.+)?dynamicTranslation(.+)?/i'   => 'config:references',
    'getCsv'                            => 'data:export',
    '/(store)?pdfModels/i'              => 'config:templates',
    'attendance_sheet_details'          => 'member:print',
    'attendance_sheet'                  => 'member:print',
    'removeDocument'                    => 'document:manage',
    'doRemoveDocument'                  => 'document:manage',
    '/(.+)?document(.+)?/i'             => 'document:manage',
    '/(.+)?myScheduledPayments/i'       => 'member',
    '/(.+)?scheduledPayment(.+)?/i'     => 'contribution:schedule'
];
