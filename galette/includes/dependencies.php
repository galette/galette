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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;
use Slim\Views\Twig;
use Twig\Extra\String\StringExtension;
use Twig\Extra\Intl\IntlExtension;

$container = $app->getContainer();

$routeParser = $app->getRouteCollector()->getRouteParser();
$container->set(RouteParser::class, $routeParser);

// -----------------------------------------------------------------------------
// Service providers
// -----------------------------------------------------------------------------

$container->set(
    \Slim\Routing\RouteCollector::class,
    function () use ($app) {
        return $app->getRouteCollector();
    }
);

// Register View helper
$container->set(\Slim\Views\Twig::class, function (ContainerInterface $c) {

    $templates = ['__main__' => GALETTE_TPL_THEME_DIR];
    foreach ($c->get(\Galette\Core\Plugins::class)->getModules() as $module_id => $module) {
        $dir = $module['root'] . '/templates/' . $c->get(\Galette\Core\Preferences::class)->pref_theme;
        if (!is_dir($dir)) {
            continue;
        }
        $templates[$c->get(\Galette\Core\Plugins::class)->getClassName($module_id)] = $dir;
    }

    $view = Twig::create(
        $templates,
        [
            'cache' => rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'templates',
            'debug' => \Galette\Core\Galette::isDebugEnabled(),
            'strict_variables' => \Galette\Core\Galette::isDebugEnabled()
        ]
    );

    //Twig extensions
    $view->addExtension(new \Galette\Twig\CsrfExtension($c->get('csrf')));
    $view->addExtension(new StringExtension());
    $view->addExtension(new IntlExtension());
    if (\Galette\Core\Galette::isDebugEnabled()) {
        $view->addExtension(new \Twig\Extension\DebugExtension());
        if (class_exists('AlisQI\TwigQI\Extension')) {
            global $logger;
            $view->addExtension(new AlisQI\TwigQI\Extension($logger));
        }
    }
    //End Twig extensions

    //Twig functions
    $function = new \Twig\TwigFunction('__', function ($string, $domain = 'galette') {
        return __($string, $domain);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('_T', function ($string, $domain = 'galette') {
        return _T($string, $domain);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('_Tn', function ($singular, $plural, $count, $domain = 'galette') {
        return _Tn($singular, $plural, (int)$count, $domain);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('_Tx', function ($context, $string, $domain = 'galette') {
        return _Tx($context, $string, $domain);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('_Tnx', function ($context, $singular, $plural, $count, $domain = 'galette') {
        return _Tnx($context, $singular, $plural, (int)$count, $domain);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('file_exists', function ($file) {
        return file_exists($file);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('get_class', function ($object) {
        return get_class($object);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('memberName', function (...$params) use ($c) {
        extract($params[0]);
        return Galette\Entity\Adherent::getSName($c->get(\Galette\Core\Db::class), $id);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('statusLabel', function (...$params) {
        extract($params);
        global $statuses_list;
        return $statuses_list[$id];
    });
    $view->getEnvironment()->addFunction($function);

    $view->getEnvironment()->addFunction(
        new \Twig\TwigFunction('callstatic', function ($class, $method, ...$args) {
            if (!class_exists($class)) {
                throw new \Exception("Cannot call static method $method on Class $class: Invalid Class");
            }

            if (!method_exists($class, $method)) {
                throw new \Exception("Cannot call static method $method on Class $class: Invalid method");
            }

            return forward_static_call_array([$class, $method], $args);
        })
    );
    //End Twig functions

    //Twig globals
    $view->getEnvironment()->addGlobal('flash', $c->get(\Slim\Flash\Messages::class));
    $view->getEnvironment()->addGlobal('login', $c->get(\Galette\Core\Login::class));
    $view->getEnvironment()->addGlobal('logo', $c->get(\Galette\Core\Logo::class));

    $view->getEnvironment()->addGlobal('plugin_headers', $c->get(\Galette\Core\Plugins::class)->getTplHeaders());
    $view->getEnvironment()->addGlobal('plugin_scripts', $c->get(\Galette\Core\Plugins::class)->getTplScripts());

    // galette_lang should be removed and languages used instead
    $view->getEnvironment()->addGlobal('galette_lang', $c->get(\Galette\Core\I18n::class)->getAbbrev());
    $view->getEnvironment()->addGlobal('galette_lang_name', $c->get(\Galette\Core\I18n::class)->getName());
    $view->getEnvironment()->addGlobal('languages', $c->get(\Galette\Core\I18n::class)->getList());
    $view->getEnvironment()->addGlobal('i18n', $c->get(\Galette\Core\I18n::class));
    $view->getEnvironment()->addGlobal('plugins', $c->get(\Galette\Core\Plugins::class));
    $view->getEnvironment()->addGlobal('preferences', $c->get(\Galette\Core\Preferences::class));
    $view->getEnvironment()->addGlobal('existing_mailing', $c->get(\RKA\Session::class)->mailing !== null);
    $view->getEnvironment()->addGlobal('html_editor', false);
    $view->getEnvironment()->addGlobal('require_charts', false);
    $view->getEnvironment()->addGlobal('require_mass', false);
    $view->getEnvironment()->addGlobal('autocomplete', false);
    if ($c->get(\Galette\Core\Login::class)->isAdmin() && $c->get(\Galette\Core\Preferences::class)->pref_telemetry_date) {
        $telemetry = new \Galette\Util\Telemetry(
            $c->get(\Galette\Core\Db::class),
            $c->get(\Galette\Core\Preferences::class),
            $c->get(\Galette\Core\Plugins::class)
        );
        if ($telemetry->shouldRenew()) {
            $view->getEnvironment()->addGlobal('renew_telemetry', true);
        }
    }

    $view->getEnvironment()->addGlobal('cur_route', null);
    $view->getEnvironment()->addGlobal('cur_subroute', null);
    $view->getEnvironment()->addGlobal('navigate', null);

    //TRANS: see https://fomantic-ui.com/modules/calendar.html#custom-format - must be the same as Y-m-d for PHP https://www.php.net/manual/datetime.format.php
    $view->getEnvironment()->addGlobal('fui_dateformatter', __("YYYY-MM-DD"));
    //End Twig globals

    return $view;
});

// Flash messages
$container->set(\Slim\Flash\Messages::class, DI\autowire());

$container->set(Galette\Core\Plugins::class, function (ContainerInterface $c) use ($plugins) {
    $i18n = $c->get(\Galette\Core\I18n::class);
    $plugins->loadModules($c->get(\Galette\Core\Preferences::class), GALETTE_PLUGINS_PATH, $i18n->getLongID());
    return $plugins;
});

$container->set(\Galette\Core\I18n::class, function (ContainerInterface $c) {
    $i18n = $c->get(\RKA\Session::class)->i18n;
    if (!$i18n || !$i18n->getId() || isset($_GET['ui_pref_lang']) && $_GET['ui_pref_lang']) {
        $i18n = new Galette\Core\I18n($_GET['ui_pref_lang'] ?? false);
        $c->get(\RKA\Session::class)->i18n = $i18n;
    }
    return $i18n;
});

$container->set(\Galette\Core\Db::class, DI\autowire());

$container->set(\Galette\Core\Preferences::class, DI\autowire());

$container->set(\Galette\Core\Login::class, function (ContainerInterface $c) {
    $login = $c->get(\RKA\Session::class)->login;
    if (!$login) {
        $login = new Galette\Core\Login(
            $c->get(\Galette\Core\Db::class),
            $c->get(\Galette\Core\I18n::class)
        );
    }
    return $login;
});

$container->set(\Galette\Core\Logo::class, DI\autowire());

$container->set(\Galette\Core\PrintLogo::class, DI\autowire());

$container->set(\Galette\Core\History::class, \DI\autowire());

$container->set('acls', function (ContainerInterface $c) {
    include GALETTE_ROOT . 'includes/core_acls.php';
    $acls = $core_acls;

    foreach ($c->get(\Galette\Core\Plugins::class)->getModules() as $plugin) {
        $acls[$plugin['route'] . 'Info'] = 'member';
    }

    //use + to be sure core ACLs are not overridden by plugins ones.
    $acls += $c->get(\Galette\Core\Plugins::class)->getAcls();

    //load user defined ACLs
    if (file_exists(GALETTE_CONFIG_PATH . 'local_acls.inc.php')) {
        //use array_merge here, we want $local_acls to override core ones.
        $acls = array_merge($acls, $local_acls);
    }

    return $acls;
});

$container->set('texts_fields', function () {
    include_once GALETTE_ROOT . 'includes/fields_defs/texts_fields.php';
    return $texts_fields;
});

$container->set('members_fields', function () {
    include GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
    return $members_fields;
});

$container->set('members_form_fields', function (ContainerInterface $c) {
    $fields = $c->get('members_fields');
    foreach ($fields as $k => $field) {
        if ($field['position'] == -1) {
            unset($fields[$k]);
        }
    }
    return $fields;
});

$container->set('members_fields_cats', function () {
    include GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
    return $members_fields_cats;
});

// -----------------------------------------------------------------------------
// Service factories
// -----------------------------------------------------------------------------

// monolog
$container->set('logger', function (ContainerInterface $c) {
    $settings = $c->get('settings');
    $logger = new \Monolog\Logger($settings['logger']['name']);
    $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
    $logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['logger']['path'], $settings['logger']['level']));
    return $logger;
});

$container->set(\Galette\Entity\FieldsConfig::class, function (ContainerInterface $c) {
    return new Galette\Entity\FieldsConfig(
        $c->get(\Galette\Core\Db::class),
        Galette\Entity\Adherent::TABLE,
        $c->get('members_fields'),
        $c->get('members_fields_cats')
    );
});

$container->set(\Galette\Entity\ListsConfig::class, function (ContainerInterface $c) {
    return new Galette\Entity\ListsConfig(
        $c->get(\Galette\Core\Db::class),
        Galette\Entity\Adherent::TABLE,
        $c->get('members_fields'),
        $c->get('members_fields_cats')
    );
});

$container->set(\Galette\Core\Translator::class, function (ContainerInterface $c) {
    $translator = new Galette\Core\Translator();

    $domains = ['galette'];
    foreach ($domains as $domain) {
        //load translation file for domain
        $translator->addTranslationFilePattern(
            'gettext',
            GALETTE_ROOT . '/lang/',
            '/%s/LC_MESSAGES/' . $domain . '.mo',
            $domain
        );

        //check if a local lang file exists and load it
        $translator->addTranslationFilePattern(
            'phparray',
            GALETTE_ROOT . '/lang/',
            $domain . '_%s_local_lang.php',
            $domain
        );
    }

    $translator->setLocale($c->get(\Galette\Core\I18n::class)->getLongID());
    return $translator;
});

// Add Event manager to dependency.
$container->set(
    'event_manager',
    DI\create(\League\Event\EventDispatcher::class)
        ->method(
            'subscribeListenersFrom',
            DI\get(\Galette\Events\MemberListener::class)
        )
        ->method(
            'subscribeListenersFrom',
            DI\get(\Galette\Events\ContribListener::class)
        )
);

$container->set(
    'CsrfExclusions',
    function (ContainerInterface $c): array {
        return $c->get(\Galette\Core\Plugins::class)->getCsrfExclusions();
    }
);

$container->set(
    'csrf',
    function (ContainerInterface $c) use ($app) {
        $responseFactory = $app->getResponseFactory();
        $storage = null;
        $guard = new \Slim\Csrf\Guard(
            $responseFactory,
            'csrf',
            $storage,
            null,
            200,
            16,
            true
        );

        $exclusions = $c->get('CsrfExclusions');
        $guard->setFailureHandler(function (ServerRequestInterface $request, RequestHandler $handler) use ($exclusions) {
            $response = $handler->handle($request);
            $routeContext = RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();

            foreach ($exclusions as $exclusion) {
                if (preg_match($exclusion, (string)$route->getname())) {
                    //route is excluded form CSRF checks
                    return $response;
                }
            }
            Analog::log(
                'CSRF check has failed',
                Analog::CRITICAL
            );
            throw new \RuntimeException(_T('Failed CSRF check!'));
        });

        return $guard;
    }
);

/**
 * TODO: to remove in next major
 * @deprecated 1.2.1
 */
$deprecateds = [
    'flash' => \Slim\Flash\Messages::class,
    'plugins' => \Galette\Core\Plugins::class,
    'i18n' => \Galette\Core\I18n::class,
    'l10n' => \Galette\Core\L10n::class,
    'zdb' => \Galette\Core\Db::class,
    'preferences' => \Galette\Core\Preferences::class,
    'login' => \Galette\Core\Login::class,
    'logo' => \Galette\Core\Logo::class,
    'print_logo' => \Galette\Core\PrintLogo::class,
    'history' => \Galette\Core\History::class,
    'fields_config' => \Galette\Entity\FieldsConfig::class,
    'lists_config' => \Galette\Entity\ListsConfig::class,
    'translator' => \Galette\Core\Translator::class,
];

foreach ($deprecateds as $deprecated => $class) {
    $container->set(
        $deprecated,
        function (ContainerInterface $c) use ($deprecated, $class) {
            Analog::log(
                sprintf(
                    'Calling "%1$s" on DI will be removed in a future version, use "%2$s::class" instead.',
                    $deprecated,
                    $class
                ),
                Analog::WARNING,
            );
            return $c->get($class);
        }
    );
}
//END TODO

//For bad existing globals can be used...
global $translator, $i18n;
if (
    !$container->has('galette.mode')
    || $container->get('galette.mode') !== 'INSTALL' //legacy case
    && $container->get('galette.mode') !== 'NEED_UPDATE'
    && !defined('GALETTE_INSTALLER')
) {
    global $zdb, $preferences, $login, $hist, $l10n, $emitter;
    $zdb = $container->get(\Galette\Core\Db::class);
    $preferences = $container->get(\Galette\Core\Preferences::class);
    $login = $container->get(\Galette\Core\Login::class);
    $hist = $container->get(\Galette\Core\History::class);
    $l10n = $container->get(\Galette\Core\L10n::class);
    $emitter = $container->get('event_manager');
    $routeparser = $container->get(RouteParser::class);
}
$i18n = $container->get(\Galette\Core\I18n::class);
$translator = $container->get(\Galette\Core\Translator::class);
require_once GALETTE_ROOT . 'includes/i18n.inc.php';
