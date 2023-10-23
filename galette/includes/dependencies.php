<?php

/**
 * Dependency injection configuration
 *
 * PHP version 5
 *
 * Copyright © 2003-2023 The Galette Team
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
 * @category  Functions
 * @package   Galette
 *
 * @author    Frédéric Jacquot <unknown@unknow.com>
 * @author    Georges Khaznadar (password encryption, images) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;
use Slim\Views\Twig;

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
$container->set('Slim\Views\Twig', function (ContainerInterface $c) {

    $templates = ['__main__' => GALETTE_TPL_THEME_DIR];
    foreach ($c->get('plugins')->getModules() as $module_id => $module) {
        $dir = $module['root'] . '/templates/' . $c->get('preferences')->pref_theme;
        if (!is_dir($dir)) {
            continue;
        }
        $templates[$c->get('plugins')->getClassName($module_id)] = $dir;
    }

    $view = Twig::create(
        $templates,
        [
            'cache' => rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR),
            'debug' => (GALETTE_MODE === \Galette\Core\Galette::MODE_DEV),
            'strict_variables' => (GALETTE_MODE == \Galette\Core\Galette::MODE_DEV)
        ]
    );

    //Twig extensions
    $view->addExtension(new \Galette\Twig\CsrfExtension($c->get('csrf')));
    if (GALETTE_MODE === \Galette\Core\Galette::MODE_DEV) {
        $view->addExtension(new \Twig\Extension\DebugExtension());
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
        return _Tn($singular, $plural, $count, $domain);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('_Tx', function ($context, $string, $domain = 'galette') {
        return _Tx($context, $string, $domain);
    });
    $view->getEnvironment()->addFunction($function);

    $function = new \Twig\TwigFunction('_Tnx', function ($context, $singular, $plural, $count, $domain = 'galette') {
        return _Tnx($context, $singular, $plural, $count, $domain);
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
        return Galette\Entity\Adherent::getSName($c->get('zdb'), $id);
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
    $view->getEnvironment()->addGlobal('flash', $c->get('flash'));
    $view->getEnvironment()->addGlobal('login', $c->get('login'));
    $view->getEnvironment()->addGlobal('logo', $c->get('logo'));

    $view->getEnvironment()->addGlobal('plugin_headers', $c->get('plugins')->getTplHeaders());

    // galette_lang should be removed and languages used instead
    $view->getEnvironment()->addGlobal('galette_lang', $c->get('i18n')->getAbbrev());
    $view->getEnvironment()->addGlobal('galette_lang_name', $c->get('i18n')->getName());
    $view->getEnvironment()->addGlobal('languages', $c->get('i18n')->getList());
    $view->getEnvironment()->addGlobal('i18n', $c->get('i18n'));
    $view->getEnvironment()->addGlobal('plugins', $c->get('plugins'));
    $view->getEnvironment()->addGlobal('preferences', $c->get('preferences'));
    $view->getEnvironment()->addGlobal('existing_mailing', $c->get('session')->mailing !== null);
    $view->getEnvironment()->addGlobal('html_editor', false);
    $view->getEnvironment()->addGlobal('require_charts', false);
    $view->getEnvironment()->addGlobal('require_mass', false);
    $view->getEnvironment()->addGlobal('autocomplete', false);
    if ($c->get('login')->isAdmin() && $c->get('preferences')->pref_telemetry_date) {
        $telemetry = new \Galette\Util\Telemetry(
            $c->get('zdb'),
            $c->get('preferences'),
            $c->get('plugins')
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
//TODO: old way - to drop
$container->set(
    'flash',
    DI\get('Slim\Flash\Messages')
);
$container->set('Slim\Flash\Messages', DI\autowire());

//TODO: old way - to drop
$container->set(
    'plugins',
    \DI\get(Galette\Core\Plugins::class)
);

$container->set(Galette\Core\Plugins::class, function (ContainerInterface $c) use ($plugins) {
    $i18n = $c->get('i18n');
    $plugins->loadModules($c->get('preferences'), GALETTE_PLUGINS_PATH, $i18n->getLongID());
    return $plugins;
});

//TODO: old way - to drop
$container->set(
    'i18n',
    \DI\get('Galette\Core\I18n')
);

$container->set('Galette\Core\I18n', function (ContainerInterface $c) {
    $i18n = $c->get('session')->i18n;
    if (!$i18n || !$i18n->getId() || isset($_GET['ui_pref_lang']) && $_GET['ui_pref_lang']) {
        $i18n = new Galette\Core\I18n($_GET['ui_pref_lang'] ?? false);
        $c->get('session')->i18n = $i18n;
    }
    return $i18n;
});

$container->set('l10n', function (ContainerInterface $c) {
    $l10n = new Galette\Core\L10n(
        $c->get('zdb'),
        $c->get('i18n')
    );
    return $l10n;
});

//TODO: old way - to drop
$container->set(
    'zdb',
    DI\get('Galette\Core\Db')
);
$container->set('Galette\Core\Db', DI\autowire());

//TODO: old way - to drop
$container->set(
    'preferences',
    DI\get('Galette\Core\Preferences')
);
$container->set('Galette\Core\Preferences', DI\autowire());

//TODO: old way - to drop
$container->set(
    'login',
    DI\get('Galette\Core\Login')
);
$container->set('Galette\Core\Login', function (ContainerInterface $c) {
    $login = $c->get('session')->login;
    if (!$login) {
        $login = new Galette\Core\Login(
            $c->get('zdb'),
            $c->get('i18n')
        );
    }
    return $login;
});

//TODO: old way - to drop
$container->set(
    'logo',
    DI\get('Galette\Core\Logo')
);
$container->set('Galette\Core\Logo', DI\autowire());

//TODO: old way - to drop
$container->set(
    'print_logo',
    DI\get('Galette\Core\PrintLogo')
);
$container->set('Galette\Core\PrintLogo', DI\autowire());

//TODO: old way - to drop
$container->set(
    'history',
    DI\get('Galette\Core\History')
);
$container->set('Galette\Core\History', \DI\autowire());

$container->set('acls', function (ContainerInterface $c) {
    include GALETTE_ROOT . 'includes/core_acls.php';
    $acls = $core_acls;

    foreach ($c->get('plugins')->getModules() as $plugin) {
        $acls[$plugin['route'] . 'Info'] = 'member';
    }

    //use + to be sure core ACLs are not overrided by plugins ones.
    $acls = $acls + $c->get('plugins')->getAcls();

    //load user defined ACLs
    if (file_exists(GALETTE_CONFIG_PATH . 'local_acls.inc.php')) {
        //use array_merge here, we want $local_acls to override core ones.
        $acls = array_merge($acls, $local_acls);
    }

    return $acls;
});

$container->set('texts_fields', function (ContainerInterface $c) {
    include_once GALETTE_ROOT . 'includes/fields_defs/texts_fields.php';
    return $texts_fields;
});

$container->set('members_fields', function (ContainerInterface $c) {
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

$container->set('members_fields_cats', function (ContainerInterface $c) {
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

//TODO: old way - to drop
$container->set(
    'fields_config',
    DI\get('Galette\Entity\FieldsConfig')
);
$container->set('Galette\Entity\FieldsConfig', function (ContainerInterface $c) {
    $fc = new Galette\Entity\FieldsConfig(
        $c->get('zdb'),
        Galette\Entity\Adherent::TABLE,
        $c->get('members_fields'),
        $c->get('members_fields_cats')
    );
    return $fc;
});

//TODO: old way - to drop
$container->set(
    'lists_config',
    DI\get('Galette\Entity\ListsConfig')
);
$container->set('Galette\Entity\ListsConfig', function (ContainerInterface $c) {
    $fc = new Galette\Entity\ListsConfig(
        $c->get('zdb'),
        Galette\Entity\Adherent::TABLE,
        $c->get('members_fields'),
        $c->get('members_fields_cats')
    );
    return $fc;
});

//TODO: old way - to drop
$container->set(
    'translator',
    DI\get('Galette\Core\Translator')
);
$container->set('Galette\Core\Translator', function (ContainerInterface $c) {
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

    $translator->setLocale($c->get('i18n')->getLongID());
    return $translator;
});

// Add Event manager to dependency.
$container->set(
    'event_manager',
    DI\create(\League\Event\EventDispatcher::class)
        ->method(
            'subscribeListenersFrom',
            DI\get('Galette\Events\MemberListener')
        )
        ->method(
            'subscribeListenersFrom',
            DI\get('Galette\Events\ContribListener')
        )
);

$container->set(
    'CsrfExclusions',
    function (ContainerInterface $c): array {
        return $c->get('plugins')->getCsrfExclusions();
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
                if (preg_match($exclusion, $route->getname())) {
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

//For bad existing globals can be used...
global $translator, $i18n;
if (
    !$container->has('galette.mode')
    || $container->get('galette.mode') !== 'INSTALL'
    && $container->get('galette.mode') !== 'NEED_UPDATE'
) {
    global $zdb, $preferences, $login, $hist, $l10n, $emitter;
    $zdb = $container->get('zdb');
    $preferences = $container->get('preferences');
    $login = $container->get('login');
    $hist = $container->get('history');
    $l10n = $container->get('l10n');
    $emitter = $container->get('event_manager');
    $routeparser = $container->get(RouteParser::class);
}
$i18n = $container->get('i18n');
$translator = $container->get('translator');
require_once GALETTE_ROOT . 'includes/i18n.inc.php';
