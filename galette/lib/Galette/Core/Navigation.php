<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Closure;
use Galette\Access\AccessControl;
use Galette\Entity\Adherent;
use Galette\IO\News;
use Galette\Repository\Documents;
use Galette\Util\Release;

/**
 * Menus, dashboards, members actions, news and release notices
 *
 * Core entries are completed with those of active plugins.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Navigation
{
    /**
     * Constructor
     *
     * @param Db                 $zdb         Database instance
     * @param Login              $login       Login instance
     * @param Preferences        $preferences Preferences instance
     * @param Plugins            $plugins     Plugins instance
     * @param AccessControl      $access      Access control
     * @param Closure(): Release $release     Releases checker provider; it may reach the network
     *                                        when built, it is only built when needed
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Login $login,
        private readonly Preferences $preferences,
        private readonly Plugins $plugins,
        private readonly AccessControl $access,
        private readonly Closure $release
    ) {
    }

    /**
     * Get Galette new release
     *
     * @return array<string, bool|string|null>
     */
    public function getNewRelease(): array
    {
        if (defined('GALETTE_TESTS') || getenv('GALETTE_TESTS')) {
            return [
                'new' => false,
                'version' => GALETTE_VERSION
            ];
        }

        $release = $this->getRelease();
        return [
            'new' => $release->checkNewRelease(),
            'version' => $release->getLatestRelease()
        ];
    }

    /**
     * Get plugins for which a new release is available
     *
     * @return array<string, array{name: string, current: string, latest: string, url: string}>
     */
    public function getPluginsUpdates(): array
    {
        if (defined('GALETTE_TESTS') || getenv('GALETTE_TESTS')) {
            return [];
        }

        try {
            return $this->getRelease()->getPluginsUpdates();
        } catch (\Throwable $e) {
            Logs::exception($e, 'Error looking for new plugins releases', Analog::ERROR);
            return [];
        }
    }

    /**
     * Get releases checker, shared with the rest of the request when possible
     */
    private function getRelease(): Release
    {
        return ($this->release)();
    }

    /**
     * Get all menus
     *
     * @return array<string, string|array<string,mixed>>
     */
    public function getAllMenus(): array
    {
        return $this->getMenus(public: true);
    }

    /**
     * Get menus
     *
     * @param bool $public Include public menus. Defaults to false
     *
     * @return array<string, string|array<string,mixed>>
     */
    public function getMenus(bool $public = false): array
    {

        $menus = [];

        if ($this->login->isLogged()) {
            $menus['myaccount'] = [
                'title' => _T("My Account"),
                'icon' => 'user',
                'items' => []
            ];

            if (!$this->login->isSuperAdmin()) {
                //member menu
                $menus['myaccount']['items'] = [
                    [
                        'label' => _T('My information'),
                        'title' => _T('View my member card'),
                        'route' => [
                            'name' => 'me',
                            'args' => []
                        ]
                    ],
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
                    ]
                ];
            } else {
                $menus['myaccount']['items'][] = [
                    'label' => _T('My information'),
                    'title' => _T('Modify my login and password'),
                    'route' => [
                        'name' => 'adminCredentials',
                        'args' => []
                    ]
                ];
            }

            if (!$this->login->isSuperAdmin() && $this->access->can('member:create-child', login: $this->login)) {
                $menus['myaccount']['items'][] = [
                    'label' => _T('Add a child member'),
                    'title' => _T('Add new child member in database'),
                    'route' => [
                        'name' => 'addMemberChild',
                        'args' => []
                    ]
                ];
            }

            //outside the member menu block above: the super administrator is not a
            //member, but it does hold a second factor of its own. That one lives in
            //the preferences, which are read only in the settings -- no form may
            //write them -- so this entry is the only way in from the interface.
            if (TwoFactorAuth::modeFrom($this->preferences) !== TwoFactorAuth::MODE_DISABLED) {
                $menus['myaccount']['items'][] = [
                    'label' => _T('Two-factor authentication'),
                    'title' => _T('Manage my two-factor authentication'),
                    'route' => [
                        'name' => 'two-factor-manage',
                        'args' => []
                    ]
                ];
            }

            $menus['members'] = [
                'title' => _T("Members"),
                'icon' => 'users',
                'items' => []
            ];

            if ($this->access->getGroupScope('member:read', $this->login) !== []) {
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

            if ($this->access->can('member:create', login: $this->login)) {
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
            $menus['contributions'] = [
                'title' => _T('Contributions'),
                'icon' => 'receipt',
                'items' => []
            ];

            if ($this->access->getGroupScope('contribution:read', $this->login) !== []) {
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

            if ($this->access->can('contribution:schedule', login: $this->login)) {
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

            if ($this->access->getGroupScope('transaction:read', $this->login) !== []) {
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

            if ($this->access->can('contribution:create', login: $this->login)) {
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

            if ($this->access->can('transaction:create', login: $this->login)) {
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

            if ($this->access->can('contribution:remind', login: $this->login)) {
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
            // /Contributions

            $management = [
                ['group:read', [
                    'label' => _T("Groups"),
                    'title' => _T("View and manage groups"),
                    'route' => [
                        'name' => 'groups',
                        'aliases' => ['editGroup']
                    ]
                ]],
                ['document:manage', [
                    'label' => _T("Documents"),
                    'title' => _T("Add documents to share related to your association (status, rules of procedure, ...)"),
                    'route' => [
                        'name' => 'documentsList',
                        'aliases' => ['editDocument', 'addDocument']
                    ]
                ]],
                ['mailing:history', [
                    'label' => _T("Mailings"),
                    'title' => _T("Manage mailings that has been sent"),
                    'route' => [
                        'name' => 'mailings'
                    ]
                ]],
                ['system:info', [
                    'label' => _T("Charts"),
                    'title' => _T("Various charts"),
                    'route' => [
                        'name' => 'charts'
                    ]
                ]],
                ['data:export', [
                    'label' => _T("Exports"),
                    'title' => _T("Export some data in various formats"),
                    'route' => [
                        'name' => 'export'
                    ]
                ]],
                ['member:import', [
                    'label' => _T("Imports"),
                    'title' => _T("Import members from CSV files"),
                    'route' => [
                        'name' => 'import',
                        'aliases' => ['importModel']
                    ]
                ]],
                ['security:manage', [
                    'label' => _T("Authentication attempts"),
                    'title' => _T("See what is being refused after repeated failures, and lift it"),
                    'route' => [
                        'name' => 'authAttempts'
                    ]
                ]],
                ['history:manage', [
                    'label' => _T("Logs"),
                    'title' => _T("View application's logs"),
                    'route' => [
                        'name' => 'history'
                    ]
                ]]
            ];
            $menus['management'] = [
                'title' => _T("Management"),
                'icon' => 'dharmachakra',
                'items' => $this->getGrantedItems($management)
            ];

            $plugins_updates = $this->access->can('plugin:manage', login: $this->login)
                ? count($this->getPluginsUpdates())
                : 0;
            $configuration = [
                ['config:preferences', [
                    'label' => _T("Settings"),
                    'title' => _T("Set applications preferences (address, website, member's cards configuration, ...)"),
                    'route' => [
                        'name' => 'preferences'
                    ]
                ]],
                ['plugin:manage', [
                    'label' => _T("Plugins"),
                    'title' => $plugins_updates > 0
                        ? sprintf(
                            //TRANS: %1$d is the number of plugins that have a new release
                            _Tn('%1$d plugin has a new release available', '%1$d plugins have a new release available', $plugins_updates),
                            $plugins_updates
                        ) : _T("Information about available plugins"),
                    'route' => [
                        'name' => 'plugins'
                    ],
                    'badge' => $plugins_updates > 0 ? $plugins_updates : null
                ]],
                ['config:fields', [
                    'label' => _T("Core lists"),
                    'title' => _T("Customize lists fields and order"),
                    'route' => [
                        'name' => 'configureListFields',
                        'args' => ['table' => 'adherents']
                    ]
                ]],
                ['config:fields', [
                    'label' => _T("Core fields"),
                    'title' => _T("Customize fields order, set which are required, and for who they're visibles"),
                    'route' => [
                        'name' => 'configureCoreFields'
                    ]
                ]],
                ['config:fields', [
                    'label' => _T("Dynamic fields"),
                    'title' => _T("Manage additional fields for various forms"),
                    'route' => [
                        'name' => 'configureDynamicFields',
                        'aliases' => ['editDynamicField'],
                    ]
                ]],
                ['config:references', [
                    'label' => _T("Contributions types"),
                    'title' => _T("Manage contributions types"),
                    'route' => [
                        'name' => 'contributionsTypes',
                        'aliases' => ['editContributionType']
                    ]
                ]],
                ['config:references', [
                    'label' => _T("Payment types"),
                    'title' => _T("Manage payment types"),
                    'route' => [
                        'name' => 'paymentTypes',
                        'aliases' => ['editPaymentType']
                    ]
                ]],
                ['config:references', [
                    'label' => _T("User statuses"),
                    'route' => [
                        'name' => 'status',
                        'aliases' => ['editStatus']
                    ]
                ]],
                ['config:references', [
                    'label' => _T("Titles"),
                    'title' => _T("Manage titles"),
                    'route' => [
                        'name' => 'titles',
                        'aliases' => ['editTitle']
                    ]
                ]],
                ['config:templates', [
                    'label' => _T("PDF models"),
                    'title' => _T("Manage PDF models"),
                    'route' => [
                        'name' => 'pdfModels'
                    ]
                ]],
                ['config:templates', [
                    'label' => _T("Emails content"),
                    'title' => _T("Manage emails texts and subjects"),
                    'route' => [
                        'name' => 'texts'
                    ]
                ]],
                ['config:references', [
                    'label' => _T("Labels translation"),
                    'title' => _T("Manage translatable labels"),
                    'route' => [
                        'name' => 'dynamicTranslations',
                        'aliases' => ['dynamicTranslation']
                    ]
                ]],
                ['role:manage', [
                    'label' => _T("Roles"),
                    'title' => _T("Manage roles and their permissions"),
                    'route' => [
                        'name' => 'roles',
                        'aliases' => ['editRole']
                    ]
                ]]
            ];

            $configuration_items = $this->getGrantedItems($configuration);
            if ($configuration_items !== []) {
                $menus['configuration'] = [
                    'title' => _T("Configuration"),
                    'icon' => 'tools',
                    'items' => $configuration_items
                ];
            }

            if ($this->login->isSuperAdmin()) {
                $menus['configuration']['items'][] = [
                    'label' => _T("Administration tools"),
                    'title' => _T("Various administrative tools"),
                    'route' => [
                        'name' => 'adminTools'
                    ]
                ];
                $menus['configuration']['items'][] = [
                    'label' => _T("Advanced configuration"),
                    'title' => _T("Edit every setting, including those the settings form does not show"),
                    'route' => [
                        'name' => 'advancedConfig'
                    ]
                ];
            }
        } // /isLogged

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\MenuProviderInterface
                && $plugin->isInstalled()
            ) {
                $menus = array_merge_recursive(
                    $menus,
                    $plugin->getMenus()
                );

                // Moves the "Add a child member" and the "Two-factor authentication"
                // items to the end of the "My Account" menu.
                $itemsToMove = ['addMemberChild', 'two-factor-manage'];
                $accountItems = $menus['myaccount']['items'] ?? [];
                $itemsToAppend = [];
                $remainingItems = [];
                foreach ($accountItems as $item) {
                    $routeName = $item['route']['name'] ?? null;

                    if (in_array($routeName, $itemsToMove, strict: true)) {
                        $itemsToAppend[$routeName][] = $item;
                        continue;
                    }

                    $remainingItems[] = $item;
                }
                foreach ($itemsToMove as $routeName) {
                    foreach ($itemsToAppend[$routeName] ?? [] as $item) {
                        $remainingItems[] = $item;
                    }
                }
                $menus['myaccount']['items'] = $remainingItems;
            }
        }

        if ($public) {
            $menus += $this->getPublicMenus();
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
     * Get menu entries granted to login
     *
     * @param array<array{0: string, 1: array<string, mixed>}> $items Permission and menu entry pairs
     *
     * @return array<array<string, mixed>>
     */
    private function getGrantedItems(array $items): array
    {
        $granted = [];
        foreach ($items as [$permission, $item]) {
            if ($this->access->can($permission, login: $this->login)) {
                $granted[] = $item;
            }
        }
        return $granted;
    }

    /**
     * Get public menus
     *
     * @return array<string, string|array<string,mixed>>
     */
    public function getPublicMenus(): array
    {

        if (!$this->preferences->arePublicPagesEnabled()) {
            return [];
        }

        $menus = [];
        $items = [];

        $galleries = [];
        $lists = [];

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_memberslist')) {
            $lists[] = [
                'label' => _T("Members"),
                'route' => [
                    'name' => 'publicMembersList'
                ],
                'icon' => 'address book'
            ];
        }

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_membersgallery')) {
            $galleries[] = [
                'label' => _T('Gallery'),
                'route' => [
                    'name' => 'publicMembersGallery'
                ],
                'icon' => 'images'
            ];
        }

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_stafflist')) {
            $lists[] = [
                'label' => _T("Staff"),
                'route' => [
                    'name' => 'publicStaffList'
                ],
                'icon' => 'address book outline'
            ];
        }

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_staffgallery')) {
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

        if ($this->preferences->showPublicPage($this->login, 'pref_publicpages_visibility_documents')) {
            $items[] = [
                'label' => _T("Documents"),
                'title' => _T("View documents related to your association"),
                'route' => [
                    'name' => 'documentsPublicList'
                ],
                'icon' => 'file alternate'
            ];
        }

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins public menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\MenuProviderInterface
                && $plugin->isInstalled()
            ) {
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
    public function getMyDashboards(): array
    {

        $dashboards = [];
        if ($this->login->isLogged() && !$this->login->isSuperAdmin()) {
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

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\DashboardProviderInterface
                && $plugin->isInstalled()
            ) {
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
    public function getDashboards(): array
    {

        $dashboards = [];

        if ($this->access->getGroupScope('member:read', $this->login) !== []) {
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
                ]
            );
        }

        if ($this->access->can('group:read', login: $this->login)) {
            $dashboards = array_merge(
                $dashboards,
                [
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

        if ($this->access->can('mailing:history', login: $this->login)) {
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
                ]
            );
        }

        if ($this->access->getGroupScope('contribution:read', $this->login) !== []) {
            $dashboards = array_merge(
                $dashboards,
                [
                    [
                        'label' => _T("Contributions"),
                        'title' => _T("View and filter contributions"),
                        'route' => [
                            'name' => 'contributions',
                            'args' => ['type' => 'contributions']
                        ],
                        'icon' => 'receipt'
                    ],
                ]
            );
        }

        if ($this->access->getGroupScope('transaction:read', $this->login) !== []) {
            $dashboards = array_merge(
                $dashboards,
                [
                    [
                        'label' => _T("Transactions"),
                        'title' => _T("View and filter transactions"),
                        'route' => [
                            'name' => 'contributions',
                            'args' => ['type' => 'transactions']
                        ],
                        'icon' => 'book'
                    ],
                ]
            );
        }

        if ($this->access->can('contribution:remind', login: $this->login)) {
            $dashboards = array_merge(
                $dashboards,
                [
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
        $documents = new Documents($this->zdb, $this->login);
        $documents_list = $documents->getList();
        if ($this->login->isSuperAdmin() || count($documents_list)) {
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

        if ($this->access->can('config:preferences', login: $this->login)) {
            $dashboards[] = [
                'label' => _T("Settings"),
                'title' => _T("Set applications preferences (address, website, member's cards configuration, ...)"),
                'route' => [
                    'name' => 'preferences'
                ],
                'icon' => 'control_knobs'
            ];
        }

        if ($this->access->can('plugin:manage', login: $this->login)) {
            $plugins_updates = count($this->getPluginsUpdates());
            $dashboards[] = [
                'label' => _T("Plugins"),
                'title' => $plugins_updates > 0
                    ? sprintf(
                        //TRANS: %1$d is the number of plugins that have a new release
                        _Tn('%1$d plugin has a new release available', '%1$d plugins have a new release available', $plugins_updates),
                        $plugins_updates
                    ) : _T("Information about available plugins"),
                'route' => [
                    'name' => 'plugins'
                ],
                'icon' => 'package',
                'badge' => $plugins_updates > 0 ? $plugins_updates : null
            ];
        }

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\DashboardProviderInterface
                && $plugin->isInstalled()
            ) {
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
    public function getListActions(Adherent $member): array
    {

        $actions = [];

        if ($member->canEdit($this->login)) {
            $actions[] = [
                'label' => sprintf(
                    //TRANS: parameter is the member name
                    _T('%1$s: edit information'),
                    $member->sname
                ),
                'title' => sprintf(
                    //TRANS: parameter is the member name
                    _T('%1$s: edit information'),
                    $member->sname
                ),
                'route' => [
                    'name' => 'editMember',
                    'args' => ['id' => $member->id]
                ],
                'icon' => 'user edit'
            ];
        }

        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $actions = array_merge($actions, [
                [
                    'label' => sprintf(
                        //TRANS: parameter is the member name
                        _T('%1$s: contributions'),
                        $member->sname
                    ),
                    'title' => sprintf(
                        //TRANS: parameter is the member name
                        _T('%1$s: contributions'),
                        $member->sname
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
                    'label' => sprintf(
                        //TRANS: parameter is the member name
                        _T('%1$s: remove from database'),
                        $member->sname
                    ),
                    'title' => sprintf(
                        //TRANS: parameter is the member name
                        _T('%1$s: remove from database'),
                        $member->sname
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

        if ($this->login->isSuperAdmin()) {
            $actions[] = [
                'label' => sprintf(
                    //TRANS: parameter is the member name
                    _T('Log in as %1$s'),
                    $member->sname
                ),
                'title' => sprintf(
                    //TRANS: parameter is the member name
                    _T('Log in as %1$s'),
                    $member->sname
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

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\MemberActionProviderInterface
                && $plugin->isInstalled()
            ) {
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
    public function getDetailedActions(Adherent $member): array
    {

        $actions = [];

        //TODO: add core detailed actions

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\MemberActionProviderInterface
                && $plugin->isInstalled()
            ) {
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
    public function getBatchActions(): array
    {

        $actions = [];

        if ($this->access->can('member:mass-edit', login: $this->login)) {
            $actions[] = [
                'name' => 'masschange',
                'label' => _T('Mass change'),
                'icon' => 'user edit blue'
            ];
        }
        if ($this->access->can('contribution:mass-create', login: $this->login)) {
            $actions[] = [
                'name' => 'masscontributions',
                'label' => _T('Mass add contributions'),
                'icon' => 'receipt bite green'
            ];
        }
        if ($this->access->can('member:mass-edit', login: $this->login) && $this->access->can('member:delete', login: $this->login)) {
            $actions[] = [
                'name' => 'delete',
                'label' => _T('Delete'),
                'icon' => 'user times red'
            ];
        }

        if (
            $this->access->can('mailing:send', login: $this->login)
            && $this->preferences->pref_mail_method != \Galette\Core\GaletteMail::METHOD_DISABLED
        ) {
            $actions[] = [
                'name' => 'sendmail',
                'label' => _T('Mail'),
                'icon' => 'mail bulk'
            ];
        }

        if ($this->access->can('member:print', login: $this->login)) {
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
                ]
            );
        }

        if ($this->access->can('member:export', login: $this->login)) {
            $actions[] = [
                'name' => 'csv__directdownload',
                'label' => _T('Export as CSV'),
                'icon' => 'file csv'
            ];
        }

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\MemberActionProviderInterface
                && $plugin->isInstalled()
            ) {
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
    public function getNews(): array
    {


        $news = [];

        //display Galette news for staff and admins
        if ($this->login->isStaff() || $this->login->isAdmin()) {
            $core_news = new News(Galette::RSS_URL);
            $entry = new News\Entry(
                _T("Galette news"),
                $core_news->getPosts(),
                50
            );
            $news[$entry->getPosition()] = $entry;
        }
        //if a custom RSS feed is set, we add it for everyone; and before Galette news
        if (!empty($this->preferences->pref_rss_url) && $this->preferences->pref_rss_url != Galette::RSS_URL) {
            $asso_news = new News($this->preferences->pref_rss_url);
            $entry = new News\Entry(
                _T("Association news"),
                $asso_news->getPosts(),
                20
            );
            $news[$entry->getPosition()] = $entry;
        }

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\NewsProviderInterface
                && $plugin->isInstalled()
            ) {
                if ($entry = $plugin->getNews()) {
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
     * Are news to be expected?
     *
     * Tells whether a news source is configured, without loading any feed:
     * getNews() reaches the network, this one must not. A plugin may still
     * provide no entry at all, so the answer is optimistic.
     */
    public function hasNews(): bool
    {


        //Galette news are displayed for staff and admins
        if ($this->login->isStaff() || $this->login->isAdmin()) {
            return true;
        }

        //a custom RSS feed is displayed for everyone
        if (!empty($this->preferences->pref_rss_url) && $this->preferences->pref_rss_url != Galette::RSS_URL) {
            return true;
        }

        foreach (array_keys($this->plugins->getActiveModules()) as $module_id) {
            /** @var GalettePlugin $plugin */
            $plugin = $this->plugins->getPlugin($module_id);
            if (
                $plugin instanceof Plugins\NewsProviderInterface
                && $plugin->isInstalled()
            ) {
                return true;
            }
        }

        return false;
    }
}
