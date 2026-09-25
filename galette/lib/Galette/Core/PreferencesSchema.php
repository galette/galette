<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\Adherent;
use Galette\Entity\PaymentType;
use Galette\Entity\Status;
use Galette\Enums\PublicPageVisibility;
use Galette\IO\File;
use Galette\IO\PdfMembersCards;
use Galette\Repository\Members;

use function Safe\preg_match;

/**
 * Declarative schema of Galette preferences
 *
 * Single source of truth for every preference: its type, default value,
 * constraints, required flag, required access level and the legacy behaviour
 * constant it supersedes, if any.
 *
 * Adding a preference means adding one entry here: `Preferences` derives its
 * defaults, its required fields and its type casts from this schema, and the
 * advanced configuration page lists it automatically.
 *
 * Plugins declare their own preferences through `PreferencesProviderInterface`;
 * `Plugins` hands them to `register()`, which merges them into the schema so
 * every accessor below treats them like any other preference. They are named
 * `pref_<plugin route>_*` and carry the owning plugin in their `plugin` key.
 * Their entries only exist while the plugin is active: deactivate it and the
 * rows stay in database, unknown and read-only, until it comes back.
 *
 * Public pages visibilities are flagged `public_page`. A plugin declaring its
 * public pages through `PublicPagesProviderInterface` gets one generated for
 * each of them, named `pref_<plugin route>_publicpages_visibility_<page>`,
 * which also lists the `routes` making the page; unlike its other
 * preferences, the core settings form renders and saves it.
 *
 * Translated strings are deliberately kept out of the structural schema: it is
 * read on every `Preferences::__get()` call, and `_T()` must not run before the
 * translator is up. Messages live in `getErrorMessage()` and `getDescription()`,
 * which are only called when an error is raised or when the advanced page is
 * rendered.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-type Entry array{
 *     type: string,
 *     default: bool|int|string,
 *     required?: bool,
 *     min?: int,
 *     max?: int,
 *     minlength?: int,
 *     error?: string,
 *     sensitive?: bool,
 *     readonly?: bool,
 *     demo_locked?: bool,
 *     mailer?: bool,
 *     advanced?: bool,
 *     alpha?: bool,
 *     acl?: string,
 *     constant?: string,
 *     plugin?: string,
 *     public_page?: bool,
 *     routes?: list<string>
 * }
 */
final class PreferencesSchema
{
    public const string TYPE_STRING = 'string';
    public const string TYPE_INT = 'int';
    public const string TYPE_BOOL = 'bool';
    public const string TYPE_EMAIL = 'email';
    public const string TYPE_EMAILS = 'emails';
    public const string TYPE_URL = 'url';
    public const string TYPE_COLOR = 'color';
    public const string TYPE_HTML = 'html';
    public const string TYPE_PASSWORD = 'password';
    public const string TYPE_LOGIN = 'login';
    public const string TYPE_DATE_MD = 'date_md';
    public const string TYPE_YEAR = 'year';

    public const string ACL_ADMIN = 'admin';
    public const string ACL_SUPERADMIN = 'superadmin';

    public const string ERR_MEASURES = 'measures';
    public const string ERR_CARD_HEIGHT = 'card_height';
    public const string ERR_CARD_WIDTH = 'card_width';
    public const string ERR_MEMBERSHIP_EXT = 'membership_ext';
    public const string ERR_OFFERMONTHS = 'offermonths';
    public const string ERR_LOGIN_LENGTH = 'login_length';
    public const string ERR_LOGIN_EXISTS = 'login_exists';
    public const string ERR_BEG_MEMBERSHIP_FORMAT = 'beg_membership_format';
    public const string ERR_BEG_MEMBERSHIP_DATE = 'beg_membership_date';
    public const string ERR_CARD_YEAR = 'card_year';
    public const string ERR_WEBSITE = 'website';
    public const string ERR_EMAIL = 'email';
    public const string ERR_POSITIVE_NUMBER = 'positive_number';
    public const string ERR_2FA_MODE = 'two_factor_mode';
    public const string ERR_THROTTLE_ATTEMPTS = 'throttle_attempts';
    public const string ERR_THROTTLE_SECONDS = 'throttle_seconds';
    public const string ERR_PUBLIC_PAGE_VISIBILITY = 'public_page_visibility';

    /** @var array<int, string> Every type an entry may declare */
    private const array TYPES = [
        self::TYPE_STRING,
        self::TYPE_INT,
        self::TYPE_BOOL,
        self::TYPE_EMAIL,
        self::TYPE_EMAILS,
        self::TYPE_URL,
        self::TYPE_COLOR,
        self::TYPE_HTML,
        self::TYPE_PASSWORD,
        self::TYPE_LOGIN,
        self::TYPE_DATE_MD,
        self::TYPE_YEAR,
    ];

    /** @var array<string, Entry>|null Core and plugin entries, merged */
    private static ?array $schema = null;

    /** @var array<string, Entry>|null Core entries alone */
    private static ?array $core = null;

    /** @var array<string, array<string, Entry>> Plugin route => its entries */
    private static array $plugin_schema = [];

    /** @var array<string, string>|null Route name => public page visibility */
    private static ?array $public_routes = null;

    /** @var array<int, string> Keys only the core sets on an entry */
    private const array RESERVED_KEYS = ['plugin', 'public_page', 'routes'];

    /**
     * Get the whole schema
     *
     * Order is significant: it drives the insertion order at install time and,
     * through it, the order preferences are read back from the database.
     *
     * @return array<string, Entry>
     */
    public static function getAll(): array
    {
        if (self::$schema === null) {
            self::$schema = self::getCore();
            foreach (self::$plugin_schema as $entries) {
                //union, never array_merge: a plugin cannot shadow a core preference
                self::$schema += $entries;
            }
        }
        return self::$schema;
    }

    /**
     * Get the core schema alone, without any plugin contribution
     *
     * Core code that describes itself needs this rather than `getAll()`: the
     * `@property` annotations on `Preferences` cannot name a plugin's keys
     * without inverting the dependency.
     *
     * @return array<string, Entry>
     */
    public static function getCore(): array
    {
        self::$core ??= self::build();
        return self::$core;
    }

    /**
     * Register the preferences a plugin declares
     *
     * Malformed entries are dropped and reported rather than thrown on:
     * registration runs while modules are being loaded, where an exception
     * would take the whole instance down over one faulty third-party plugin.
     *
     * Preferences and public pages are registered together: a registration
     * replaces whatever the plugin declared before.
     *
     * @param string              $plugin       Plugin route name
     * @param array<string,mixed> $entries      Preference name => Entry
     * @param array<mixed>        $public_pages Page identifier => page, as
     *                                          `PublicPagesProviderInterface::getPublicPages()`
     *                                          returns them
     */
    public static function register(string $plugin, array $entries, array $public_pages = []): void
    {
        $accepted = [];
        $prefix = 'pref_' . $plugin . '_';

        foreach ($entries as $name => $entry) {
            $error = self::rejectEntry(plugin: $plugin, prefix: $prefix, name: $name, entry: $entry);
            if ($error !== null) {
                Analog::log(
                    sprintf('Plugin "%s" declares an invalid preference: %s', $plugin, $error),
                    Analog::ERROR
                );
                continue;
            }
            /** @var Entry $entry */
            //what only the core decides on is not taken from a plugin: a
            //preference flagged as a public page would join the core form
            $entry = array_diff_key($entry, array_flip(self::RESERVED_KEYS));
            $entry['plugin'] = $plugin;
            $accepted[$name] = $entry;
        }

        foreach ($public_pages as $id => $page) {
            $error = self::rejectPublicPage(id: $id, page: $page);
            if ($error !== null) {
                Analog::log(
                    sprintf('Plugin "%s" declares an invalid public page: %s', $plugin, $error),
                    Analog::ERROR
                );
                continue;
            }
            /** @var string $id */
            /** @var array{routes: list<string>, default?: int} $page */
            $name = self::getPublicPageName(plugin: $plugin, id: $id);
            if (isset($accepted[$name])) {
                Analog::log(
                    sprintf(
                        'Plugin "%s" declares a preference "%s" that its public page "%s" replaces',
                        $plugin,
                        $name,
                        $id
                    ),
                    Analog::ERROR
                );
            }
            $accepted[$name] = [
                'type' => self::TYPE_INT,
                'default' => $page['default'] ?? PublicPageVisibility::Inherit->value,
                'min' => min(array_column(PublicPageVisibility::cases(), 'value')),
                'max' => max(array_column(PublicPageVisibility::cases(), 'value')),
                'error' => self::ERR_PUBLIC_PAGE_VISIBILITY,
                'public_page' => true,
                'routes' => $page['routes'],
                'plugin' => $plugin,
            ];
        }

        self::$plugin_schema[$plugin] = $accepted;
        self::invalidate();
    }

    /**
     * Get the name of the visibility of a plugin public page
     *
     * @param string $plugin Plugin route name
     * @param string $id     Page identifier
     */
    public static function getPublicPageName(string $plugin, string $id): string
    {
        return 'pref_' . $plugin . '_publicpages_visibility_' . $id;
    }

    /**
     * Why a public page cannot be accepted, null when it can
     *
     * @param int|string $id   Page identifier
     * @param mixed      $page Candidate page
     */
    private static function rejectPublicPage(int|string $id, mixed $page): ?string
    {
        if (!is_string($id) || preg_match('/^[a-z0-9_]+$/', $id) !== 1) {
            return sprintf('"%s" is not a valid identifier', $id);
        }

        if (!is_array($page) || !isset($page['routes']) || !is_array($page['routes']) || $page['routes'] === []) {
            return sprintf('"%s" has no routes', $id);
        }

        foreach ($page['routes'] as $route) {
            if (!is_string($route) || $route === '') {
                return sprintf('"%s" has an invalid route name', $id);
            }
        }

        if (
            isset($page['default'])
            && (!is_int($page['default']) || PublicPageVisibility::tryFrom($page['default']) === null)
        ) {
            return sprintf('"%s" has an unknown default visibility', $id);
        }

        return null;
    }

    /**
     * Why an entry cannot be accepted, null when it can
     *
     * The prefix rule is what keeps a plugin off core preferences: no core
     * name can ever carry a plugin prefix.
     *
     * @param string $plugin Plugin route name
     * @param string $prefix Prefix every name of that plugin must carry
     * @param string $name   Preference name
     * @param mixed  $entry  Candidate entry
     */
    private static function rejectEntry(string $plugin, string $prefix, string $name, mixed $entry): ?string
    {
        if (!is_array($entry)) {
            return sprintf('"%s" is not an array', $name);
        }

        if (!str_starts_with($name, $prefix)) {
            return sprintf('"%s" is not prefixed with "%s"', $name, $prefix);
        }

        if (!isset($entry['type']) || !in_array($entry['type'], self::TYPES, strict: true)) {
            return sprintf('"%s" has no known type', $name);
        }

        if (!array_key_exists('default', $entry) || !is_scalar($entry['default'])) {
            return sprintf('"%s" has no scalar default value', $name);
        }

        return null;
    }

    /**
     * Drop the preferences a plugin declared
     *
     * @param string $plugin Plugin route name
     */
    public static function unregister(string $plugin): void
    {
        if (isset(self::$plugin_schema[$plugin])) {
            unset(self::$plugin_schema[$plugin]);
            self::invalidate();
        }
    }

    /**
     * Drop every plugin registration
     *
     * The registry is static, so it outlives a request only under a test
     * runner; that is where this is needed.
     */
    public static function reset(): void
    {
        if (count(self::$plugin_schema) > 0) {
            self::$plugin_schema = [];
            self::invalidate();
        }
    }

    /**
     * Which plugin declared that preference, if any
     *
     * @param string $name Preference name
     */
    public static function getOwner(string $name): ?string
    {
        return self::getAll()[$name]['plugin'] ?? null;
    }

    /**
     * Is that preference the visibility of a public page?
     *
     * Such a preference is rendered by the core settings form, whether the core
     * or a plugin declares it.
     *
     * @param string $name Preference name
     */
    public static function isPublicPage(string $name): bool
    {
        return self::getAll()[$name]['public_page'] ?? false;
    }

    /**
     * Get the visibilities of the public pages plugins declare
     *
     * @return array<string, Entry> Preference name => entry
     */
    public static function getPluginPublicPages(): array
    {
        return array_filter(
            self::getAll(),
            static fn(array $entry): bool => isset($entry['plugin']) && ($entry['public_page'] ?? false)
        );
    }

    /**
     * Get the visibility governing a plugin route, if its plugin declared one
     *
     * Route names are shared by every plugin: when the pattern is given, the
     * route must also live under the declaring plugin's own path, so that a
     * plugin cannot put a route of another one behind its own visibility.
     *
     * @param string      $route_name Route name
     * @param string|null $pattern    Route pattern
     */
    public static function getPublicPageRight(string $route_name, ?string $pattern = null): ?string
    {
        if (self::$public_routes === null) {
            self::$public_routes = [];
            foreach (self::getPluginPublicPages() as $name => $entry) {
                foreach ($entry['routes'] ?? [] as $route) {
                    if (isset(self::$public_routes[$route])) {
                        Analog::log(
                            sprintf(
                                'Route "%s" is declared by several public pages, "%s" is ignored',
                                $route,
                                $name
                            ),
                            Analog::ERROR
                        );
                        continue;
                    }
                    self::$public_routes[$route] = $name;
                }
            }
        }

        $name = self::$public_routes[$route_name] ?? null;
        if ($name === null) {
            return null;
        }

        if ($pattern !== null && !str_starts_with($pattern, '/plugins/' . self::getOwner($name) . '/')) {
            return null;
        }

        return $name;
    }

    /**
     * Forget the merged schema, and the defaults Preferences derives from it
     */
    private static function invalidate(): void
    {
        self::$schema = null;
        self::$public_routes = null;
        Preferences::invalidateDefaults();
    }

    /**
     * Build the schema
     *
     * @return array<string, Entry>
     */
    private static function build(): array
    {
        $measure = [
            'type' => self::TYPE_INT,
            'min' => 0,
            'error' => self::ERR_MEASURES,
        ];

        //a value out of the enum cannot be told apart from a bug, and makes
        //Preferences::showPublicPage() throw on every page load
        $visibility = [
            'type' => self::TYPE_INT,
            'min' => min(array_column(PublicPageVisibility::cases(), 'value')),
            'max' => max(array_column(PublicPageVisibility::cases(), 'value')),
            'error' => self::ERR_PUBLIC_PAGE_VISIBILITY,
            'public_page' => true,
        ];

        return [
            'pref_admin_login' => [
                'type' => self::TYPE_LOGIN,
                'default' => 'admin',
                'minlength' => 4,
                'error' => self::ERR_LOGIN_LENGTH,
                'acl' => self::ACL_SUPERADMIN,
                'demo_locked' => true,
            ],
            'pref_admin_pass' => [
                'type' => self::TYPE_PASSWORD,
                'default' => 'admin',
                'sensitive' => true,
                'acl' => self::ACL_SUPERADMIN,
                'demo_locked' => true,
            ],
            'pref_nom' => ['type' => self::TYPE_STRING, 'default' => 'Galette', 'required' => true],
            'pref_slogan' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_adresse' => ['type' => self::TYPE_STRING, 'default' => '-'],
            'pref_adresse2' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_cp' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_ville' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_region' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_pays' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_postal_address' => [
                'type' => self::TYPE_INT,
                'default' => Preferences::POSTAL_ADDRESS_FROM_PREFS,
            ],
            'pref_postal_staff_member' => ['type' => self::TYPE_INT, 'default' => ''],
            'pref_org_phone_number' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_org_phone' => [
                'type' => self::TYPE_INT,
                'default' => Preferences::PHONE_NUMBER_FROM_PREFS,
            ],
            'pref_org_phone_staff_member' => ['type' => self::TYPE_INT, 'default' => ''],
            'pref_org_email' => ['type' => self::TYPE_EMAIL, 'default' => ''],
            'pref_disable_members_socials' => ['type' => self::TYPE_BOOL, 'default' => false, 'advanced' => true],
            'pref_lang' => [
                'type' => self::TYPE_STRING,
                'default' => I18n::DEFAULT_LANG,
                'required' => true,
            ],
            'pref_numrows' => $measure + ['default' => 30, 'required' => true],
            'pref_statut' => [
                'type' => self::TYPE_INT,
                'default' => Status::DEFAULT_STATUS,
                'required' => true,
            ],
            /* Appearance */
            'pref_hide_bg_image' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_enable_custom_colors' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_cc_primary' => ['type' => self::TYPE_COLOR, 'default' => '#ffb619'],
            'pref_cc_primary_text' => ['type' => self::TYPE_COLOR, 'default' => '#000000'],
            'pref_cc_secondary' => ['type' => self::TYPE_COLOR, 'default' => '#ffda89'],
            'pref_cc_secondary_text' => ['type' => self::TYPE_COLOR, 'default' => '#1b1c1d'],
            /* Preferences for emails */
            'pref_email_nom' => ['type' => self::TYPE_STRING, 'default' => 'Galette', 'mailer' => true],
            'pref_email' => [
                'type' => self::TYPE_EMAIL,
                'default' => 'mail@domain.com',
                'demo_locked' => true,
                'mailer' => true,
            ],
            'pref_email_newadh' => [
                'type' => self::TYPE_EMAILS,
                'default' => 'mail@domain.com',
                'demo_locked' => true,
                'mailer' => true,
            ],
            'pref_bool_mailadh' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_mailowner' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_editor_enabled' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_mail_method' => [
                'type' => self::TYPE_INT,
                'default' => GaletteMail::METHOD_DISABLED,
                'demo_locked' => true,
                'mailer' => true,
            ],
            'pref_mail_smtp' => ['type' => self::TYPE_STRING, 'default' => '', 'advanced' => true],
            'pref_mail_smtp_host' => ['type' => self::TYPE_STRING, 'default' => '', 'mailer' => true],
            'pref_mail_smtp_auth' => ['type' => self::TYPE_BOOL, 'default' => false, 'mailer' => true],
            'pref_mail_smtp_secure' => ['type' => self::TYPE_BOOL, 'default' => false, 'mailer' => true],
            'pref_mail_smtp_port' => ['type' => self::TYPE_INT, 'default' => '', 'mailer' => true],
            'pref_mail_smtp_user' => ['type' => self::TYPE_STRING, 'default' => '', 'mailer' => true],
            'pref_mail_smtp_password' => [
                'type' => self::TYPE_STRING,
                'default' => '',
                'sensitive' => true,
                'mailer' => true,
            ],
            'pref_mail_smtp_keepalive' => [
                'type' => self::TYPE_BOOL,
                'default' => true,
                'advanced' => true,
            ],
            // === Mass mailing throttling ===
            // alpha feature
            'pref_mail_batch_size' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'min' => 0,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
                'alpha' => true,
            ],
            'pref_mail_batch_delay' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'min' => 0,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
                'alpha' => true,
            ],
            'pref_mail_hourly_limit' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'min' => 0,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
                'alpha' => true,
            ],
            'pref_mail_daily_limit' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'min' => 0,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
                'alpha' => true,
            ],
            // === /Mass mailing throttling ===
            'pref_membership_ext' => [
                'type' => self::TYPE_INT,
                'default' => 12,
                'min' => 1,
                'error' => self::ERR_MEMBERSHIP_EXT,
            ],
            'pref_beg_membership' => ['type' => self::TYPE_DATE_MD, 'default' => ''],
            'pref_membership_offermonths' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'min' => 0,
                'error' => self::ERR_OFFERMONTHS,
            ],
            'pref_email_reply_to' => [
                'type' => self::TYPE_EMAIL,
                'default' => '',
                'demo_locked' => true,
                'mailer' => true,
            ],
            'pref_website' => ['type' => self::TYPE_URL, 'default' => ''],
            /* Preferences for labels */
            'pref_etiq_marges_v' => $measure + ['default' => 10, 'required' => true],
            'pref_etiq_marges_h' => $measure + ['default' => 10, 'required' => true],
            'pref_etiq_hspace' => $measure + ['default' => 10, 'required' => true],
            'pref_etiq_vspace' => $measure + ['default' => 5, 'required' => true],
            'pref_etiq_hsize' => $measure + ['default' => 90, 'required' => true],
            'pref_etiq_vsize' => $measure + ['default' => 35, 'required' => true],
            'pref_etiq_cols' => $measure + ['default' => 2, 'required' => true],
            'pref_etiq_rows' => $measure + ['default' => 7, 'required' => true],
            'pref_etiq_corps' => $measure + ['default' => 12, 'required' => true],
            'pref_etiq_border' => ['type' => self::TYPE_BOOL, 'default' => true],
            /* Preferences for members cards */
            'pref_force_picture_ratio' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_member_picture_ratio' => ['type' => self::TYPE_STRING, 'default' => 'square_ratio'],
            'pref_card_abrev' => ['type' => self::TYPE_STRING, 'default' => 'GALETTE'],
            'pref_card_strip' => [
                'type' => self::TYPE_STRING,
                'default' => 'Gestion d\'Adherents en Ligne Extrêmement Tarabiscotée',
            ],
            'pref_card_tcol' => ['type' => self::TYPE_COLOR, 'default' => '#FFFFFF'],
            'pref_card_scol' => ['type' => self::TYPE_COLOR, 'default' => '#8C2453'],
            'pref_card_bcol' => ['type' => self::TYPE_COLOR, 'default' => '#53248C'],
            'pref_card_hcol' => ['type' => self::TYPE_COLOR, 'default' => '#248C53'],
            'pref_bool_display_title' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_card_hsize' => [
                'type' => self::TYPE_INT,
                'default' => PdfMembersCards::WIDTH,
                'min' => 70,
                'max' => 95,
                'error' => self::ERR_CARD_WIDTH,
            ],
            'pref_card_vsize' => [
                'type' => self::TYPE_INT,
                'default' => PdfMembersCards::HEIGHT,
                'min' => 40,
                'max' => 55,
                'error' => self::ERR_CARD_HEIGHT,
            ],
            'pref_card_address' => ['type' => self::TYPE_INT, 'default' => 1],
            'pref_card_year' => ['type' => self::TYPE_YEAR, 'default' => ''],
            'pref_card_marges_v' => $measure + ['default' => 15, 'required' => true],
            'pref_card_marges_h' => $measure + ['default' => 20, 'required' => true],
            'pref_card_vspace' => $measure + ['default' => 5, 'required' => true],
            'pref_card_hspace' => $measure + ['default' => 10, 'required' => true],
            'pref_card_self' => ['type' => self::TYPE_BOOL, 'default' => 1],
            'pref_theme' => ['type' => self::TYPE_STRING, 'default' => 'default'],
            'pref_bool_publicpages' => ['type' => self::TYPE_BOOL, 'default' => true],
            //the default right has nothing to inherit from
            'pref_publicpages_visibility_generic' => [
                'default' => Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
                'max' => PublicPageVisibility::Hidden->value,
            ] + $visibility,
            'pref_publicpages_visibility_documents' => [
                'default' => Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
            ] + $visibility,
            'pref_publicpages_visibility_memberslist' => [
                'default' => Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
            ] + $visibility,
            'pref_publicpages_visibility_membersgallery' => [
                'default' => Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
            ] + $visibility,
            'pref_publicpages_visibility_stafflist' => [
                'default' => Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
            ] + $visibility,
            'pref_publicpages_visibility_staffgallery' => [
                'default' => Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
            ] + $visibility,
            'pref_bool_groupsmanagers_are_staff' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_mail_sign' => [
                //a signature may carry a link, and getMailSignature() hands
                //back markup unless it is asked for text
                'type' => self::TYPE_HTML,
                'default' => "{ASSO_NAME}\r\n\r\n{ASSO_WEBSITE}",
            ],
            /* Preferences for member/subscribe form */
            'pref_bool_selfsubscribe' => ['type' => self::TYPE_BOOL, 'default' => true],
            'pref_member_form_grid' => ['type' => self::TYPE_STRING, 'default' => 'one'],
            'pref_bool_empty_form_link' => ['type' => self::TYPE_BOOL, 'default' => false],
            /* New contribution script */
            'pref_new_contrib_script' => ['type' => self::TYPE_STRING, 'default' => ''],
            'pref_bool_wrap_mails' => ['type' => self::TYPE_BOOL, 'default' => true, 'mailer' => true],
            'pref_rss_url' => ['type' => self::TYPE_STRING, 'default' => Galette::RSS_URL],
            'pref_adhesion_form' => [
                'type' => self::TYPE_STRING,
                'default' => \Galette\IO\PdfAdhesionForm::class,
                'readonly' => true,
            ],
            'pref_mail_allow_unsecure' => ['type' => self::TYPE_BOOL, 'default' => false, 'mailer' => true],
            'pref_instance_uuid' => ['type' => self::TYPE_STRING, 'default' => '', 'readonly' => true],
            'pref_registration_uuid' => ['type' => self::TYPE_STRING, 'default' => '', 'readonly' => true],
            'pref_telemetry_date' => ['type' => self::TYPE_STRING, 'default' => '', 'readonly' => true],
            'pref_registration_date' => ['type' => self::TYPE_STRING, 'default' => '', 'readonly' => true],
            'pref_footer' => ['type' => self::TYPE_HTML, 'default' => ''],
            'pref_filter_account' => ['type' => self::TYPE_INT, 'default' => Members::ALL_ACCOUNTS],
            'pref_galette_url' => [
                'type' => self::TYPE_STRING,
                'default' => '',
                'constant' => 'GALETTE_URI',
            ],
            'pref_redirect_on_create' => [
                'type' => self::TYPE_INT,
                'default' => Adherent::AFTER_ADD_DEFAULT,
            ],
            /* Security related */
            'pref_password_length' => ['type' => self::TYPE_INT, 'default' => 6],
            'pref_password_blacklist' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_password_strength' => ['type' => self::TYPE_INT, 'default' => Preferences::PWD_NONE],
            'pref_default_paymenttype' => ['type' => self::TYPE_INT, 'default' => PaymentType::CHECK],
            'pref_bool_create_member' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_create_member' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_edit_member' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_edit_groups' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_mailings' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_exports' => ['type' => self::TYPE_BOOL, 'default' => true],
            'pref_bool_groupsmanagers_create_contributions' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_create_transactions' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_see_contributions' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_bool_groupsmanagers_see_transactions' => ['type' => self::TYPE_BOOL, 'default' => false],
            'pref_noindex' => ['type' => self::TYPE_BOOL, 'default' => false],
            // === Two-factor authentication ===
            // alpha feature
            'pref_2fa_mode' => [
                'type' => self::TYPE_INT,
                'default' => TwoFactorAuth::MODE_DISABLED,
                'min' => TwoFactorAuth::MODE_DISABLED,
                'max' => TwoFactorAuth::MODE_REQUIRED_ALL,
                'error' => self::ERR_2FA_MODE,
                'alpha' => true,
            ],
            /*
             * The second factor of the super administrator. That account is not
             * a member, so it has nowhere else to keep one. Read by the
             * challenge before anybody is logged in, written by the enrolment
             * pages, and never by a form: read only here, and the secret never
             * rendered. Locked on a demonstration instance, whose credentials
             * are public: anybody could otherwise enrol a factor of their own
             * on that account and close the demonstration.
             */
            'pref_2fa_superadmin_secret' => [
                'type' => self::TYPE_STRING,
                'default' => '',
                'sensitive' => true,
                'readonly' => true,
                'demo_locked' => true,
                'acl' => self::ACL_SUPERADMIN,
                'alpha' => true,
            ],
            'pref_2fa_superadmin_enabled' => [
                'type' => self::TYPE_BOOL,
                'default' => false,
                'readonly' => true,
                'demo_locked' => true,
                'acl' => self::ACL_SUPERADMIN,
                'alpha' => true,
            ],
            'pref_2fa_superadmin_timeslice' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'readonly' => true,
                'demo_locked' => true,
                'acl' => self::ACL_SUPERADMIN,
                'alpha' => true,
            ],
            // === /Two-factor authentication ===
            /* Throttling of failed authentication attempts */
            'pref_throttle_account_ip_attempts' => [
                'type' => self::TYPE_INT,
                'default' => 5,
                'min' => AuthThrottle::MIN_ATTEMPTS,
                'error' => self::ERR_THROTTLE_ATTEMPTS,
                'advanced' => true
            ],
            'pref_throttle_account_ip_window' => [
                'type' => self::TYPE_INT,
                'default' => 900,
                'min' => AuthThrottle::MIN_SECONDS,
                'error' => self::ERR_THROTTLE_SECONDS,
                'advanced' => true
            ],
            'pref_throttle_ip_attempts' => [
                'type' => self::TYPE_INT,
                'default' => 30,
                'min' => AuthThrottle::MIN_ATTEMPTS,
                'error' => self::ERR_THROTTLE_ATTEMPTS,
                'advanced' => true
            ],
            'pref_throttle_ip_window' => [
                'type' => self::TYPE_INT,
                'default' => 900,
                'min' => AuthThrottle::MIN_SECONDS,
                'error' => self::ERR_THROTTLE_SECONDS,
                'advanced' => true
            ],
            'pref_throttle_account_attempts' => [
                'type' => self::TYPE_INT,
                'default' => 100,
                'min' => AuthThrottle::MIN_ATTEMPTS,
                'error' => self::ERR_THROTTLE_ATTEMPTS,
                'advanced' => true
            ],
            'pref_throttle_account_window' => [
                'type' => self::TYPE_INT,
                'default' => 86400,
                'min' => AuthThrottle::MIN_SECONDS,
                'error' => self::ERR_THROTTLE_SECONDS,
                'advanced' => true
            ],
            'pref_throttle_delay' => [
                'type' => self::TYPE_INT,
                'default' => 900,
                'min' => AuthThrottle::MIN_SECONDS,
                'error' => self::ERR_THROTTLE_SECONDS,
                'advanced' => true
            ],
            'pref_throttle_recovery_attempts' => [
                'type' => self::TYPE_INT,
                'default' => 3,
                'min' => AuthThrottle::MIN_ATTEMPTS,
                'error' => self::ERR_THROTTLE_ATTEMPTS,
                'advanced' => true
            ],
            'pref_throttle_recovery_window' => [
                'type' => self::TYPE_INT,
                'default' => 3600,
                'min' => AuthThrottle::MIN_SECONDS,
                'error' => self::ERR_THROTTLE_SECONDS,
                'advanced' => true
            ],
            'pref_throttle_subscribe_attempts' => [
                'type' => self::TYPE_INT,
                'default' => 5,
                'min' => AuthThrottle::MIN_ATTEMPTS,
                'error' => self::ERR_THROTTLE_ATTEMPTS,
                'advanced' => true
            ],
            'pref_throttle_subscribe_window' => [
                'type' => self::TYPE_INT,
                'default' => 3600,
                'min' => AuthThrottle::MIN_SECONDS,
                'error' => self::ERR_THROTTLE_SECONDS,
                'advanced' => true
            ],
            'pref_throttle_second_factor_attempts' => [
                'type' => self::TYPE_INT,
                'default' => 5,
                'min' => AuthThrottle::MIN_ATTEMPTS,
                'error' => self::ERR_THROTTLE_ATTEMPTS,
                'advanced' => true,
                'alpha' => true,
            ],
            'pref_throttle_second_factor_window' => [
                'type' => self::TYPE_INT,
                'default' => 900,
                'min' => AuthThrottle::MIN_SECONDS,
                'error' => self::ERR_THROTTLE_SECONDS,
                'advanced' => true,
                'alpha' => true,
            ],
            /* Settings that used to live in behavior.inc.php only */
            'pref_x_forwarded_for_index' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'min' => 0,
                'error' => self::ERR_POSITIVE_NUMBER,
                'constant' => 'GALETTE_X_FORWARDED_FOR_INDEX',
                'advanced' => true,
            ],
            'pref_session_timeout' => [
                'type' => self::TYPE_INT,
                'default' => 0,
                'min' => 0,
                'error' => self::ERR_POSITIVE_NUMBER,
                'constant' => 'GALETTE_TIMEOUT',
                'advanced' => true,
            ],
            /* Maximum size of an upload, in Ko, one setting per kind of upload.
               PHP caps them all: whatever is set here, a file bigger than
               upload_max_filesize or a request bigger than post_max_size never
               reaches Galette. */
            'pref_upload_size_images' => [
                'type' => self::TYPE_INT,
                'default' => File::MAX_FILE_SIZE,
                'min' => 1,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
            ],
            'pref_upload_size_attachments' => [
                'type' => self::TYPE_INT,
                'default' => File::MAX_FILE_SIZE,
                'min' => 1,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
            ],
            'pref_upload_size_documents' => [
                'type' => self::TYPE_INT,
                'default' => File::MAX_FILE_SIZE,
                'min' => 1,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
            ],
            'pref_upload_size_imports' => [
                'type' => self::TYPE_INT,
                'default' => File::MAX_FILE_SIZE,
                'min' => 1,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
            ],
            'pref_upload_size_dynamic_files' => [
                'type' => self::TYPE_INT,
                'default' => DynamicField::DEFAULT_MAX_FILE_SIZE,
                'min' => 1,
                'error' => self::ERR_POSITIVE_NUMBER,
                'advanced' => true,
            ],
        ];
    }

    /**
     * Is that preference known?
     *
     * @param string $name Preference name
     */
    public static function has(string $name): bool
    {
        return isset(self::getAll()[$name]);
    }

    /**
     * Get a single schema entry
     *
     * @param string $name Preference name
     *
     * @return Entry|null
     */
    public static function get(string $name): ?array
    {
        return self::getAll()[$name] ?? null;
    }

    /**
     * Get default values, as a flat name => value map
     *
     * @return array<string, bool|int|string>
     */
    public static function getDefaults(): array
    {
        return array_map(
            fn(array $entry): bool|int|string => $entry['default'],
            self::getAll()
        );
    }

    /**
     * Get required preferences, as the name => 1 map Preferences expects
     *
     * @return array<string, int>
     */
    public static function getRequired(): array
    {
        $required = [];
        foreach (self::getAll() as $name => $entry) {
            if ($entry['required'] ?? false) {
                $required[$name] = 1;
            }
        }
        return $required;
    }

    /**
     * Get the type of a preference
     *
     * Unknown preferences are handled as plain strings, so that rows left in
     * database by an old version or by a plugin keep being readable.
     *
     * @param string $name Preference name
     */
    public static function getType(string $name): string
    {
        return self::getAll()[$name]['type'] ?? self::TYPE_STRING;
    }

    /**
     * Get the access level required to write a preference
     *
     * @param string $name Preference name
     */
    public static function getAcl(string $name): string
    {
        return self::getAll()[$name]['acl'] ?? self::ACL_ADMIN;
    }

    /**
     * Should that preference value be kept out of any display?
     *
     * @param string $name Preference name
     */
    public static function isSensitive(string $name): bool
    {
        return self::getAll()[$name]['sensitive'] ?? false;
    }

    /**
     * Is that preference maintained by Galette itself?
     *
     * Such a value is displayed but never offered for edition: it is a
     * generated identifier or a date Galette records on its own, and letting
     * anyone rewrite it would only break things.
     *
     * @param string $name Preference name
     */
    public static function isReadOnly(string $name): bool
    {
        return self::getAll()[$name]['readonly'] ?? false;
    }

    /**
     * Is that preference frozen on a demonstration instance?
     *
     * A public demo hands its credentials to everyone, so the values that
     * would let a visitor take the instance over - or use it to send mail -
     * are never written there.
     *
     * @param string $name Preference name
     */
    public static function isDemoLocked(string $name): bool
    {
        return self::getAll()[$name]['demo_locked'] ?? false;
    }

    /**
     * Get every preference frozen on a demonstration instance
     *
     * @return array<string>
     */
    public static function getDemoLocked(): array
    {
        return array_keys(
            array_filter(
                self::getAll(),
                static fn(array $entry): bool => $entry['demo_locked'] ?? false
            )
        );
    }

    /**
     * Get every preference the mail transport is built from
     *
     * These are the ones a test may run on values that have not been stored
     * yet: anything `GaletteMail` reads, and nothing else.
     *
     * @return array<string>
     */
    public static function getMailer(): array
    {
        return array_keys(
            array_filter(
                self::getAll(),
                static fn(array $entry): bool => $entry['mailer'] ?? false
            )
        );
    }

    /**
     * Is that preference left out of the settings form?
     *
     * Such a preference is only offered by the advanced configuration page, so
     * a settings submission that does not carry it is not a request to turn it
     * off: it keeps what is stored.
     *
     * @param string $name Preference name
     */
    public static function isAdvanced(string $name): bool
    {
        return self::getAll()[$name]['advanced'] ?? false;
    }

    /**
     * Does that preference drive a feature that is still in alpha?
     *
     * Such a setting works, but the feature behind it has not been through a
     * release yet: the advanced configuration page says so before it is set.
     *
     * @param string $name Preference name
     */
    public static function isAlpha(string $name): bool
    {
        return self::getAll()[$name]['alpha'] ?? false;
    }

    /**
     * Get the legacy behaviour constant a preference supersedes, if any
     *
     * @param string $name Preference name
     */
    public static function getConstant(string $name): ?string
    {
        return self::getAll()[$name]['constant'] ?? null;
    }

    /**
     * Get every preference superseding a legacy constant
     *
     * @return array<string, string> Preference name => constant name
     */
    public static function getConstants(): array
    {
        $constants = [];
        foreach (self::getAll() as $name => $entry) {
            if (isset($entry['constant'])) {
                $constants[$name] = $entry['constant'];
            }
        }
        return $constants;
    }

    /**
     * Get the translated message for an error identifier
     *
     * Kept apart from the schema so `_T()` is only called once an error is
     * actually raised, and so the literals stay extractable by xgettext.
     *
     * A message may carry numbered placeholders, the first of which is the
     * preference name. A generic message reused across preferences therefore
     * stays informative, and a new one of the same kind needs no new
     * translatable string at all. Arguments are numbered rather than named
     * because a named one reads like a word, and comes back translated.
     *
     * @param string $id      Error identifier
     * @param string ...$args What the message's placeholders stand for, the
     *                        preference name first
     */
    public static function getErrorMessage(string $id, string ...$args): string
    {
        $message = match ($id) {
            self::ERR_MEASURES => _T("- The numbers and measures have to be integers!"),
            self::ERR_CARD_HEIGHT => _T("- The card height have to be an integer between 40 and 55!"),
            self::ERR_CARD_WIDTH => _T("- The card width have to be an integer between 70 and 95!"),
            self::ERR_MEMBERSHIP_EXT => _T("- Invalid number of months of membership extension."),
            self::ERR_OFFERMONTHS => _T("- Invalid number of offered months."),
            self::ERR_LOGIN_LENGTH => _T("- The username must be composed of at least 4 characters!"),
            self::ERR_LOGIN_EXISTS => _T("- This username is already used by another member !"),
            self::ERR_BEG_MEMBERSHIP_FORMAT => _T("- Invalid format of beginning of membership."),
            self::ERR_BEG_MEMBERSHIP_DATE => _T("- Invalid date for beginning of membership."),
            self::ERR_CARD_YEAR => _T("- Invalid year for cards."),
            self::ERR_WEBSITE => _T("- Invalid website URL."),
            //TRANS: first parameter is the address, second the setting holding it
            self::ERR_EMAIL => _T('- Invalid E-Mail address %1$s (%2$s)'),
            //TRANS: parameter is the setting name
            self::ERR_POSITIVE_NUMBER => _T('- Value for \'%1$s\' must be a positive number!'),
            //throttling can be made as generous as wanted, but not turned off
            //TRANS: first parameter is the setting name, second the lowest value it takes
            self::ERR_2FA_MODE => _T("- Unknown two-factor authentication mode!"),
            self::ERR_THROTTLE_ATTEMPTS => _T('- Value for \'%1$s\' must be %2$s attempts or more!'),
            //TRANS: first parameter is the setting name, second the lowest value it takes
            self::ERR_THROTTLE_SECONDS => _T('- Value for \'%1$s\' must be %2$s seconds or more!'),
            //TRANS: parameter is the setting name
            self::ERR_PUBLIC_PAGE_VISIBILITY => _T('- Unknown visibility for \'%1$s\'!'),
            default => throw new \InvalidArgumentException(sprintf('Unknown error identifier "%s".', $id)),
        };

        //a message asked for on its own keeps its placeholders, which is how
        //the settings pages show what a bound means
        if ($args === []) {
            return $message;
        }

        //the floor belongs to the message: no caller knows which of the two
        //applies, and neither should it
        $args = [...$args, ...match ($id) {
            self::ERR_THROTTLE_ATTEMPTS => [(string)AuthThrottle::MIN_ATTEMPTS],
            self::ERR_THROTTLE_SECONDS => [(string)AuthThrottle::MIN_SECONDS],
            default => [],
        }];

        return vsprintf($message, $args);
    }
}
