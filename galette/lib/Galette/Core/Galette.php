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

namespace Galette\Core;

use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Util\Release;
use RuntimeException;

/**
 * Galette application instance
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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
    public static function gitVersion(bool $time = false): string
    {
        $galette_version = GALETTE_VERSION;

        //used for both git and nightly installs
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
        } elseif (static::isNightly()) {
            $galette_version = $version . '-' . GALETTE_NIGHTLY;
        }
        return $galette_version;
    }

    /**
     * Get Galette new release
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getNewRelease(): array
    {
        $release = new Release();
        return [
            'new' => $release->checkNewRelease(),
            'version' => $release->getLatestRelease()
        ];
    }

    /**
     * Get all menus
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getAllMenus(): array
    {
        return static::getMenus(true);
    }

    /**
     * Get menus
     *
     * @param bool $public Include public menus. Defaults to false
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getMenus(bool $public = false): array
    {
        /**
         * @var Login $login
         * @var Preferences $preferences
         * @var Plugins $plugins
         */
        global $login, $preferences, $plugins;

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
                                'name' => 'myContributions',
                                'args' => ['type' => 'contributions']
                            ]
                        ],
                        [
                            'label' => _T('My transactions'),
                            'title' => _T('View and filter all my transactions'),
                            'route' => [
                                'name' => 'myContributions',
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
                            'aliases' => ['editMember', 'member']
                        ]
                    ],
                    [
                        'label' => _T("Advanced search"),
                        'title' => _T("Perform advanced search into members list"),
                        'route' => [
                            'name' => 'advanced-search'
                        ]
                    ],
                    [
                        'label' => _T("Saved searches"),
                        'route' => [
                            'name' => 'searches'
                        ]
                    ]
                ];
            }

            if (
                $login->isAdmin()
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
                    'icon' => 'receipt',
                    'items' => [
                        [
                            'label' => _T("List of contributions"),
                            'title' => _T("View and filter contributions"),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'contributions'],
                                'aliases' => ['editContribution']
                            ]
                        ],
                        [
                            'label' => _T("List of transactions"),
                            'title' => _T("View and filter transactions"),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'transactions'],
                                'aliases' => ['editTransaction']
                            ]
                        ],
                        [
                            'label' => _T("Add a membership fee"),
                            'title' => _T("Add new membership fee in database"),
                            'route' => [
                                'name' => 'addContribution',
                                'args' => ['type' => \Galette\Entity\Contribution::TYPE_FEE]
                            ]
                        ],
                        [
                            'label' => _T("Add a donation"),
                            'title' => _T("Add new donation in database"),
                            'route' => [
                                'name' => 'addContribution',
                                'args' => ['type' => \Galette\Entity\Contribution::TYPE_DONATION]
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
                                'name' => 'mailings'
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
                                'name' => 'import',
                                'aliases' => ['importModel']
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
                                    'aliases' => ['editDynamicField'],
                                ]
                            ],
                            [
                                'label' => _T("Translate labels"),
                                'title' => _T("Translate additional fields labels"),
                                'route' => [
                                    'name' => 'dynamicTranslations'
                                ]
                            ],
                            [
                                'label' => _T("Manage statuses"),
                                'route' => [
                                    'name' => 'status',
                                    'aliases' => ['editStatus'],
                                    'sub_select' => false
                                ]
                            ],
                            [
                                'label' => _T("Contributions types"),
                                'title' => _T("Manage contributions types"),
                                'route' => [
                                    'name' => 'contributionsTypes',
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
                                    'name' => 'titles',
                                    'aliases' => ['editTitle']
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
                                    'name' => 'paymentTypes',
                                    'aliases' => ['editPaymentType']
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

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                $plugin = new $plugin_class();
                $menus = array_merge_recursive(
                    $menus,
                    $plugin->getMenus()
                );
            }
        }

        if ($public) {
            $menus += static::getPublicMenus();
        }

        //cleanup empty entries (no items)
        foreach ($menus as $key => $menu) {
            if (!count($menu['items'])) {
                unset($menus[$key]);
            }
        }

        return $menus;
    }

    /**
     * Get public menus
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getPublicMenus(): array
    {
        /**
         * @var Preferences $preferences
         * @var Login $login
         * @var Plugins $plugins
         */
        global $preferences, $login, $plugins;

        $menus = [];
        if ($preferences->showPublicPages($login)) {
            $menus['public'] = [
                'title' => _T("Public pages"),
                'icon' => 'eye outline',
                'items' => [
                    [
                        'label' => _T("Members list"),
                        'route' => [
                            'name' => 'publicList',
                            'args' => ['type' => 'list']
                        ],
                        'icon' => 'address book'
                    ],
                    [
                        'label' => _T("Trombinoscope"),
                        'route' => [
                            'name' => 'publicList',
                            'args' => ['type' => 'trombi']
                        ],
                        'icon' => 'user friends'
                    ]
                ]
            ];

            foreach (array_keys($plugins->getModules()) as $module_id) {
                //get plugins public menus entries
                $plugin_class = $plugins->getClassName($module_id, true);
                if (class_exists($plugin_class)) {
                    $plugin = new $plugin_class();
                    $menus['public']['items'] = array_merge(
                        $menus['public']['items'],
                        $plugin->getPublicMenuItems()
                    );
                }
            }
        }

        return $menus;
    }

    /**
     * Get dashboards
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getDashboards(): array
    {
        /**
         * @var Login $login
         * @var Plugins $plugins
         */
        global $login, $plugins;

        $dashboards = [];

        if ($login->isAdmin() || $login->isStaff() || $login->isGroupManager()) {
            $dashboards = array_merge(
                $dashboards,
                [
                    [
                        'label' => _T("Members"),
                        'title' => _T("View, search into and filter member's list"),
                        'route' => [
                            'name' => 'members'
                        ],
                        'icon' => 'card_box'
                    ],
                    [
                        'label' => _T("Groups"),
                        'title' => _T("View and manage groups"),
                        'route' => [
                            'name' => 'groups'
                        ],
                        'icon' => 'busts_in_silhouette'
                    ],
                ]
            );
        }

        if ($login->isAdmin() || $login->isStaff()) {
            $dashboards = array_merge(
                $dashboards,
                [
                    [
                        'label' => _T("Mailings"),
                        'title' => _T("Manage mailings that has been sent"),
                        'route' => [
                            'name' => 'mailings'
                        ],
                        'icon' => 'postbox'
                    ],
                    [
                        'label' => _T("Contributions"),
                        'title' => _T("View and filter contributions"),
                        'route' => [
                            'name' => 'contributions',
                            'args' => ['type' => 'contributions']
                        ],
                        'icon' => 'receipt'
                    ],
                    [
                        'label' => _T("Transactions"),
                        'title' => _T("View and filter transactions"),
                        'route' => [
                            'name' => 'contributions',
                            'args' => ['type' => 'transactions']
                        ],
                        'icon' => 'book'
                    ],
                    [
                        'label' => _T("Reminders"),
                        'title' => _T("Send reminders to late members"),
                        'route' => [
                            'name' => 'reminders'
                        ],
                        'icon' => 'bell'
                    ],
                ]
            );
        }

        if ($login->isAdmin()) {
            $dashboards = array_merge(
                $dashboards,
                [
                    [
                        'label' => _T("Settings"),
                        'title' => _T("Set applications preferences (address, website, member's cards configuration, ...)"),
                        'route' => [
                            'name' => 'preferences'
                        ],
                        'icon' => 'control_knobs'
                    ],
                    [
                        'label' => _T("Plugins"),
                        'title' => _T("Information about available plugins"),
                        'route' => [
                            'name' => 'plugins'
                        ],
                        'icon' => 'package'
                    ],
                ]
            );
        }

        if ($login->isLogged() && !$login->isSuperAdmin()) {
            // Single member
            $dashboards = array_merge(
                $dashboards,
                [
                    [
                        'label' => _T("My information"),
                        'title' => _T("View my member card"),
                        'route' => [
                            'name' => 'me'
                        ],
                        'icon' => 'bust_in_silhouette'
                    ],
                    [
                        'label' => _T("My contributions"),
                        'title' => _T("View and filter all my contributions"),
                        'route' => [
                            'name' => 'myContributions',
                            'args' => ['type' => 'contributions']
                        ],
                        'icon' => 'receipt'
                    ],
                    [
                        'label' => _T("My transactions"),
                        'title' => _T("View and filter all my transactions"),
                        'route' => [
                            'name' => 'myContributions',
                            'args' => ['type' => 'transactions']
                        ],
                        'icon' => 'book'
                    ],

                ]
            );
        }

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = new $plugin_class();
                $dashboards = array_merge_recursive(
                    $dashboards,
                    $plugin->getDashboards()
                );
            }
        }

        return $dashboards;
    }

    /**
     * Get members list actions
     *
     * @param Adherent $member Current member
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getListActions(Adherent $member): array
    {
        /**
         * @var Login $login
         * @var Plugins $plugins
         */
        global $login, $plugins;

        $actions = [];

        if ($member->canEdit($login)) {
            $actions[] = [
                'label' => str_replace(
                    "%membername",
                    $member->sname,
                    _T("%membername: edit information")
                ),
                'title' => str_replace(
                    "%membername",
                    $member->sname,
                    _T("%membername: edit information")
                ),
                'route' => [
                    'name' => 'editMember',
                    'args' => ['id' => $member->id]
                ],
                'icon' => 'user edit'
            ];
        }

        if ($login->isAdmin() || $login->isStaff()) {
            $actions = array_merge($actions, [
                [
                    'label' => str_replace(
                        "%membername",
                        $member->sname,
                        _T("%membername: contributions")
                    ),
                    'title' => str_replace(
                        "%membername",
                        $member->sname,
                        _T("%membername: contributions")
                    ),
                    'route' => [
                        'name' => 'contributions',
                        'args' => [
                            "type" => "contributions",
                            "option" => "member",
                            'value' => $member->id
                        ]
                    ],
                    'icon' => 'receipt green'
                ],
                [
                    'label' => str_replace(
                        "%membername",
                        $member->sname,
                        _T("%membername: remove from database")
                    ),
                    'title' => str_replace(
                        "%membername",
                        $member->sname,
                        _T("%membername: remove from database")
                    ),
                    'route' => [
                        'name' => 'removeMember',
                        'args' => [
                            'id' => $member->id
                        ]
                    ],
                    'icon' => 'user times red',
                    'extra_class' => 'delete'
                ]
            ]);
        }

        if ($login->isSuperAdmin()) {
            $actions[] = [
                'label' => str_replace(
                    "%membername",
                    $member->sname,
                    _T("Log in in as %membername")
                ),
                'title' => str_replace(
                    "%membername",
                    $member->sname,
                    _T("Log in in as %membername")
                ),
                'route' => [
                    'name' => 'impersonate',
                    'args' => [
                        'id' => $member->id
                    ]
                ],
                'icon' => 'user secret grey'
            ];
        }

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = new $plugin_class();
                $actions = array_merge_recursive(
                    $actions,
                    $plugin->getListActions($member)
                );
            }
        }
        return $actions;
    }

    /**
     * Get member show actions
     *
     * @param Adherent $member Current member
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getDetailedActions(Adherent $member): array
    {
        /**
         * @var Login $login
         * @var Plugins $plugins
         */
        global $login, $plugins;

        $actions = [];

        //TODO: add core detailed actions

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = new $plugin_class();
                $actions = array_merge_recursive(
                    $actions,
                    $plugin->getDetailedActions($member)
                );
            }
        }
        return $actions;
    }

    /**
     * Get members list batch actions
     *
     * @return array<string,array<string, string>>
     */
    public static function getBatchActions(): array
    {
        /**
         * @var Login $login
         * @var Plugins $plugins
         * @var Preferences $preferences
         */
        global $login, $plugins, $preferences;

        $actions = [];

        if (
            $login->isAdmin()
            || $login->isStaff()
        ) {
            $actions = array_merge(
                $actions,
                [
                    [
                        'name' => 'masschange',
                        'label' => _T('Mass change'),
                        'icon' => 'user edit blue'
                    ],
                    [
                        'name' => 'masscontributions',
                        'label' => _T('Mass add contributions'),
                        'icon' => 'receipt bite green'
                    ],
                    [
                        'name' => 'delete',
                        'label' => _T('Delete'),
                        'icon' => 'user times red'
                    ]
                ]
            );
        }

        if (
            ($login->isAdmin()
            || $login->isStaff()
            || $login->isGroupManager()
            && $preferences->pref_bool_groupsmanagers_mailings)
            && $preferences->pref_mail_method != \Galette\Core\GaletteMail::METHOD_DISABLED
        ) {
            $actions[] = [
                'name' => 'sendmail',
                'label' => _T('Mail'),
                'icon' => 'mail bulk'
            ];
        }

        if (
            $login->isGroupManager()
            && $preferences->pref_bool_groupsmanagers_exports
            || $login->isAdmin()
            || $login->isStaff()
        ) {
            $actions = array_merge(
                $actions,
                [
                    [
                        'name' => 'attendance_sheet',
                        'label' => _T('Attendance sheet'),
                        'icon' => 'file alternate'
                    ],
                    [
                        'name' => 'labels__directdownload',
                        'label' => _T('Generate labels'),
                        'icon' => 'address card'
                    ],
                    [
                        'name' => 'cards__directdownload',
                        'label' => _T('Generate Member Cards'),
                        'icon' => 'id badge'
                    ],
                    [
                        'name' => 'csv__directdownload',
                        'label' => _T('Export as CSV'),
                        'icon' => 'file csv'
                    ],
                ]
            );
        }

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = new $plugin_class();
                $actions = array_merge_recursive(
                    $actions,
                    $plugin->getBatchActions()
                );
            }
        }
        return $actions;
    }

    /**
     * Is demonstration mode enabled
     *
     * @return bool
     */
    public static function isDemo(): bool
    {
        return GALETTE_MODE === static::MODE_DEMO;
    }

    /**
     * Is debug mode enabled
     *
     * @return bool
     */
    public static function isDebugEnabled(): bool
    {
        if (GALETTE_MODE === static::MODE_DEV) {
            //since 1.1.0, GALETTE_MODE with DEV value is deprecated.
            Analog::log(
                'Using GALETTE_MODE set to DEV is deprecated. Use GALETTE_DEBUG.',
                Analog::WARNING
            );
            return true;
        }
        // @const bool GALETTE_DEBUG
        return GALETTE_DEBUG === true;
    }

    /**
     * Is SQL debug mode enabled
     *
     * @return bool
     */
    public static function isSqlDebugEnabled(): bool
    {
        return defined('GALETTE_SQL_DEBUG') || static::isDebugEnabled();
    }

    /**
     * Is a nightly build
     *
     * @return bool
     */
    public static function isNightly(): bool
    {
        return GALETTE_NIGHTLY !== false;
    }

    /**
     * Check if a string is serialized
     *
     * @param string $string String to check
     *
     * @return bool
     */
    public static function isSerialized(string $string): bool
    {
        return (@unserialize($string) !== false);
    }

    /**
     * JSON decode with exception
     *
     * @param string $string JSON encoded string to decode
     *
     * @return array<string|int, mixed>
     * @throws RuntimeException
     */
    public static function jsonDecode(string $string): array
    {
        $decoded = json_decode($string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * JSON encode with exception
     *
     * @param array<string|int, mixed>|object $data Data to encode
     *
     * @return string
     * @throws RuntimeException
     */
    public static function jsonEncode(array|object $data): string
    {
        $encoded = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON encode error: ' . json_last_error_msg());
        }

        return $encoded;
    }
}
