<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Entity\Document;
use Galette\IO\News;
use Galette\Util\Release;
use Psr\Container\ContainerInterface;
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

    public const RSS_URL = 'https://galette.eu/dc/index.php/feed/atom';

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
        if (!str_contains($version, '-git')) {
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
     * @return array<string, bool|string|null>
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
         * @var ContainerInterface $container
         */
        global $login, $preferences, $plugins, $container;

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
                            'label' => _T('My scheduled payments'),
                            'title' => _T('View and filter all my scheduled payments'),
                            'route' => [
                                'name' => 'myScheduledPayments'
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
                $menus['members']['items'] = array_merge($menus['members']['items'], [
                    [
                        'label' => _T("Add a member"),
                        'title' => _T("Add new member in database"),
                        'route' => [
                            'name' => 'addMember'
                        ]
                    ],
                    [
                        'label' => _T("Empty adhesion form"),
                        'title' => _T("Download empty adhesion form"),
                        'route' => [
                            'name' => 'emptyAdhesionForm'
                        ]
                    ]
                ]);
            }

            // Contributions
            if ($login->isAdmin() || $login->isStaff() || $login->isGroupManager()) {
                $menus['contributions'] = [
                    'title' => _T('Contributions'),
                    'icon' => 'receipt',
                    'items' => []
                ];

                if ($preferences->pref_bool_groupsmanagers_see_contributions || $login->isAdmin() || $login->isStaff()) {
                    $menus['contributions']['items'] = array_merge($menus['contributions']['items'], [
                        [
                            'label' => _T("List of contributions"),
                            'title' => _T("View and filter contributions"),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'contributions'],
                                'aliases' => ['editContribution']
                            ]
                        ]
                    ]);
                }

                if ($login->isAdmin() || $login->isStaff()) {
                    $menus['contributions']['items'] = array_merge($menus['contributions']['items'], [
                        [
                            'label' => _T("List of scheduled payments"),
                            'title' => _T("View and filter scheduled payments"),
                            'route' => [
                                'name' => 'scheduledPayments',
                                'aliases' => ['addScheduledPayment', 'editScheduledPayment']
                            ]
                        ]
                    ]);
                }

                if ($preferences->pref_bool_groupsmanagers_see_transactions || $login->isAdmin() || $login->isStaff()) {
                    $menus['contributions']['items'] = array_merge($menus['contributions']['items'], [
                        [
                            'label' => _T("List of transactions"),
                            'title' => _T("View and filter transactions"),
                            'route' => [
                                'name' => 'contributions',
                                'args' => ['type' => 'transactions'],
                                'aliases' => ['editTransaction']
                            ]
                        ]
                    ]);
                }

                if ($preferences->pref_bool_groupsmanagers_create_contributions || $login->isAdmin() || $login->isStaff()) {
                    $menus['contributions']['items'] = array_merge($menus['contributions']['items'], [
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
                        ]
                    ]);
                }

                if ($preferences->pref_bool_groupsmanagers_create_transactions || $login->isAdmin() || $login->isStaff()) {
                    $menus['contributions']['items'] = array_merge($menus['contributions']['items'], [
                        [
                            'label' => _T("Add a transaction"),
                            'title' => _T("Add new transaction in database"),
                            'route' => [
                                'name' => 'addTransaction'
                            ]
                        ]
                    ]);
                }

                if ($login->isAdmin() || $login->isStaff()) {
                    $menus['contributions']['items'] = array_merge($menus['contributions']['items'], [
                        [
                            'label' => _T("Reminders"),
                            'title' => _T("Send reminders to late members"),
                            'route' => [
                                'name' => 'reminders'
                            ]
                        ]
                    ]);
                }
            } // /Contributions

            if ($login->isAdmin() || $login->isStaff() || $login->isGroupManager()) {
                $menus['management'] = [
                    'title' => _T("Management"),
                    'icon' => 'dharmachakra',
                    'items' => [
                        [
                            'label' => _T("Groups"),
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
                            'label' => _T("Documents"),
                            'title' => _T("Add documents to share related to your association (status, rules of procedure, ...)"),
                            'route' => [
                                'name' => 'documentsList',
                                'aliases' => ['editDocument', 'addDocument']
                            ]
                        ],
                        [
                            'label' => _T("Mailings"),
                            'title' => _T("Manage mailings that has been sent"),
                            'route' => [
                                'name' => 'mailings'
                            ]
                        ],
                        [
                            'label' => _T("Charts"),
                            'title' => _T("Various charts"),
                            'route' => [
                                'name' => 'charts'
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
                            'label' => _T("Logs"),
                            'title' => _T("View application's logs"),
                            'route' => [
                                'name' => 'history'
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
                                'label' => _T("Contributions types"),
                                'title' => _T("Manage contributions types"),
                                'route' => [
                                    'name' => 'contributionsTypes',
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
                                'label' => _T("User statuses"),
                                'route' => [
                                    'name' => 'status',
                                    'aliases' => ['editStatus']
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
                                'label' => _T("Emails content"),
                                'title' => _T("Manage emails texts and subjects"),
                                'route' => [
                                    'name' => 'texts'
                                ]
                            ],
                            [
                                'label' => _T("Labels translation"),
                                'title' => _T("Manage translatable labels"),
                                'route' => [
                                    'name' => 'dynamicTranslations',
                                    'aliases' => ['dynamicTranslation']
                                ]
                            ]
                        ]
                    ];

                    if ($login->isSuperAdmin()) {
                        $menus['configuration']['items'][] = [
                            'label' => _T("Administration tools"),
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
                /** @var GalettePlugin $plugin */
                $plugin = $container->get($plugin_class);
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
         * @var ContainerInterface $container
         */
        global $preferences, $login, $plugins, $container;

        if (!$preferences->arePublicPagesEnabled()) {
            return [];
        }

        $menus = [];
        $items = [];

        $galleries = [];
        $lists = [];

        if ($preferences->showPublicPage($login, 'pref_publicpages_visibility_memberslist')) {
            $lists[] = [
                'label' => _T("Members"),
                'route' => [
                    'name' => 'publicMembersList'
                ],
                'icon' => 'address book'
            ];
        }

        if ($preferences->showPublicPage($login, 'pref_publicpages_visibility_membersgallery')) {
            $galleries[] = [
                'label' => _T('Gallery'),
                'route' => [
                    'name' => 'publicMembersGallery'
                ],
                'icon' => 'images'
            ];
        }

        if ($preferences->showPublicPage($login, 'pref_publicpages_visibility_stafflist')) {
            $lists[] = [
                'label' => _T("Staff"),
                'route' => [
                    'name' => 'publicStaffList'
                ],
                'icon' => 'address book outline'
            ];
        }

        if ($preferences->showPublicPage($login, 'pref_publicpages_visibility_staffgallery')) {
            $galleries[] = [
                'label' => _T('Staff gallery'),
                'route' => [
                    'name' => 'publicStaffGallery'
                ],
                'icon' => 'images outline'
            ];
        }

        if (count($lists) > 1) {
            //handle multiple lists
            $items[] = [
                'label' => _T("Directories"),
                'icon' => 'address book',
                'children' => $lists
            ];
        } else {
            $items = array_merge($items, $lists);
        }

        if (count($galleries) > 1) {
            //handle multiple galleries
            $items[] = [
                'label' => _T("Galleries"),
                'icon' => 'images',
                'children' => $galleries
            ];
        } else {
            $items = array_merge($items, $galleries);
        }

        if ($preferences->showPublicPage($login, 'pref_publicpages_visibility_documents')) {
            $items[] = [
                'label' => _T("Documents"),
                'title' => _T("View documents related to your association"),
                'route' => [
                    'name' => 'documentsPublicList'
                ],
                'icon' => 'file alternate'
            ];
        }

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins public menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = $container->get($plugin_class);
                $items = array_merge(
                    $items,
                    $plugin->getPublicMenuItems()
                );
            }
        }

        if (count($items)) {
            $menus['public'] = [
                'title' => _T("Public pages"),
                'icon' => 'eye outline',
                'items' => $items
            ];
        }

        return $menus;
    }

    /**
     * Get current logged-in user dashboards
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getMyDashboards(): array
    {
        /**
         * @var Login $login
         * @var Plugins $plugins
         * @var ContainerInterface $container
         */
        global $login, $plugins, $container;

        $dashboards = [];
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
                    ]
                ]
            );
        }

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class) && method_exists($plugin_class, 'getMyDashboards')) {
                /** @var GalettePlugin $plugin */
                $plugin = $container->get($plugin_class);
                $dashboards = array_merge_recursive(
                    $dashboards,
                    $plugin->getMyDashboards()
                );
            }
        }

        return $dashboards;
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
         * @var Db $zdb
         * @var ContainerInterface $container
         */
        global $login, $plugins, $zdb, $container;

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

        //display documents menu if at least one document is present with current ACLs
        $document = new Document($zdb);
        $documents = $document->getList();
        if ($login->isSuperAdmin() || count($documents)) {
            $dashboards = array_merge(
                $dashboards,
                [
                    [
                        'label' => _T("Documents"),
                        'title' => _T("View documents related to your association"),
                        'route' => [
                            'name' => 'documentsPublicList'
                        ],
                        'icon' => 'dividers'
                    ]
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

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = $container->get($plugin_class);
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
         * @var ContainerInterface $container
         */
        global $login, $plugins, $container;

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
                    _T("Log in as %membername")
                ),
                'title' => str_replace(
                    "%membername",
                    $member->sname,
                    _T("Log in as %membername")
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
                $plugin = $container->get($plugin_class);
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
         * @var Plugins $plugins
         * @var ContainerInterface $container
         */
        global $plugins, $container;

        $actions = [];

        //TODO: add core detailed actions

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = $container->get($plugin_class);
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
         * @var ContainerInterface $container
         */
        global $login, $plugins, $preferences, $container;

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
                $plugin = $container->get($plugin_class);
                $actions = array_merge_recursive(
                    $actions,
                    $plugin->getBatchActions()
                );
            }
        }
        return $actions;
    }

    /**
     * Get Galette news
     *
     * @return array<int, News\Entry>
     */
    public static function getNews(): array
    {
        global $container;

        /**
         * @var Login $login
         * @var Preferences $preferences
         * @var Plugins $plugins
         */
        global $login, $preferences, $plugins;

        $news = [];

        //display Galette news for staff and admins
        if ($login->isStaff() || $login->isAdmin()) {
            $core_news = new News(self::RSS_URL);
            $entry = new News\Entry(
                _T("Galette news"),
                $core_news->getPosts(),
                50
            );
            $news[$entry->getPosition()] = $entry;
        }
        //if a custom RSS feed is set, we add it for everyone; and before Galette news
        if (!empty($preferences->pref_rss_url) && $preferences->pref_rss_url != self::RSS_URL) {
            $asso_news = new News($preferences->pref_rss_url);
            $entry = new News\Entry(
                _T("Association news"),
                $asso_news->getPosts(),
                20
            );
            $news[$entry->getPosition()] = $entry;
        }

        foreach (array_keys($plugins->getModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, true);
            if (class_exists($plugin_class)) {
                /** @var GalettePlugin $plugin */
                $plugin = $container->get($plugin_class);
                if ($plugin->isInstalled() && $entry = $plugin->getNews()) {
                    $position = $entry->getPosition();
                    while (isset($news[$position])) {
                        ++$position;
                    }
                    $news[$position] = $entry;
                }
            }
        }

        ksort($news);
        return $news;
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
        return @unserialize($string) !== false;
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
