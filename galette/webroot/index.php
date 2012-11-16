<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's instanciation and routes
 *
 * PHP version 5
 *
 * Copyright © 2012 The Galette Team
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
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.7.3dev 2012-11-13
 */

use \Slim\Extras\Views\Smarty as SmartyView;

$time_start = microtime(true);

//define galette's root directory
if ( !defined('GALETTE_ROOT') ) {
    define('GALETTE_ROOT', __DIR__ . '/../');
}

// define relative base path templating can use
if ( !defined('GALETTE_BASE_PATH') ) {
    define('GALETTE_BASE_PATH', '../');
}

/** @ignore */
require_once GALETTE_ROOT . 'includes/galette.inc.php';

$app = new \Slim\Slim(
    array(
        'view' => new \Slim\Extras\Views\Smarty()/*,
        'log.enable' => true,
        'log.level' => \Slim\Log::DEBUG,
        'debug' => true*/
    )
);

$authenticate = function ($app) use (&$session) {
    return function () use ($app, &$session) {
        if ( isset($session['login']) ) {
            $login = unserialize($session['login']);
        } else {
            $login = new Galette\Core\Login();
        }
        if ( !$login->isLogged() ) {
            $session['urlRedirect'] = $app->request()->getPathInfo();
            $app->flash('error', _T("Login required"));
            $app->redirect($app->urlFor('slash'));
        }
    };
};

$baseRedirect = function ($app) use ($login, $session) {
    $urlRedirect = null;
    if (isset($session['urlRedirect'])) {
        $urlRedirect = $app->request()->getRootUri() . $session['urlRedirect'];
        unset($session['urlRedirect']);
    }

    /*echo 'logged? ' . $login->isLogged();
    var_dump($login);*/
    if ( $login->isLogged() ) {
        if ( $urlRedirect !== null ) {
            $app->redirect($urlRedirect);
        } else {
            if ( $login->isSuperAdmin()
                || $login->isAdmin()
                || $login->isStaff()
            ) {
                if ( !isset($_COOKIE['show_galette_dashboard'])
                    || $_COOKIE['show_galette_dashboard'] == 1
                ) {
                    $app->redirect($app->urlFor('dashboard'));
                } else {
                    $app->redirect($app->urlFor('members'));
                }
            } else {
                $app->redirect($app->urlFor('me'));
            }
        }
    } else {
        $app->redirect($app->urlFor('login'));
    }
};

$app->hook(
    'slim.before.dispatch',
    function () use ($app) {
        $curUri = str_replace(
            'index.php',
            '',
            $app->request()->getRootUri()
        );

        $v = $app->view();
        $v->setData('galette_base_path', $curUri);
        $v->setData('cur_path', $app->request()->getPathInfo());
        $v->setData('require_tabs', null);
        $v->setData('require_cookie', null);
        $v->setData('contentcls', null);
        $v->setData('require_tabs', null);
        $v->setData('require_cookie', false);
        $v->setData('additionnal_html_class', null);
        $v->setData('require_calendar', null);
        $v->setData('head_redirect', null);
        $v->setData('error_detected', null);
        $v->setData('warning_detected', null);
        $v->setData('success_detected', null);
        $v->setData('color_picker', null);
        $v->setData('require_sorter', null);
        $v->setData('require_dialog', null);
        $v->setData('require_tree', null);
        $v->setData('existing_mailing', null);
        $v->setData('html_editor', null);
    }
);

$app->get(
    '/',
    function () use ($app, $baseRedirect) {
        $baseRedirect($app);
    }
)->name('slash');

//routes for anonymous users
$app->get(
    '/login',
    function () use ($app, $login, $baseRedirect, &$session) {
        //store redirect path if any
        if ($app->request()->get('r')
            && $app->request()->get('r') != '/logout'
            && $app->request()->get('r') != '/login'
        ) {
            $session['urlRedirect'] = $app->request()->get('r');
        }

        if ( !$login->isLogged() ) {
            // display page
            $app->render(
                'index.tpl',
                array(
                    'page_title'    => _T("Login"),
                )
            );
        } else {
            $baseRedirect($app);
        }
    }
)->name('login');

// Authentication procedure
$app->post(
    '/login',
    function () use ($app, &$session, $hist, $preferences, $login, $baseRedirect) {
        $nick = $app->request()->post('login');
        $password = $app->request()->post('password');

        if ( trim($nick) == '' || trim($password) == '' ) {
            $app->flash(
                'loginfault',
                _T("You must provide both login and password.")
            );
            $app->redirect($app->urlFor('login'));
        }

        if ( $nick === $preferences->pref_admin_login ) {
            $pw_superadmin = password_verify(
                $password,
                $preferences->pref_admin_pass
            );
            if ( !$pw_superadmin ) {
                $pw_superadmin = (
                    md5($password) === $preferences->pref_admin_pass
                );
            }
            if ( $pw_superadmin ) {
                $login->logAdmin($nick);
            }
        } else {
            $login->logIn($nick, $password);
        }

        if ( $login->isLogged() ) {
            $session['login'] = serialize($login);
            $hist->add(_T("Login"));
            $baseRedirect($app);
        } else {
            $app->flash('loginfault', _T("Login failed."));
            $hist->add(_T("Authentication failed"), $nick);
            $app->redirect($app->urlFor('login'));
        }
    }
)->name('dologin');

$app->get(
    '/logout',
    function () use ($app, $login, &$session) {
        $login->logOut();
        $session['login'] = null;
        unset($session['login']);
        $session['history'] = null;
        unset($session['history']);
        $app->redirect($app->urlFor('slash'));
    }
)->name('logout');

$app->get(
    '/logo',
    function () use ($logo, $app) {
        $res = $app->response();
        $path = $logo->getPath();
        $res['Content-Type'] = $logo->getMime();
        $res['Content-Length'] = filesize($path);
        readfile($path);
    }
)->name('logo');

//routes for authenticated users
//routes for admins
//routes for admins/staff
$app->get(
    '/dashboard',
    $authenticate($app),
    function () use ($app, $preferences) {
        $news = new Galette\IO\News($preferences->pref_rss_url);

        $app->render(
            'desktop.tpl',
            array(
                'page_title'        => _T("Dashboard"),
                'contentcls'        => 'desktop',
                'news'              => $news->getPosts(),
                'show_dashboard'    => $_COOKIE['show_galette_dashboard'],
                'require_cookie'    => true
            )
        );

    }
)->name('dashboard');

$app->get(
    '/members',
    function () use ($app) {
        echo 'empty';
    }
)->name('members');

$app->run();
