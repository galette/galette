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

    require_once '../includes/dependencies.php';

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
                'addContentLengthHeader' => false,
                // monolog settings
                'logger' => [
                    'name'  => 'galette',
                    'level' => \Monolog\Logger::DEBUG,
                    'path'  => GALETTE_LOGS_PATH . '/galette_slim.log',
                ],
                //'routerCacheFile' => (GALETTE_MODE === 'DEV') ? false : GALETTE_CACHE_DIR . '/fastroute.cache' //disabled until properly handled
            ],
            'mode'      => 'PROD'
        ]
    );
}

//Session duration
if (!defined('GALETTE_TIMEOUT')) {
    //See php.net/manual/en/session.configuration.php#ini.session.cookie-lifetime
    define('GALETTE_TIMEOUT', 0);
}

$plugins = new Galette\Core\Plugins();
$plugins->autoload(GALETTE_PLUGINS_PATH);

$session_name = '';
//since PREFIX_DB and NAME_DB are required to properly instanciate sessions,
// we have to check here if they're assigned
if ($installer || !defined('PREFIX_DB') || !defined('NAME_DB')) {
    $session_name = 'install_' . str_replace('.', '_', GALETTE_VERSION);
} else {
    $session_name = PREFIX_DB . '_' . NAME_DB . '_' . str_replace('.', '_', GALETTE_VERSION);
}
$session_name = 'galette_' . $session_name;
$session = new \RKA\SessionMiddleware([
    'name'      => $session_name,
    'lifetime'  => GALETTE_TIMEOUT
]);
$app->add($session);
$session->start();

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

/**
 * Cards navigation middleware
 */
$navMiddleware = function ($request, $response, $next) use ($container) {
    $navigate = array();
    $route = $request->getAttribute('route');
    //$uri = $request->getUri();
    //$route_name = $route->getName();
    $args = $route->getArguments();

    if (isset($this->session->filter_members)) {
        $filters =  $this->session->filter_members;
    } else {
        $filters = new Galette\Filters\MembersList();
    }

    if ($this->login->isAdmin()
        || $this->login->isStaff()
        || $this->login->isGroupManager()
    ) {
        $m = new Galette\Repository\Members($filters);
        $ids = $m->getList(false, array(Galette\Entity\Adherent::PK, 'nom_adh', 'prenom_adh'));
        $ids = $ids->toArray();
        foreach ($ids as $k => $m) {
            if ($m['id_adh'] == $args['id']) {
                $navigate = array(
                    'cur'  => $m['id_adh'],
                    'count' => count($ids),
                    'pos' => $k+1
                );
                if ($k > 0) {
                    $navigate['prev'] = $ids[$k-1]['id_adh'];
                }
                if ($k < count($ids)-1) {
                    $navigate['next'] = $ids[$k+1]['id_adh'];
                }
                break;
            }
        }
    }
    $this->view->getSmarty()->assign('navigate', $navigate);

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
$baseRedirect = function ($request, $response, $args = []) use ($container) {
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
            $urlRedirect = getGaletteBaseUrl($request) . $session->urlRedirect;
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
                        //Do not use "$router->pathFor('dashboard'))" to prevent translation issues when login
                        ->withHeader('Location', getGaletteBaseUrl($request) . '/dashboard');
                } else {
                    return $response
                        ->withStatus(301)
                        //Do not use "$router->pathFor('members'))" to prevent translation issues when login
                        ->withHeader('Location', getGaletteBaseUrl($request) . '/members');
                }
            } else {
                return $response
                    ->withStatus(301)
                    //Do not use "$router->pathFor('me'))" to prevent translation issues when login
                    ->withHeader('Location', getGaletteBaseUrl($request) . '/dashboard');
            }
        }
    } else {
        return $response
            ->withStatus(301)
            //Do not use "$router->pathFor('login'))" to prevent translation issues when login
            ->withHeader('Location', getGaletteBaseUrl($request) . '/login');
    }
};

/**
 * Get base URL fixed for proxies
 * TODO: remove, i'ts been migrated to AbstractController
 *
 * @param Request $request request to work on
 *
 * @return string
 */
function getGaletteBaseUrl(\Slim\Http\Request $request)
{
    $url = preg_replace(
        [
            '|index\.php|',
            '|https?://' . $_SERVER['HTTP_HOST'] . '(:\d+)?' . '|'
        ],
        ['', ''],
        $request->getUri()->getBaseUrl()
    );
    if (strlen($url) && substr($url, -1) !== '/') {
        $url .= '/';
    }
    return $url;
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
 * TODO: lang is now changed directly at I18n construct
 */
$app->add(function ($request, $response, $next) use ($i18n) {
    $get = $request->getQueryParams();

    if (isset($get['ui_pref_lang'])) {
        $route = $request->getAttribute('route');

        $route_name = $route->getName();
        $arguments = $route->getArguments();

        $this->i18n->changeLanguage($get['ui_pref_lang']);
        $this->session->i18n = $this->i18n;

        return $response->withRedirect(
            $this->router->pathFor(
                $route_name,
                $arguments
            ),
            301
        );
    }
    return $next($request, $response);
});

//Telemetry update middleware
$app->add(function ($request, $response, $next) {
    $telemetry = new \Galette\Util\Telemetry(
        $this->zdb,
        $this->preferences,
        $this->plugins
    );
    if ($telemetry->isSent()) {
        try {
            $dformat = 'Y-m-d H:i:s';
            $mdate = \DateTime::createFromFormat(
                $dformat,
                $telemetry->getSentDate()
            );
            $expire = $mdate->add(
                new \DateInterval('P7D')
            );
            $now = new \DateTime();
            $has_expired = $now > $expire;

            if ($has_expired) {
                $cfile = GALETTE_CACHE_DIR . 'telemetry.cache';
                if (file_exists($cfile)) {
                    $mdate = \DateTime::createFromFormat(
                        $dformat,
                        date(
                            $dformat,
                            filemtime($cfile)
                        )
                    );
                    $expire = $mdate->add(
                        new \DateInterval('P7D')
                    );
                    $now = new \DateTime();
                    $has_expired = $now > $expire;
                }

                if ($has_expired) {
                    //create/update cache file
                    $stream = fopen($cfile, 'w+');
                    fwrite(
                        $stream,
                        $telemetry->getSentDate()
                    );
                    fclose($stream);

                    //send telemetry data
                    try {
                        $result = $telemetry->send();
                    } catch (\Exception $e) {
                        Analog::log(
                            $e->getMessage(),
                            Analog::INFO
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            //empty catch
        }
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
            'publicList',
            'filterPublicList'
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
