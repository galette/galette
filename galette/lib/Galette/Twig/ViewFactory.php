<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Twig;

use Galette\Core\Db;
use Galette\Core\Galette;
use Galette\Core\I18n;
use Galette\Core\Login;
use Galette\Core\Logo;
use Galette\Core\Navigation;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Util\Telemetry;
use Psr\Container\ContainerInterface;
use RKA\Session;
use Slim\Flash\Messages;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\String\StringExtension;

/**
 * Builds the Twig view: templates paths (core and plugins), extensions and globals
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ViewFactory
{
    /**
     * Create the Twig view
     *
     * @param ContainerInterface $c Container instance
     */
    public function create(ContainerInterface $c): Twig
    {
        /** @var Plugins $plugins */
        $plugins = $c->get(Plugins::class);
        /** @var Preferences $preferences */
        $preferences = $c->get(Preferences::class);
        /** @var I18n $i18n */
        $i18n = $c->get(I18n::class);
        /** @var Login $login */
        $login = $c->get(Login::class);

        $templates = ['__main__' => GALETTE_TPL_THEME_DIR];
        foreach ($plugins->getActiveModules() as $module_id => $module) {
            $dir = $module['root'] . '/templates/' . $preferences->pref_theme;
            if (!is_dir($dir)) {
                continue;
            }
            $templates[$plugins->getClassName($module_id)] = $dir;
        }

        $view = Twig::create(
            $templates,
            [
                'cache' => rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'templates',
                'debug' => Galette::isDebugEnabled(),
                'strict_variables' => Galette::isDebugEnabled()
            ]
        );

        //Twig extensions
        $view->addExtension($c->get(GettextExtension::class));
        $view->addExtension($c->get(StaticExtension::class));
        $view->addExtension($c->get(FeatureFlagExtension::class));
        $view->addExtension(new StringExtension());
        $view->addExtension(new IntlExtension());
        if (Galette::isDebugEnabled()) {
            $view->addExtension(new DebugExtension());
            if (class_exists(\AlisQI\TwigQI\Extension::class)) {
                global $logger;
                $view->addExtension(new \AlisQI\TwigQI\Extension($logger));
            }
        }

        //Twig globals
        $env = $view->getEnvironment();
        $env->addGlobal('flash', $c->get(Messages::class));
        $env->addGlobal('login', $login);
        $env->addGlobal('logo', $c->get(Logo::class));
        $env->addGlobal('plugin_headers', $plugins->getTplHeaders());
        $env->addGlobal('plugin_scripts', $plugins->getTplScripts());
        $env->addGlobal('display_version', Galette::gitVersion(time: false));
        $env->addGlobal('languages', $i18n->getList());
        $env->addGlobal('i18n', $i18n);
        $env->addGlobal('plugins', $plugins);
        $env->addGlobal('navigation', $c->get(Navigation::class));
        $env->addGlobal('preferences', $preferences);
        $env->addGlobal('existing_mailing', $c->get(Session::class)->mailing !== null);
        $env->addGlobal('html_editor', value: false);
        $env->addGlobal('require_charts', value: false);
        $env->addGlobal('require_mass', value: false);
        $env->addGlobal('autocomplete', value: false);
        if ($login->isAdmin() && $preferences->pref_telemetry_date) {
            $telemetry = new Telemetry(
                $c->get(Db::class),
                $preferences,
                $plugins
            );
            if ($telemetry->shouldRenew()) {
                $env->addGlobal('renew_telemetry', value: true);
            }
        }

        $env->addGlobal('cur_route', value: null);
        $env->addGlobal('cur_subroute', value: null);
        $env->addGlobal('navigate', value: null);

        //TRANS: see https://fomantic-ui.com/modules/calendar.html#custom-format - must be the same as Y-m-d for PHP https://www.php.net/manual/datetime.format.php
        $env->addGlobal('fui_dateformatter', __("YYYY-MM-DD"));

        return $view;
    }
}
