<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Safe\DateTime;
use Galette\Access\AccessControl;
use Galette\Entity\Adherent;
use Galette\Repository\Documents;
use Galette\IO\News;
use Galette\Util\Release;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Slim\App;

use function Safe\glob;
use function Safe\exec;

/**
 * Galette application instance
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Galette
{
    public const string MODE_PROD = 'PROD';
    public const string MODE_DEV = 'DEV';
    public const string MODE_MAINT = 'MAINT';
    public const string MODE_DEMO = 'DEMO';

    public const string RSS_URL = 'https://galette.eu/dc/index.php/feed/atom';

    /**
     * Retrieve Galette version from git, if present.
     *
     * @param bool $time Include time and timezone. Defaults to false.
     */
    public static function gitVersion(bool $time = false): string
    {
        $galette_version = GALETTE_VERSION;

        //used for both git and nightly installs
        $version = str_replace('-dev', '-git', GALETTE_VERSION);
        if (!str_contains($version, '-git')) {
            $version .= '-git';
        }

        if (file_exists(GALETTE_ROOT . '../.git')) {
            $git = 'git -C ' . escapeshellarg(GALETTE_ROOT . '..');

            //a release tag checkout is the release itself
            $output = [];
            $result = 1;
            exec($git . ' describe --tags --exact-match HEAD 2>/dev/null', $output, $result);
            if ($result === 0 && trim($output[0] ?? '') === GALETTE_VERSION) {
                return GALETTE_VERSION;
            }

            $output = [];
            $result = 1;
            exec($git . ' log -n1 --pretty="%h %ci" HEAD 2>/dev/null', $output, $result);
            if ($result !== 0 || !isset($output[0]) || !str_contains($output[0], ' ')) {
                //git cannot be run (missing binary, repository not marked as safe.directory for the web server user, ...)
                Analog::log(
                    'Unable to retrieve git version information (exit code ' . $result . ')',
                    Analog::WARNING
                );
                return $version;
            }

            [$commitHash, $commitDate] = explode(' ', trim($output[0]), 2);
            $commitDate = new DateTime($commitDate);

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
        if (defined('GALETTE_TESTS') || getenv('GALETTE_TESTS')) {
            return [
                'new' => false,
                'version' => GALETTE_VERSION
            ];
        }

        $release = self::getRelease();
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
    public static function getPluginsUpdates(): array
    {
        if (defined('GALETTE_TESTS') || getenv('GALETTE_TESTS')) {
            return [];
        }

        try {
            return self::getRelease()->getPluginsUpdates();
        } catch (\Throwable $e) {
            Logs::exception($e, 'Error looking for new plugins releases', Analog::ERROR);
            return [];
        }
    }

    /**
     * Get releases checker, shared with the rest of the request when possible
     */
    private static function getRelease(): Release
    {
        /** @var ?ContainerInterface $container */
        global $container;

        if ($container instanceof ContainerInterface) {
            return $container->get(Release::class);
        }
        return new Release();
    }

    /**
     * Get all menus
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getAllMenus(): array
    {
        return static::getMenus(public: true);
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
        $access = $container->get(AccessControl::class);

        if ($login->isLogged()) {
            $menus['myaccount'] = [
                'title' => _T("My Account"),
                'icon' => 'user',
                'items' => []
            ];

            if (!$login->isSuperAdmin()) {
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

            if (!$login->isSuperAdmin() && $access->can('member:create-child', login: $login)) {
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
            if (TwoFactorAuth::modeFrom($preferences) !== TwoFactorAuth::MODE_DISABLED) {
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

            if ($access->getGroupScope('member:read', $login) !== []) {
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

            if ($access->can('member:create', login: $login)) {
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

            if ($access->getGroupScope('contribution:read', $login) !== []) {
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

            if ($access->can('contribution:schedule', login: $login)) {
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

            if ($access->getGroupScope('transaction:read', $login) !== []) {
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

            if ($access->can('contribution:create', login: $login)) {
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

            if ($access->can('transaction:create', login: $login)) {
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

            if ($access->can('contribution:remind', login: $login)) {
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
                'items' => self::getGrantedItems($management, $access, $login)
            ];

            $plugins_updates = $access->can('plugin:manage', login: $login)
                ? count(self::getPluginsUpdates())
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

            $configuration_items = self::getGrantedItems($configuration, $access, $login);
            if ($configuration_items !== []) {
                $menus['configuration'] = [
                    'title' => _T("Configuration"),
                    'icon' => 'tools',
                    'items' => $configuration_items
                ];
            }

            if ($login->isSuperAdmin()) {
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

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\MenuProviderInterface
                || method_exists($plugin, 'getMenusContents')) //handle deprecated case
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
     * Get menu entries granted to login
     *
     * @param array<array{0: string, 1: array<string, mixed>}> $items  Permission and menu entry pairs
     * @param AccessControl                                    $access Access control
     * @param Login                                            $login  Login to check
     *
     * @return array<array<string, mixed>>
     */
    private static function getGrantedItems(array $items, AccessControl $access, Login $login): array
    {
        $granted = [];
        foreach ($items as [$permission, $item]) {
            if ($access->can($permission, login: $login)) {
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

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins public menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\MenuProviderInterface
                || method_exists($plugin, 'getPublicMenusItemsList')) //handle deprecated case
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

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\DashboardProviderInterface
                || method_exists($plugin, 'getMyDashboardsContents')) //handle deprecated case
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
        $access = $container->get(AccessControl::class);

        if ($access->getGroupScope('member:read', $login) !== []) {
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

        if ($access->can('group:read', login: $login)) {
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

        if ($access->can('mailing:history', login: $login)) {
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

        if ($access->getGroupScope('contribution:read', $login) !== []) {
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

        if ($access->getGroupScope('transaction:read', $login) !== []) {
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

        if ($access->can('contribution:remind', login: $login)) {
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
        $documents = new Documents($zdb, $login);
        $documents_list = $documents->getList();
        if ($login->isSuperAdmin() || count($documents_list)) {
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

        if ($access->can('config:preferences', login: $login)) {
            $dashboards[] = [
                'label' => _T("Settings"),
                'title' => _T("Set applications preferences (address, website, member's cards configuration, ...)"),
                'route' => [
                    'name' => 'preferences'
                ],
                'icon' => 'control_knobs'
            ];
        }

        if ($access->can('plugin:manage', login: $login)) {
            $plugins_updates = count(self::getPluginsUpdates());
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

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\DashboardProviderInterface
                || method_exists($plugin, 'getDashboardsContents')) //handle deprecated case
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

        if ($login->isAdmin() || $login->isStaff()) {
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

        if ($login->isSuperAdmin()) {
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

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\MemberActionProviderInterface
                || method_exists($plugin, 'getListActionsContents')) //handle deprecated case
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
    public static function getDetailedActions(Adherent $member): array
    {
        /**
         * @var Plugins $plugins
         * @var ContainerInterface $container
         */
        global $plugins, $container;

        $actions = [];

        //TODO: add core detailed actions

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\MemberActionProviderInterface
                || method_exists($plugin, 'getDetailedActionsContents')) //handle deprecated case
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

        $access = $container->get(AccessControl::class);
        if ($access->can('member:mass-edit', login: $login)) {
            $actions[] = [
                'name' => 'masschange',
                'label' => _T('Mass change'),
                'icon' => 'user edit blue'
            ];
        }
        if ($access->can('contribution:mass-create', login: $login)) {
            $actions[] = [
                'name' => 'masscontributions',
                'label' => _T('Mass add contributions'),
                'icon' => 'receipt bite green'
            ];
        }
        if ($access->can('member:mass-edit', login: $login) && $access->can('member:delete', login: $login)) {
            $actions[] = [
                'name' => 'delete',
                'label' => _T('Delete'),
                'icon' => 'user times red'
            ];
        }

        if (
            $access->can('mailing:send', login: $login)
            && $preferences->pref_mail_method != \Galette\Core\GaletteMail::METHOD_DISABLED
        ) {
            $actions[] = [
                'name' => 'sendmail',
                'label' => _T('Mail'),
                'icon' => 'mail bulk'
            ];
        }

        if ($access->can('member:print', login: $login)) {
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

        if ($access->can('member:export', login: $login)) {
            $actions[] = [
                'name' => 'csv__directdownload',
                'label' => _T('Export as CSV'),
                'icon' => 'file csv'
            ];
        }

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\MemberActionProviderInterface
                || method_exists($plugin, 'getBatchActionsContents')) //handle deprecated case
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

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            //get plugins menus entries
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\NewsProviderInterface
                || method_exists($plugin, 'getNews')) //handle deprecated 1.2.2 case
                && $plugin->isInstalled()
            ) {
                //display deprecation message - since 1.2.2
                if (!($plugin instanceof Plugins\NewsProviderInterface)) {
                    Analog::log(
                        $plugin::class . '::getNews() is deprecated, please implement NewsProviderInterface',
                        Analog::WARNING
                    );
                }
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
    public static function hasNews(): bool
    {
        global $container;

        /**
         * @var Login $login
         * @var Preferences $preferences
         * @var Plugins $plugins
         */
        global $login, $preferences, $plugins;

        //Galette news are displayed for staff and admins
        if ($login->isStaff() || $login->isAdmin()) {
            return true;
        }

        //a custom RSS feed is displayed for everyone
        if (!empty($preferences->pref_rss_url) && $preferences->pref_rss_url != self::RSS_URL) {
            return true;
        }

        foreach (array_keys($plugins->getActiveModules()) as $module_id) {
            $plugin_class = $plugins->getClassName($module_id, full: true);
            /** @var GalettePlugin $plugin */
            $plugin = $container->get($plugin_class);
            if (
                ($plugin instanceof Plugins\NewsProviderInterface
                || method_exists($plugin, 'getNews')) //handle deprecated 1.2.2 case
                && $plugin->isInstalled()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is demonstration mode enabled
     */
    public static function isDemo(): bool
    {
        return GALETTE_MODE === static::MODE_DEMO;
    }

    /**
     * Is maintenance mode enabled
     */
    public static function isUnderMaintenance(): bool
    {
        return GALETTE_MODE === static::MODE_MAINT;
    }

    /**
     * Is debug mode enabled
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
     */
    public static function isSqlDebugEnabled(): bool
    {
        return defined('GALETTE_SQL_DEBUG') && GALETTE_SQL_DEBUG || static::isDebugEnabled();
    }

    /**
     * Is a nightly build
     */
    public static function isNightly(): bool
    {
        return GALETTE_NIGHTLY !== false;
    }

    /**
     * Check if a string is serialized
     *
     * @param string $string String to check
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
        $decoded = \json_decode($string, associative: true); // @phpstan-ignore theCodingMachineSafe.function
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
     * @throws RuntimeException
     */
    public static function jsonEncode(array|object $data): string
    {
        $encoded = \json_encode($data); // @phpstan-ignore theCodingMachineSafe.function
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON encode error: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * Load routes
     *
     * @param App<ContainerInterface> $app   App instance
     * @param bool                    $force Force routes re-loading (for tests)
     * @param bool                    $cron  Load only cron routes
     */
    public static function loadRoutes(App $app, bool $force = false, bool $cron = false): void
    {
        /** @var array<string, array<int, string>> $routeFiles */
        static $routeFiles = [];

        $cron_exclusions = [
            'ajax.routes.php',
            'plugins.routes.php'
        ];

        $mode = $cron ? 'cron' : 'all';
        if (!isset($routeFiles[$mode]) || $force) {
            $routesPath = GALETTE_ROOT . 'includes/routes/';
            $files = array_merge(
                glob($routesPath . '*.route.php') ?: [],
                glob($routesPath . '*.routes.php') ?: []
            );
            $files = array_values(array_unique($files));

            if ($cron) {
                $files = array_filter(
                    $files,
                    static fn(string $file): bool => !in_array(basename($file), $cron_exclusions, strict: true)
                );
            }

            usort(
                $files,
                static function (string $left, string $right): int {
                    $baseLeft = basename($left);
                    $baseRight = basename($right);

                    if ($baseLeft === $baseRight && $baseLeft === 'main.routes.php') {
                        return 0;
                    }
                    if ($baseLeft === 'main.routes.php') {
                        return -1;
                    }
                    if ($baseRight === 'main.routes.php') {
                        return 1;
                    }
                    return strcmp($left, $right);
                }
            );
            $routeFiles[$mode] = $files;
        }

        foreach ($routeFiles[$mode] as $routeFile) {
            if ($force) {
                require $routeFile;
            } else {
                require_once $routeFile;
            }
        }
    }
}
