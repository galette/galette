<?php

/**
 * Dependency injection configuration
 *
 * PHP version 5
 *
 * Copyright © 2003-2018 The Galette Team
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
 * @copyright 2003-2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

use Galette\Entity\PdfModel;
use Slim\Event\SlimEventManager;
use Slim\Views\SmartyPlugins;

$container = $app->getContainer();

// -----------------------------------------------------------------------------
// Error handling
// -----------------------------------------------------------------------------

$container['errorHandler'] = function ($c) {
    return new Galette\Handlers\Error($c['view'], true);
};

$container['phpErrorHandler'] = function ($c) {
    return new Galette\Handlers\PhpError($c['view'], true);
};

$container['notFoundHandler'] = function ($c) {
    return new Galette\Handlers\NotFound($c['view']);
};

// -----------------------------------------------------------------------------
// Service providers
// -----------------------------------------------------------------------------

// Register Smarty View helper
$container['view'] = function ($c) {
    $view = new \Slim\Views\Smarty(
        rtrim(GALETTE_ROOT . GALETTE_TPL_SUBDIR, DIRECTORY_SEPARATOR),
        [
            'cacheDir' => rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR),
            'compileDir' => rtrim(GALETTE_COMPILE_DIR, DIRECTORY_SEPARATOR),
            'pluginsDir' => [
                GALETTE_ROOT . 'includes/smarty_plugins'
            ]
        ]
    );

    // Add Slim specific plugins
    $basepath = str_replace(
        'index.php',
        '',
        $c->get('request')->getUri()->getBasePath()
    );
    $smartyPlugins = new SmartyPlugins($c['router'], $basepath);
    $view->registerPlugin('function', 'path_for', [$smartyPlugins, 'pathFor']);
    $view->registerPlugin('function', 'base_url', [$smartyPlugins, 'baseUrl']);

    $smarty = $view->getSmarty();
    $smarty->inheritance_merge_compiled_includes = false;

    $smarty->assign('flash', $c->get('flash'));

    $smarty->assign('login', $c->get('login'));
    $smarty->assign('logo', $c->get('logo'));
    $smarty->assign('tpl', $smarty);
    $smarty->assign('headers', $c->get('plugins')->getTplHeaders());
    $smarty->assign('plugin_actions', $c->get('plugins')->getTplAdhActions());
    $smarty->assign(
        'plugin_batch_actions',
        $c->get('plugins')->getTplAdhBatchActions()
    );
    $smarty->assign(
        'plugin_detailled_actions',
        $c->get('plugins')->getTplAdhDetailledActions()
    );
    $smarty->assign('scripts_dir', 'js/');
    $smarty->assign('jquery_dir', 'js/jquery/');
    $smarty->assign('jquery_markitup_version', JQUERY_MARKITUP_VERSION);
    $smarty->assign('PAGENAME', basename($_SERVER['SCRIPT_NAME']));
    $smarty->assign('galette_base_path', './');
    $smarty->assign('GALETTE_VERSION', GALETTE_VERSION);
    $smarty->assign('GALETTE_MODE', GALETTE_MODE);
    $smarty->assign('GALETTE_DISPLAY_ERRORS', GALETTE_DISPLAY_ERRORS);
    $smarty->assign('_CURRENT_THEME_PATH', _CURRENT_THEME_PATH);

    /*if ($this->parserConfigDir) {
        $instance->setConfigDir($this->parserConfigDir);
    }*/

    $smarty->assign('template_subdir', GALETTE_THEME);
    foreach ($c->get('plugins')->getTplAssignments() as $k => $v) {
        $smarty->assign($k, $v);
    }
    /** galette_lang should be removed and languages used instead */
    $smarty->assign('galette_lang', $c->get('i18n')->getAbbrev());
    $smarty->assign('galette_lang_name', $c->get('i18n')->getName());
    $smarty->assign('languages', $c->get('i18n')->getList());
    $smarty->assign('i18n', $c->get('i18n'));
    $smarty->assign('plugins', $c->get('plugins'));
    $smarty->assign('preferences', $c->get('preferences'));
    $smarty->assign('pref_slogan', $c->get('preferences')->pref_slogan);
    $smarty->assign('pref_theme', $c->get('preferences')->pref_theme);
    $smarty->assign('pref_statut', $c->get('preferences')->pref_statut);
    $smarty->assign(
        'pref_editor_enabled',
        $c->preferences->pref_editor_enabled
    );
    $smarty->assign('pref_mail_method', $c->get('preferences')->pref_mail_method);
    $smarty->assign('existing_mailing', $c->get('session')->mailing !== null);
    $smarty->assign('contentcls', null);
    $smarty->assign('additionnal_html_class', null);
    $smarty->assign('head_redirect', null);
    $smarty->assign('error_detected', null);
    $smarty->assign('warning_detected', null);
    $smarty->assign('success_detected', null);
    $smarty->assign('require_tree', null);
    $smarty->assign('html_editor', null);
    $smarty->assign('require_charts', null);
    $smarty->assign('require_mass', null);
    $smarty->assign('autocomplete', null);
    if ($c->get('login')->isAdmin() && $c->get('preferences')->pref_telemetry_date) {
        $now = new \DateTime();
        $sent = new \DateTime($c->get('preferences')->pref_telemetry_date);
        $sent->add(new \DateInterval('P1Y')); // ask to resend telemetry after one year
        if ($now > $sent && !$_COOKIE['renew_telemetry']) {
            $smarty->assign('renew_telemetry', true);
        }
    }

    foreach ($c->get('plugins')->getModules() as $module_id => $module) {
        $smarty->addTemplateDir(
            $module['root'] . '/templates/' . $c->get('preferences')->pref_theme,
            $module['route']
        );
    }
    return $view;
};

// Flash messages
$container['flash'] = function ($c) {
    return new \Slim\Flash\Messages();
};

$container['plugins'] = function ($c) {
    $plugins = new Galette\Core\Plugins();
    $i18n = $c->get('i18n');
    $plugins->loadModules($c->get('preferences'), GALETTE_PLUGINS_PATH, $i18n->getLongID());
    return $plugins;
};

$container['i18n'] = function ($c) {
    $i18n = $c->get('session')->i18n;
    if (!$i18n || !$i18n->getId() || isset($_GET['ui_pref_lang']) && $_GET['ui_pref_lang']) {
        $i18n = new Galette\Core\I18n($_GET['ui_pref_lang'] ?? false);
        $c->get('session')->i18n = $i18n;
    }
    return $i18n;
};

$container['l10n'] = function ($c) {
    $l10n = new Galette\Core\L10n(
        $c->get('zdb'),
        $c->get('i18n')
    );
    return $l10n;
};

$container['zdb'] = function ($c) {
    $zdb = new Galette\Core\Db();
    return $zdb;
};

$container['preferences'] = function ($c) {
    return new Galette\Core\Preferences($c->get('zdb'));
};

$container['login'] = function ($c) {
    $login = $c->get('session')->login;
    if (!$login) {
        $login = new Galette\Core\Login(
            $c->get('zdb'),
            $c->get('i18n'),
            $c->get('session')
        );
    }
    return $login;
};

$container['session'] = function ($c) {
    $session = new \RKA\Session();
    return $session;
};

$container['logo'] = function ($c) {
    return new Galette\Core\Logo();
};

$container['print_logo'] = function ($c) {
    return new Galette\Core\PrintLogo();
};


$container['history'] = function ($c) {
    return new Galette\Core\History($c->get('zdb'), $c->get('login'));
};

$container['acls'] = function ($c) {
    include_once GALETTE_ROOT . 'includes/core_acls.php';
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
};

$container['members_fields'] = function ($c) {
    include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
    return $members_fields;
};

$container['members_form_fields'] = function ($c) {
    $fields = $c->get('members_fields');
    foreach ($fields as $k => $field) {
        if ($field['position'] == -1) {
            unset($fields[$k]);
        }
    }
    return $fields;
};


$container['members_fields_cats'] = function ($c) {
    include_once GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
    return $members_fields_cats;
};

// -----------------------------------------------------------------------------
// Service factories
// -----------------------------------------------------------------------------

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings');
    $logger = new \Monolog\Logger($settings['logger']['name']);
    $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
    $logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['logger']['path'], $settings['logger']['level']));
    return $logger;
};

$container['fields_config'] = function ($c) {
    $fc = new Galette\Entity\FieldsConfig(
        $c->get('zdb'),
        Galette\Entity\Adherent::TABLE,
        $c->get('members_fields'),
        $c->get('members_fields_cats')
    );
    return $fc;
};

$container['lists_config'] = function ($c) {
    $fc = new Galette\Entity\ListsConfig(
        $c->get('zdb'),
        Galette\Entity\Adherent::TABLE,
        $c->get('members_fields'),
        $c->get('members_fields_cats')
    );
    return $fc;
};

$container['cache'] = function ($c) {
    $adapter = null;
    if (function_exists('wincache_ucache_add')) {
        //since APCu is not known to work on windows
        $adapter = 'wincache';
    } elseif (function_exists('apcu_fetch')) {
        $adapter = 'apcu';
    }
    if ($adapter !== null) {
        $uuid = $c->get('mode') !== 'INSTALL' ? $c->get('preferences')->pref_instance_uuid : '_install';
        $cache = Laminas\Cache\StorageFactory::factory([
            'adapter'   => $adapter,
            'options'   => [
                'namespace' => str_replace(
                    ['%version', '%uuid'],
                    [GALETTE_VERSION, $uuid],
                    'galette_%version_%uuid'
                )
            ]
        ]);
        return $cache;
    }
    return null;
};

$container['translator'] = function ($c) {
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
    if (!isset($container['mode']) || $c->get('mode') !== 'INSTALL' && $c->get('mode') !== 'NEED_UPDATE') {
        $translator->setCache($c->get('cache'));
    }
    return $translator;
};

// Add Event manager to dependency.
$container['event_manager'] = function ($c) {
    $emitter = new SlimEventManager();

    $emitter->useListenerProvider(
        new Galette\Events\MemberListener(
            $c->get('preferences'),
            $c->get('router'),
            $c->get('history'),
            $c->get('flash'),
            $c->get('login'),
            $c->get('zdb')
        )
    );

    $emitter->useListenerProvider(
        new Galette\Events\ContribListener(
            $c->get('preferences'),
            $c->get('router'),
            $c->get('history'),
            $c->get('flash'),
            $c->get('login'),
            $c->get('zdb')
        )
    );


    return $emitter;
};

//For bad existing globals can be used...
if (!isset($container['mode']) || ($container['mode'] !== 'INSTALL' && $container['mode'] !== 'NEED_UPDATE')) {
    $zdb = $container->get('zdb');
    $preferences = $container->get('preferences');
    $login = $container->get('login');
    $hist = $container->get('history');
    global $l10n;
    $l10n = $container->get('l10n');
    $emitter = $container->get('event_manager');
}
global $translator, $i18n;
$i18n = $container->get('i18n');
$translator = $container->get('translator');

require_once GALETTE_ROOT . 'includes/i18n.inc.php';
