<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Container definitions
 *
 * Returned to Galette\Core\SlimApp, which adds the ones depending on runtime
 * values (application mode, plugins instance, Slim application itself).
 */

use Galette\Access\AccessControl;
use Galette\Access\Permissions;
use Galette\Access\RoleResolver;
use Galette\Access\Roles;
use Galette\Core\AclsFactory;
use Galette\Core\Db;
use Galette\Core\FeatureFlagManager;
use Galette\Core\FlashMessages;
use Galette\Core\History;
use Galette\Core\I18n;
use Galette\Core\Login;
use Galette\Core\Logo;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Core\PrintLogo;
use Galette\Core\SystemClock;
use Galette\Core\Translator;
use Galette\Entity\Adherent;
use Galette\Entity\FieldsConfig;
use Galette\Entity\ListsConfig;
use Galette\Events\ContribListener;
use Galette\Events\MemberListener;
use Galette\Interfaces\PermissionResolverInterface;
use Galette\Middleware\Csrf;
use Galette\Navigation\ReleaseNotices;
use Galette\Twig\ViewFactory;
use Galette\Util\Release;
use League\Event\EventDispatcher;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use RKA\Session;
use Slim\App;
use Slim\Flash\Messages;
use Slim\Routing\RouteCollector;
use Slim\Routing\RouteParser;
use Slim\Views\Twig;

use function DI\autowire;
use function DI\create;
use function DI\factory;
use function DI\get;

return [
    // -------------------------------------------------------------------------
    // Slim
    // -------------------------------------------------------------------------
    //App is registered by DI\Bridge\Slim\Bridge::create()
    RouteCollector::class => factory(fn(App $app) => $app->getRouteCollector()),
    RouteParser::class => factory(fn(App $app) => $app->getRouteCollector()->getRouteParser()),
    Twig::class => factory([ViewFactory::class, 'create']),
    Messages::class => autowire(FlashMessages::class),

    // -------------------------------------------------------------------------
    // Core services
    // -------------------------------------------------------------------------
    Db::class => autowire(),
    Preferences::class => autowire(),
    Logo::class => autowire(),
    PrintLogo::class => autowire(),
    History::class => autowire(),
    //code depending on "now" takes a clock rather than calling time() itself
    ClockInterface::class => autowire(SystemClock::class),

    //plugins classes may need the permissions catalog while plugins are being loaded
    Permissions::class => factory(
        fn(ContainerInterface $c): Permissions => new Permissions(fn(): Plugins => $c->get(Plugins::class))
    ),
    RoleResolver::class => autowire()->constructorParameter('repository', get(Roles::class)),
    //permissions come from roles
    PermissionResolverInterface::class => get(RoleResolver::class),
    AccessControl::class => autowire(),
    FeatureFlagManager::class => autowire(),
    ReleaseNotices::class => autowire()->constructorParameter(
        'release',
        factory(fn(ContainerInterface $c): Closure => fn(): Release => $c->get(Release::class))
    ),
    //optional constructor parameters are not autowired
    Release::class => autowire()->constructorParameter('plugins', get(Plugins::class)),

    //I18n and Login are kept in session
    I18n::class => factory(function (Session $session): I18n {
        $i18n = $session->i18n;
        if (!$i18n || !$i18n->getId() || isset($_GET['ui_pref_lang']) && $_GET['ui_pref_lang']) {
            $i18n = new I18n($_GET['ui_pref_lang'] ?? false);
            $session->i18n = $i18n;
        }
        return $i18n;
    }),
    Login::class => factory(
        fn(Session $session, ContainerInterface $c): Login => $session->login
            ?: new Login($c->get(Db::class), $c->get(I18n::class))
    ),

    Translator::class => factory(function (I18n $i18n): Translator {
        $translator = new Translator();

        $domains = ['galette'];
        foreach ($domains as $domain) {
            //load translation file for domain
            $translator->addTranslationFilePattern(
                type: 'gettext',
                baseDir: GALETTE_ROOT . '/lang/',
                pattern: '/%s/LC_MESSAGES/' . $domain . '.mo',
                textDomain: $domain
            );

            //check if a local lang file exists and load it
            $translator->addTranslationFilePattern(
                type: 'phparray',
                baseDir: GALETTE_ROOT . '/lang/',
                pattern: $domain . '_%s_local_lang.php',
                textDomain: $domain
            );
        }

        $translator->setLocale($i18n->getLongID());
        return $translator;
    }),

    // -------------------------------------------------------------------------
    // Events; plugins subscribe their own listeners when loaded
    // -------------------------------------------------------------------------
    EventDispatcher::class => create()
        ->method('subscribeListenersFrom', get(MemberListener::class))
        ->method('subscribeListenersFrom', get(ContribListener::class)),

    // -------------------------------------------------------------------------
    // ACLs and CSRF
    // -------------------------------------------------------------------------
    'acls' => factory([AclsFactory::class, 'create']),
    'CsrfExclusions' => factory(fn(Plugins $plugins): array => $plugins->getCsrfExclusions()),
    Csrf::class => factory(fn(ContainerInterface $c): Csrf => new Csrf($c->get('CsrfExclusions'))),

    // -------------------------------------------------------------------------
    // Fields definitions
    // -------------------------------------------------------------------------
    'texts_fields' => factory(function (): array {
        include GALETTE_ROOT . 'includes/fields_defs/texts_fields.php';
        /** @var array<array<string,string>> $texts_fields */
        return $texts_fields;
    }),
    'members_fields' => factory(function (): array {
        include GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        /** @var array<array<string,string|bool|int>> $members_fields */
        return $members_fields;
    }),
    'members_form_fields' => factory(function (ContainerInterface $c): array {
        $fields = $c->get('members_fields');
        foreach ($fields as $k => $field) {
            if (!FieldsConfig::isColumn($field)) {
                unset($fields[$k]);
            }
        }
        return $fields;
    }),
    'members_fields_cats' => factory(function (): array {
        include GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
        /** @var array<array<string,string|int>> $members_fields_cats */
        return $members_fields_cats;
    }),
    FieldsConfig::class => factory(fn(ContainerInterface $c): FieldsConfig => new FieldsConfig(
        zdb: $c->get(Db::class),
        table: Adherent::TABLE,
        defaults: $c->get('members_fields'),
        cats_defaults: $c->get('members_fields_cats')
    )),
    ListsConfig::class => factory(fn(ContainerInterface $c): ListsConfig => new ListsConfig(
        zdb: $c->get(Db::class),
        table: Adherent::TABLE,
        defaults: $c->get('members_fields'),
        cats_defaults: $c->get('members_fields_cats')
    )),
];
