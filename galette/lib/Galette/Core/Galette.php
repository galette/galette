<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette application instance
 *
 * PHP version 5
 *
 * Copyright © 2020-2022 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9.4-dev - 2020-05-18
 */

namespace Galette\Core;

/**
 * Galette application instance
 *
 * @category  Core
 * @name      Galette
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9.4-dev - 2020-05-18
 */
class Galette
{
    public const MODE_PROD = 'PROD';
    public const MODE_DEV = 'DEV';
    public const MODE_MAINT = 'MAINT';
    public const MODE_DEMO = 'DEMO';

    /**
     * Retrieve Galette version from git, if present.
     *
     * @param boolean $time Include time and timezone. Defaults to false.
     *
     * @return string
     */
    public static function gitVersion($time = false)
    {
        $galette_version = GALETTE_VERSION;

        //used for both gith and nightly installs
        $version = str_replace('-dev', '-git', GALETTE_VERSION);
        if (strstr($version, '-git') === false) {
            $version .= '-git';
        }

        if (is_dir(GALETTE_ROOT . '../.git')) {
            $commitHash = trim(exec('git log --pretty="%h" -n1 HEAD'));

            $commitDate = new \DateTime(trim(exec('git log -n1 --pretty=%ci HEAD')));

            $galette_version = sprintf(
                '%s-%s (%s)',
                $version,
                $commitHash,
                $commitDate->format(($time ? 'Y-m-d H:i:s T' : 'Y-m-d'))
            );
        } elseif (GALETTE_NIGHTLY !== false) {
            $galette_version = $version . '-' . GALETTE_NIGHTLY;
        }
        return $galette_version;
    }

    /**
     * Get menus
     *
     * @return array
     */
    public static function getMenus(): array
    {
        /** @var Login $login */
        global $login, $preferences;

        $menus = [];

        if ($login->isLogged()) {
            if (!$login->isSuperAdmin()) {
                //member menu
                $menus['myaccount'] = [
                    'title' => _T("My Account"),
                    'icon' => 'user',
                    'items' => [
                        [
                            'label' => _T('My contributions'),
                            'title' => _T('View and filter all my contributions'),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'contributions']
                            ]
                        ],
                        [
                            'label' => _T('My transactions'),
                            'title' => _T('View and filter all my transactions'),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'transactions']
                            ]
                        ],
                        [
                            'label' => _T('My information'),
                            'title' => _T('View my member card'),
                            'route' => [
                                'name' => 'me',
                                'args' => []
                            ]
                        ]
                    ]
                ];

                if ($preferences->pref_bool_create_member) {
                    $menus['myaccount']['items'][] = [
                        'label' => _T('Add a child member'),
                        'title' => _T('Add new child member in database'),
                        'route' => [
                            'name' => 'addMemberChild',
                            'args' => []
                        ]
                    ];
                }
            }

            $menus['members'] = [
                'title' => _T("Members"),
                'icon' => 'users',
                'items' => []
            ];

            if ($login->isAdmin() || $login->isStaff() || $login->isGroupManager()) {
                $menus['members']['items'] = [
                    [
                        'label' => _T("List of members"),
                        'title' => _T("View, search into and filter member's list"),
                        'route' => [
                            'name' => 'members',
                            'args' => []
                        ]
                    ],
                    [
                        'label' => _T("Advanced search"),
                        'title' => _T("Perform advanced search into members list"),
                        'route' => [
                            'name' => 'advanced-search',
                            'args' => []
                        ]
                    ],
                    [
                        'label' => _T("Saved searches"),
                        'title' => _T("Saved searches"),
                        'route' => [
                            'name' => 'searches',
                            'args' => []
                        ]
                    ]
                ];
            }

            if (
                !$login->isSuperAdmin()
                && $login->isAdmin()
                || $login->isStaff()
                || ($login->isGroupManager() && $preferences->pref_bool_groupsmanagers_create_member)
            ) {
                $menus['members']['items'][] = [
                    'label' => _T("Add a member"),
                    'title' => _T("Add new member in database"),
                    'route' => [
                        'name' => 'addMember'
                    ]
                ];
            }

            if ($login->isAdmin() || $login->isStaff()) {
                $menus['contributions'] = [
                    'title' => _T('Contributions'),
                    'icon' => 'cookie',
                    'items' => [
                        [
                            'label' => _T("List of contributions"),
                            'title' => _T("View and filter contributions"),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'contributions']
                            ]
                        ],
                        [
                            'label' => _T("List of transactions"),
                            'title' => _T("View and filter transactions"),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'transactions']
                            ]
                        ],
                        [
                            'label' => _T("Add a membership fee"),
                            'title' => _T("Add new membership fee in database"),
                            'route' => [
                                'name' => 'addContribution',
                                'args' => ['type' => 'fee']
                            ]
                        ],
                        [
                            'label' => _T("Add a donation"),
                            'title' => _T("Add new donation in database"),
                            'route' => [
                                'name' => 'addContribution',
                                'args' => ['type' => 'donation']
                            ]
                        ],
                        [
                            'label' => _T("Add a transaction"),
                            'title' => _T("Add new transaction in database"),
                            'route' => [
                                'name' => 'addTransaction'
                            ]
                        ],
                        [
                            'label' => _T("Reminders"),
                            'title' => _T("Send reminders to late members"),
                            'route' => [
                                'name' => 'reminders'
                            ]
                        ]
                    ]
                ];
            } //admin or staff

            if ($login->isAdmin() || $login->isStaff() || $login->isGroupManager()) {
                $menus['management'] = [
                    'title' => _T("Management"),
                    'icon' => 'dharmachakra',
                    'items' => [
                        [
                            'label' => _T("Manage groups"),
                            'title' => _T("View and manage groups"),
                            'route' => [
                                'name' => 'groups'
                            ]
                        ]
                    ]
                ];

                if ($login->isAdmin() || $login->isStaff()) {
                    $menus['management']['items'] = array_merge($menus['management']['items'], [
                        [
                            'label' => _T("Logs"),
                            'title' => _T("View application's logs"),
                            'route' => [
                                'name' => 'history'
                            ]
                        ],
                        [
                            'label' => _T("Manage mailings"),
                            'title' => _T("Manage mailings that has been sent"),
                            'route' => [
                                'name' => 'mailings',
                                'aliases' => ['mailing']
                            ]
                        ],
                        [
                            'label' => _T("Exports"),
                            'title' => _T("Export some data in various formats"),
                            'route' => [
                                'name' => 'export'
                            ]
                        ],
                        [
                            'label' => _T("Imports"),
                            'title' => _T("Import members from CSV files"),
                            'route' => [
                                'name' => 'import'
                            ]
                        ],
                        [
                            'label' => _T("Charts"),
                            'title' => _T("Various charts"),
                            'route' => [
                                'name' => 'charts'
                            ]
                        ]
                    ]);
                }//admin or staff

                if ($login->isAdmin()) {
                    $menus['configuration'] = [
                        'title' => _T("Configuration"),
                        'icon' => 'tools',
                        'items' => [
                            [
                                'label' => _T("Settings"),
                                'title' => _T("Set applications preferences (address, website, member's cards configuration, ...)"),
                                'route' => [
                                    'name' => 'preferences'
                                ]
                            ],
                            [
                                'label' => _T("Plugins"),
                                'title' => _T("Information about available plugins"),
                                'route' => [
                                    'name' => 'plugins'
                                ]
                            ],
                            [
                                'label' => _T("Core lists"),
                                'title' => _T("Customize lists fields and order"),
                                'route' => [
                                    'name' => 'configureListFields',
                                    'args' => ['table' => 'adherents']
                                ]
                            ],
                            [
                                'label' => _T("Core fields"),
                                'title' => _T("Customize fields order, set which are required, and for who they're visibles"),
                                'route' => [
                                    'name' => 'configureCoreFields'
                                ]
                            ],
                            [
                                'label' => _T("Dynamic fields"),
                                'title' => _T("Manage additional fields for various forms"),
                                'route' => [
                                    'name' => 'configureDynamicFields',
                                    'alias' => 'editDynamicField'
                                ]
                            ],
                            [
                                'label' => _T("Translate labels"),
                                'title' => _T("Translate additionnals fields labels"),
                                'route' => [
                                    'name' => 'dynamicTranslations'
                                ]
                            ],
                            [
                                'label' => _T("Manage statuses"),
                                'title' => _T("Manage statuses"),
                                'route' => [
                                    'name' => 'entitleds',
                                    'args' => ['class' => 'status']
                                ]
                            ],
                            [
                                'label' => _T("Contributions types"),
                                'title' => _T("Manage contributions types"),
                                'route' => [
                                    'name' => 'entitleds',
                                    'args' => ['class' => 'contributions-types']
                                ]
                            ],
                            [
                                'label' => _T("Emails content"),
                                'title' => _T("Manage emails texts and subjects"),
                                'route' => [
                                    'name' => 'texts'
                                ]
                            ],
                            [
                                'label' => _T("Emails content"),
                                'title' => _T("Manage emails texts and subjects"),
                                'route' => [
                                    'name' => 'texts'
                                ]
                            ],
                            [
                                'label' => _T("Titles"),
                                'title' => _T("Manage titles"),
                                'route' => [
                                    'name' => 'titles'
                                ]
                            ],
                            [
                                'label' => _T("PDF models"),
                                'title' => _T("Manage PDF models"),
                                'route' => [
                                    'name' => 'pdfModels'
                                ]
                            ],
                            [
                                'label' => _T("Payment types"),
                                'title' => _T("Manage payment types"),
                                'route' => [
                                    'name' => 'paymentTypes'
                                ]
                            ],
                            [
                                'label' => _T("Empty adhesion form"),
                                'title' => _T("Download empty adhesion form"),
                                'route' => [
                                    'name' => 'emptyAdhesionForm'
                                ]
                            ]
                        ]
                    ];

                    if ($login->isSuperAdmin()) {
                        $menus['configuration']['items'][] = [
                            'label' => _T("Admin tools"),
                            'title' => _T("Various administrative tools"),
                            'route' => [
                                'name' => 'adminTools'
                            ]
                        ];
                    }
                }
            }
        } // /isLogged

        return $menus;
    }

    /**
     * Get public menus
     *
     * @return array
     */
    public static function getPublicMenus(): array
    {
        global $preferences, $login;

        $menus = [];
        if ($preferences->showPublicPages($login)) {
            $menus['public'] = [
                'title' => _T("Public pages"),
                'icon' => 'eye outline',
                'items' => [
                    [
                        'label' => _T("Members list"),
                        'title' => _T("Members list"),
                        'route' => [
                            'name' => 'publicList',
                            'args' => ['type' => 'list']
                        ],
                        'icon' => 'address book'
                    ],
                    [
                        'label' => _T("Trombinoscope"),
                        'title' => _T("Trombinoscope"),
                        'route' => [
                            'name' => 'publicList',
                            'args' => ['type' => 'trombi']
                        ],
                        'icon' => 'user friends'
                    ]
                ]
            ];
        }

        return $menus;
    }
}
