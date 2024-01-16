<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galettelette core ACLs
 *
 * PHP version 5
 *
 * Copyright © 2020-2023 The Galette Team
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
 * @category  Functions
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     0.9.4dev - 2020-05-10
 */

$core_acls = [
    // Main core rules.
    'impersonate'                       => 'superadmin',
    'unimpersonate'                     => 'member',
    '/(.+)?admin(.+)?/i'                => 'superadmin',
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
    '/(.+)?contribution(.+)?/i'         => 'staff',
    '/(.+)?transaction(.+)?/i'          => 'staff',
    // /Contributions rules
    // Members rules
    'me'                                => 'member',
    'member'                            => 'member',
    'pdf-members-cards'                 => 'member',
    'editMember'                        => 'member',
    'addMemberChild'                    => 'member',
    //most of members routes are accessible to groups manager, including mass changes pages
    '/(.+)?member(.+)?/i'               => 'groupmanager',
    'ajaxGroupMembers'                  => 'staff',
    'duplicateMember'                   => 'staff',
    'payments_filter'                   => 'member',
    'adhesionForm'                      => 'member',
    'getDynamicFile'                    => 'member',
    'photoDnd'                          => 'staff',
    // /Members rules
    // Groups rules
    '/(.+)?group(.+)?/i'                => 'groupmanager',
    'add_group'                         => 'staff', //adding group is for staff only
    // /Groups rules

    '/(.+)?text(.+)?/i'                 => 'staff',
    '/(.+)?entitled(.+)?/i'             => 'staff',
    '/(.+)?title(.+)?/i'                => 'staff',
    '/(.+)?reminder(.+)?/i'             => 'staff',
    '/(.+)?paymentType(.+)?/i'          => 'staff',
    '/(.+)?dynamicTranslation(.+)?/i'   => 'staff',
    'previewAttachment'                 => 'groupmanager',
    'getCsv'                            => 'staff',
    'pdfModels'                         => 'staff',
    'attendance_sheet_details'          => 'groupmanager',
    'attendance_sheet'                  => 'groupmanager',
];
