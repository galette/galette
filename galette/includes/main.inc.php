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

    $i18n = new Galette\Core\I18n();
    require_once __DIR__ . '/i18n.inc.php';

    $app->add(
        new Galette\Core\Middleware(
            $i18n,
            Galette\Core\Middleware::NEED_UPDATE
        )
    );

    $app->run();
    die();
} else {
    $app = new \Slim\App(
        [
            'settings' => [
                'determineRouteBeforeAppMiddleware' => true,
                'displayErrorDetails' => (GALETTE_MODE === 'DEV'),
                // monolog settings
                'logger' => [
                    'name'  => 'galette',
                    'level' => \Monolog\Logger::DEBUG,
                    'path'  => GALETTE_LOGS_PATH . '/galette_slim.log',
                ],
                'routerCacheFile' => (GALETTE_MODE === 'DEV') ? false : GALETTE_CACHE_DIR . '/fastroute.cache'
            ]
        ]
    );
}

// Set up dependencies
require GALETTE_ROOT . '/includes/dependencies.php';

/*$app->configureMode(
    'MAINT',
    function () use ($app, $i18n, $login) {
        $app->add(new Galette\Core\Middleware($i18n, $login));
    }
);*/


$smarty = $app->getContainer()->get('view')->getSmarty();
require_once GALETTE_ROOT . 'includes/smarty.inc.php';

/**
 * Authentication middleware
 */
$authenticate = function ($request, $response, $next) use ($container) {
    $login = $container->session->login;

    if (!$login || !$login->isLogged()) {
        if ($request->isGet()) {
            $this->session->urlRedirect = $request->getUri()->getPath();
        }
        Analog::log(
            'Login required to access ' . $this->session->urlRedirect,
            Analog::DEBUG
        );
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
                Analog::log(
                    'Permission denied for route ' . $cur_route . ' for user ' . $login->login,
                    Analog::DEBUG
                );
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


//Maintainance middleware
if ('MAINT' === GALETTE_MODE && !$container->get('login')->isSuperAdmin()) {
    $app->add(
        new Galette\Core\Middleware(
            $i18n,
            Galette\Core\Middleware::MAINTENANCE
        )
    );
}


/**
 * Redirection middleware.
 * Each user sill have a default homepage varying on it status (logged in or not, its credentials, etc.
 */
$baseRedirect = function ($request, $response, $args = []) use ($app, $container) {
    $login = $container->get('login');
    $router = $container->get('router');
    $session = $container->get('session');

    $flashes = $container->get('flash')->getMessages();
    foreach ($flashes as $type => $messages) {
        foreach ($messages as $message) {
            $container->get('flash')->addMessage($type, $message);
        }
    }

    if ($login->isLogged()) {
        $urlRedirect = null;
        if ($session->urlRedirect !== null) {
            $urlRedirect = $request->getUri()->getBaseUrl() . $session->urlRedirect;
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

/**
 * Trailing slash middleware
 */
$app->add(function ($request, $response, $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));

        if ($request->getMethod() == 'GET') {
            return $response->withRedirect((string)$uri, 301);
        } else {
            return $next($request->withUri($uri), $response);
        }
    }

    return $next($request, $response);
});

/**
 * Change language middleware
 */
$app->add(function ($request, $response, $next) use ($i18n, $lang) {
    $get = $request->getQueryParams();

    if (isset($get['pref_lang'])) {
        $route = $request->getAttribute('route');
        $uri = $request->getUri();

        $route_name = $route->getName();
        $arguments = $route->getArguments();

        $this->i18n->changeLanguage($get['pref_lang']);
        $this->session->i18n = $this->i18n;
        $this->session->changelang_route = [
            'name'      => $route_name,
            'arguments' => $arguments
        ];

        return $response->withRedirect($this->router->pathFor('changeLanguage'), 301);
    }
    return $next($request, $response);
});

/**
 * This is important this one to be the last, so it'll be executed first.
 */
$app->add(function ($request, $response, $next) {
    $route = $request->getAttribute('route');
    $route_info = $request->getAttribute('routeInfo');

    if ($route != null) {
        $this->view->getSmarty()->assign('cur_route', $route->getName());
        if ($route_info != null && is_array($route_info[2])) {
            $this->view->getSmarty()->assign('cur_subroute', array_shift($route_info[2]));
        }
    }

    $acls = array_merge($this->get('acls'), $this->get('plugins')->getAcls());

    if (GALETTE_MODE === 'DEV') {
        //check for routes that are not in ACLs
        $routes = $this->get('router')->getRoutes();

        $missing_acls = [];
        $excluded_names = [
            'publicMembers',
            'filterPublicMemberslist',
            'publicTrombinoscope'
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

$app->run();

if (isset($profiler)) {
    $profiler->stop();
}
