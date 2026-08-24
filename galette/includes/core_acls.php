<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

//TODO: find a better way.
//phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used on file inclusion
$core_acls = [
    // Main core rules.
    'impersonate'                       => 'superadmin',
    'unimpersonate'                     => 'member',
    '/(.+)?admin(.+)?/i'                => 'superadmin',
    '/(.+)?[aA]dvancedConfig(.+)?/i'    => 'superadmin',
    '/(.+)?telemetry(.+)?/i'            => 'admin',
    'setRegistered'                     => 'admin',
    '/(.+)?preferences(.+)?/i'          => 'admin',
    '/(.+)?(Core|Dynamic|List)Field(.+)?/i'  => 'admin', //dynamic fields are for admins only
    '/(.+)?removeSearch(.+)?/i'         => 'member',
    '/(.+)?remove(.+)?/i'               => 'staff', //per default, removal is limited to staff
    'advanced-search'                   => 'groupmanager',
    '/(.+)?search(.+)?/i'               => 'member',
    'testEmail'                         => 'admin',
    'dashboard'                         => 'member',
    'sysinfos'                          => 'staff',
    'charts'                            => 'staff',
    '/(.+)?plugin(.+)?/i'               => 'admin',
    '/(.+)?mailing(.+)?/i'              => 'staff',
    'mailing'                           => 'groupmanager',
    'doMailing'                         => 'groupmanager',
    'mailingPreview'                    => 'groupmanager',
    'mailingRecipients'                 => 'groupmanager',
    '/(.+)?history(.+)?/i'              => 'staff',
    '/(.+)?import(.+)?/i'               => 'staff',
    '/(.+)?export(.+)?/i'               => 'staff',
    // /Main core rule
    // Contributions rules
    'contributions'                     => 'member',
    'printContribution'                 => 'member',
    'myContributions'                   => 'member',
    'contributionMembers'               => 'groupmanager',
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
    'editMember'                        => 'member',
    'memberVCard'                       => 'member',
    '/(.+)?addMemberChild/i'            => 'member',
    //most of members routes are accessible to groups manager, including mass changes pages
    '/(.+)?member(.+)?/i'               => 'groupmanager',
    'ajaxGroupMembers'                  => 'staff',
    'duplicateMember'                   => 'staff',
    'filterContributions'               => 'member',
    'adhesionForm'                      => 'member',
    'getDynamicFile'                    => 'member',
    // /Members rules
    // Groups rules
    'doAddGroup'                        => 'staff', //adding group is for staff only
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
    'attendance_sheet_details'          => 'groupmanager',
    'attendance_sheet'                  => 'groupmanager',
    '/(.+)?document(.+)?/i'             => 'staff',
    '/(.+)?myScheduledPayments/i'       => 'member',
    '/(.+)?scheduledPayment(.+)?/i'     => 'staff'
];
