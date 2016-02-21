<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's instanciation and routes
 *
 * PHP version 5
 *
 * Copyright © 2012-2014 The Galette Team
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
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-10
 */

use \Slim\Slim;
use \Slim\Route;
use Galette\Core\Smarty;
use Galette\Core\Login;
use \Analog\Analog;

$time_start = microtime(true);

//define galette's root directory
if (!defined('GALETTE_ROOT')) {
    define('GALETTE_ROOT', __DIR__ . '/../');
}

// define relative base path templating can use
if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', '../');
}

$needs_update = false;
/** @ignore */
require_once GALETTE_ROOT . 'includes/galette.inc.php';

//Galette needs database update!
if ($needs_update) {
    $app = new \Slim\App(
        array(
            'templates.path'    => GALETTE_ROOT . 'templates/default/',
            'mode'              => 'NEED_UPDATE'
        )
    );

    define(
        'GALETTE_THEME',
        'themes/default/'
    );

    $app->configureMode(
        'NEED_UPDATE',
        function () use ($app, $i18n) {
            $app->add(new Galette\Core\Middleware($i18n, null, Galette\Core\Middleware::NEED_UPDATE));
        }
    );

    $app->run();
    die();
} else {
    $app = new \Slim\App(
        [
            'settings' => [
                'determineRouteBeforeAppMiddleware' => true,
                'displayErrorDetails' => true,

                // View settings
                /*'view' => [
                    'template_path' => __DIR__ . '/templates',
                    'twig' => [
                        'cache' => __DIR__ . '/../cache/twig',
                        'debug' => true,
                        'auto_reload' => true,
                    ],
                ],*/

                // monolog settings
                'logger' => [
                    'name' => 'app',
                    'path' => __DIR__ . '/../log/app.log',
                ]
            ]
        ]
    );
}

// Set up dependencies
require GALETTE_ROOT . '/includes/dependencies.php';

// Register middleware
/*require __DIR__ . '/../app/middleware.php';

// Register routes
require __DIR__ . '/../app/routes.php';*/


/*$app->configureMode(
    'DEV',
    function () use ($app) {
        $app->config(
            array(
                'debug' => true
            )
        );
    }
);*/

/*$app->configureMode(
    'MAINT',
    function () use ($app, $i18n, $login) {
        $app->add(new Galette\Core\Middleware($i18n, $login));
    }
);*/

//set default conditions
/*Route::setDefaultConditions(
    array(
        'id' => '\d+'
    )
);*/

$smarty = $app->getContainer()->get('view')->getSmarty();
require_once GALETTE_ROOT . 'includes/smarty.inc.php';

/**
 * Authentication middleware
 */
$authenticate = function ($request, $response, $next) use ($container) {
    $login = $container->session->login;

    if (!$login->isLogged()) {
        //$this->session->urlRedirect = $request->getPathInfo();
        $this->flash->addMessage('error_detected', _T("Login required"));
        return $response
            ->withStatus(403)
            ->withHeader('Location', $this->router->pathFor('slash'));
    } else {
        //check for ACLs
        $cur_route = $request->getAttribute('route')->getName();

        //ACLs for plugins
        $acls = array_merge($container->acls, $container->plugins->getAcls());
        if (isset($acls[$cur_route])) {
            $acl = $acls[$cur_route];
            $go = false;
            switch ($acl) {
                case 'superadmin':
                    if ($login->isSuperAdmin()) {
                        $go = true;
                    }
                    break;
                case 'admin':
                    if ($login->isSuperAdmin()
                        || $login->isAdmin()
                    ) {
                        $go = true;
                    }
                    break;
                case 'staff':
                    if ($login->isSuperAdmin()
                        || $login->isAdmin()
                        || $login->isStaff()
                    ) {
                        $go = true;
                    }
                    break;
                case 'groupmanager':
                    if ($login->isSuperAdmin()
                        || $login->isAdmin()
                        || $login->isStaff()
                        || $login->isGroupManager()
                    ) {
                        $go = true;
                    }
                    break;
                case 'member':
                    if ($login->isLogged()) {
                        $go = true;
                    }
                    break;
                default:
                    throw new \RuntimeException(
                        str_replace(
                            '%acl',
                            $acl,
                            _T("Unknown ACL rule '%acl'!")
                        )
                    );
                    break;
            }
            if (!$go) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("You do not have permission for requested URL.")
                );
                return $response
                    ->withStatus(403)
                    ->withHeader('Location', $this->router->pathFor('slash'));
            }
        } else {
            throw new \RuntimeException(
                str_replace(
                    '%name',
                    $cur_route,
                    _T("Route '%name' is not registered in ACLs!")
                )
            );
        }
    }

    return $next($request, $response);
};

/*$authenticate = function () use ($zdb, $i18n, &$session, $acls, $app, $plugins) {
    return function () use ($app, $zdb, &$session, $acls, $plugins, $i18n) {
        $app->flashKeep();
        if (isset($session['login'])) {
            $login = unserialize($session['login']);
            $login->setDb($zdb);
        } else {
            $login = new Login($zdb, $i18n, $session);
        }
        if (!$login->isLogged()) {
            $session['urlRedirect'] = $app->request()->getPathInfo();
            $this->flash->addMessage('error', _T("Login required"));
            $app->redirect($app->pathFor('slash'), 403);
        } else {
            //check for ACLs
            $cur_route = getCurrentRoute($app);
            //ACLs for plugins
            $acls = array_merge($acls, $plugins->getAcls());
            if (isset($acls[$cur_route])) {
                $acl = $acls[$cur_route];
                $go = false;
                switch ($acl) {
                    case 'superadmin':
                        if ($login->isSuperAdmin()) {
                            $go = true;
                        }
                        break;
                    case 'admin':
                        if ($login->isSuperAdmin()
                            || $login->isAdmin()
                        ) {
                            $go = true;
                        }
                        break;
                    case 'staff':
                        if ($login->isSuperAdmin()
                            || $login->isAdmin()
                            || $login->isStaff()
                        ) {
                            $go = true;
                        }
                        break;
                    case 'groupmanager':
                        if ($login->isSuperAdmin()
                            || $login->isAdmin()
                            || $login->isStaff()
                            || $login->isGroupManager()
                        ) {
                            $go = true;
                        }
                        break;
                    case 'member':
                        if ($login->isLogged()) {
                            $go = true;
                        }
                        break;
                    default:
                        throw new \RuntimeException(
                            str_replace(
                                '%acl',
                                $acl,
                                _T("Unknown ACL rule '%acl'!")
                            )
                        );
                        break;
                }
                if (!$go) {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("You do not have permission for requested URL.")
                    );
                    $app->redirect($app->pathFor('slash'), 403);
                }
            } else {
                throw new \RuntimeException(
                    str_replace(
                        '%name',
                        $cur_route,
                        _T("Route '%name' is not registered in ACLs!")
                    )
                );
            }
        }
    };
};*/

/**
 * Redirection middleware.
 * Each user sill have a default homepage varying on it status (logged in or not, its credentials, etc.
 */
$baseRedirect = function ($request, $response, $args = []) use ($app, $container) {
    $login = $container->get('login');
    $router = $container->get('router');
    $session = $container->get('session');

    //$app->flashKeep();
    if ($login->isLogged()) {
        $urlRedirect = null;
        if ($session->urlRedirect !== null) {
            $urlRedirect = $app->request()->getRootUri() . $session->urlRedirect;
            $session->urlRedirect = null;
        }

        if ($urlRedirect !== null) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $urlRedirect);
        } else {
            if ($login->isSuperAdmin()
                || $login->isAdmin()
                || $login->isStaff()
            ) {
                if (!isset($_COOKIE['show_galette_dashboard'])
                    || $_COOKIE['show_galette_dashboard'] == 1
                ) {
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $router->pathFor('dashboard'));
                } else {
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $router->pathFor('members'));
                }
            } else {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $router->pathFor('me'));
            }
        }
    } else {
        return $response
            ->withStatus(301)
            ->withHeader('Location', $router->pathFor('login'));
    }
};

/**
 * Get current URI
 *
 * @param app $app Slim application instance
 *
 * @return string
 */
function getCurrentUri($app)
{
    $curUri = str_replace(
        'index.php',
        '',
        $app->request()->getRootUri()
    );

    //add ending / if missing
    if ($curUri === ''
        || $curUri !== '/'
        && substr($curUri, -1) !== '/'
    ) {
        $curUri .= '/';
    }
    return $curUri;
};

/**
 * Retrieve current route name
 *
 * @param app $app Slim application instance
 *
 * @return string
 */
function getCurrentRoute($app)
{
    $cur_route = $app->router()->getMatchedRoutes(
        'get',
        $app->request()->getPathInfo()
    )[0]->getName();
    return $cur_route;
}

$app->add(function ($request, $response, $next) {
    $route = $request->getAttribute('route');
    /*$name = $route->getName();
    $group = $route->getGroup();
    $methods = $route->getMethods();
    $arguments = $route->getArguments();*/

    /*var_dump($this->get('router')->getRoutes());
    throw new \RuntimeException('STOP');*/
    /*if ($request->getAttribute('route')->getArgument('auth', true)) {
        $response->write('Authed: ');
    }*/
    return $next($request, $response);
});

/**
 * This is important this one to be the last, so it'll be executed first.
 */
$app->add(function ($request, $response, $next) {
    $route = $request->getAttribute('route');

    if ($route != null) {
        $this->view->getSmarty()->assign('cur_route', $route->getName());
    }

    $acls = array_merge($this->get('acls'), $this->get('plugins')->getAcls());

    if (GALETTE_MODE === 'DEV') {
        //check for routes that are not in ACLs
        $routes = $this->get('router')->getRoutes();

        $missing_acls = [];
        $excluded_names = [
            'public_members',
            'public_trombinoscope'
        ];
        foreach ($routes as $route) {
            $name = $route->getName();
            //check if route has $authenticate middleware
            $middlewares = $route->getMiddleware();
            if (count($middlewares) > 0) {
                foreach ($middlewares as $middleware) {
                    if (!in_array($name, array_keys($acls))
                        && !in_array($name, $excluded_names)
                        && !in_array($name, $missing_acls)
                    ) {
                        $missing_acls[] = $name;
                    }
                }
            }
        }
        if (count($missing_acls) > 0) {
            $msg = str_replace(
                '%routes',
                implode(', ', $missing_acls),
                _T("Routes '%routes' are missing in ACLs!")
            );
            Analog::log($msg, Analog::ERROR);
            //FIXME: with flash(), message is only shown on the seconde round,
            //with flashNow(), thas just does not work :(
            $this->flash->addMessage('error_detected', $msg);
        }
    }

    return $next($request, $response);
});

/*$app->hook(
    'slim.before.dispatch',
    function () use ($app, $error_detected, $warning_detected, $success_detected,
        $authenticate, $acls, $plugins
    ) {
        $acls = array_merge($acls, $plugins->getAcls());
        if ( GALETTE_MODE === 'DEV' ) {
            //check for routes that are not in ACLs
            $named_routes = $app->router()->getNamedRoutes();
            $missing_acls = [];
            $excluded_names = [
                'public_members',
                'public_trombinoscope'
            ];
            foreach ( $named_routes as $name=>$route ) {
                //check if route has $authenticate middleware
                $middlewares = $route->getMiddleware();
                if ( count($middlewares) > 0 ) {
                    foreach ( $middlewares as $middleware ) {
                        if ( !in_array($name, array_keys($acls))
                            && !in_array($name, $excluded_names)
                            && !in_array($name, $missing_acls)
                        ) {
                            $missing_acls[] = $name;
                        }
                    }
                }
            }
            if ( count($missing_acls) > 0 ) {
                $msg = str_replace(
                    '%routes',
                    implode(', ', $missing_acls),
                    _T("Routes '%routes' are missing in ACLs!")
                );
                Analog::log($msg, Analog::ERROR);
                //FIXME: with flash(), message is only shown on the seconde round,
                //with flashNow(), thas just does not work :(
                $this->flash->addMessage('error_detected', [$msg]);
            }
        }

        $v = $app->view();

        $v->setData('galette_base_path', getCurrentUri($app));
        $v->setData('cur_route', getCurrentRoute($app));
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

        //FIXME: no longer works, should be set with $app::flash()
        if ( isset($error_detected) ) {
            $v->setData('error_detected', $error_detected);
        }
        //FIXME: no longer works, should be set with $app::flash()
        if (isset($warning_detected)) {
            $v->setData('warning_detected', $warning_detected);
        }
        //FIXME: no longer works, should be set with $app::flash()
        if (isset($success_detected)) {
            $v->setData('success_detected', $success_detected);
        }
    }
);*/

$app->add(new \RKA\SessionMiddleware(['name' => 'galette_' . $session_name]));

require_once GALETTE_ROOT . 'includes/routes/main.routes.php';
require_once GALETTE_ROOT . 'includes/routes/authentication.routes.php';
require_once GALETTE_ROOT . 'includes/routes/management.routes.php';
require_once GALETTE_ROOT . 'includes/routes/members.routes.php';
require_once GALETTE_ROOT . 'includes/routes/groups.routes.php';
require_once GALETTE_ROOT . 'includes/routes/contributions.routes.php';
require_once GALETTE_ROOT . 'includes/routes/public_pages.routes.php';
require_once GALETTE_ROOT . 'includes/routes/ajax.routes.php';
require_once GALETTE_ROOT . 'includes/routes/plugins.routes.php';

//custom error handler
//will not be used if mode is DEV.
/*$app->error(
    function (\Exception $e) use ($app) {
        //ensure error is logged
        $etype = get_class($e);
        Analog::log(
            'exception \'' . $etype . '\' with message \'' . $e->getMessage() .
            '\' in ' . $e->getFile() . ':' . $e->getLine() .
            "\nStack trace:\n" . $e->getTraceAsString(),
            Analog::ERROR
        );

        $app->render(
            '500.tpl',
            array(
                'page_title'        => _T("Error"),
                'exception'         => $e,
                'galette_base_path' => getCurrentUri($app)
            )
        );
    }
);*/

//custom 404 handler
/*$app->notFound(
    function () use ($app) {
        $app->render(
            '404.tpl',
            array(
                'page_title'        => _T("Page not found :("),
                'cur_route'         => null,
                'galette_base_path' => getCurrentUri($app)
            )
        );
    }
);*/

$app->run();

if (isset($profiler)) {
    $profiler->stop();
}
