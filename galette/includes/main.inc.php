<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

use Galette\Core\I18n;
use Galette\Core\LightSlimApp;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\SlimApp;
use Galette\Middleware\Authenticate;
use Galette\Middleware\Language;
use Galette\Middleware\Telemetry;
use Galette\Middleware\UpdateAndMaintenance;
use RKA\SessionMiddleware;
use Slim\Routing\RouteContext;
use Galette\Core\Galette;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Routing\RouteParser;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use function Safe\define;
use function Safe\parse_url;

if (!defined('GLOB_BRACE')) {
    \define('GLOB_BRACE', 0); //@phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
}

//define galette's root directory
if (!defined('GALETTE_ROOT')) {
    \define('GALETTE_ROOT', __DIR__ . '/../'); //@phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
}

// define relative base path templating can use
if (!defined('GALETTE_BASE_PATH')) {
    \define('GALETTE_BASE_PATH', '../'); //@phpstan-ignore theCodingMachineSafe.function (dependencies not loaded yet)
}
/** @var bool $needs_update */
$needs_update = false;
/** @ignore */
require_once GALETTE_ROOT . 'includes/galette.inc.php';

/** @var Plugins $plugins */

//CONFIGURE AND START SESSION

//Session duration
if (!defined('GALETTE_TIMEOUT')) {
    //See https://php.net/manual/en/session.configuration.php#ini.session.cookie-lifetime
    define('GALETTE_TIMEOUT', 0);
}

$session_name = '';
//since PREFIX_DB and NAME_DB are required to properly instantiate sessions,
// we have to check here if they're assigned
/** @var bool $installer */
if ($installer || !defined('PREFIX_DB') || !defined('NAME_DB')) {
    $session_name = 'install_' . str_replace('.', '_', GALETTE_VERSION);
} else {
    $session_name = PREFIX_DB . '_' . NAME_DB . '_' . str_replace('.', '_', GALETTE_VERSION);
}
$session_name = 'galette_' . $session_name;
$session = new SessionMiddleware([
    'name'      => $session_name,
    'lifetime'  => GALETTE_TIMEOUT
]);

$session->start();
//Galette needs database update!
if ($needs_update) { //@phpstan-ignore if.alwaysFalse (variable defined in galette.inc.php)
    define('GALETTE_THEME', 'themes/default/');
    $gapp = new LightSlimApp(plugins: $plugins);
} else {
    $gapp = new SlimApp(plugins: $plugins);
}
/** @var \DI\Container $container */
$container = $gapp->getApp()->getContainer();
$app = $gapp->getApp();

// Globals... :( - see also galette/includes/dependencies.php
global $zdb, $preferences, $login, $hist, $l10n, $emitter, $routeparser, $i18n, $translator;

$app->setBasePath((function () {
    $uri = (string)parse_url('http://a' . ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (stripos($uri, (string)$_SERVER['SCRIPT_NAME']) === 0) {
        return dirname((string)$_SERVER['SCRIPT_NAME']);
    }

    $scriptDir = str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME']));
    if ($scriptDir !== '/' && stripos($uri, $scriptDir) === 0) {
        return $scriptDir;
    }

    return '';
})());


$app->add($session);

$app->add($app->getContainer()->get(\Slim\Csrf\Guard::class));

/** @var \DI\Container $container */
/**
 * Authentication middleware
 * FIXME: use DI when needed instead of global variable
 */
$authenticate = $container->get(Authenticate::class); //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- not used here, but in route files

require_once GALETTE_ROOT . 'includes/routes/main.routes.php';

if ($needs_update) { //@phpstan-ignore if.alwaysFalse (variable defined in galette.inc.php)
    $app->add(
        new UpdateAndMaintenance(
            $container->get(I18n::class),
            $container->get(RouteParser::class),
            UpdateAndMaintenance::NEED_UPDATE
        )
    );

    $app->run();
    die();
}

//Maintenance middleware
if (Galette::isUnderMaintenance() && !$container->get(Login::class)->isSuperAdmin()) {
    $app->add(
        new UpdateAndMaintenance(
            $container->get(I18n::class),
            $container->get(RouteParser::class),
            UpdateAndMaintenance::MAINTENANCE
        )
    );
}

/**
 * Change language middleware
 *
 * Require determineRouteBeforeAppMiddleware to be on.
 */
$app->add(Language::class);

//Telemetry update middleware
$app->add(Telemetry::class);

require_once GALETTE_ROOT . 'includes/routes/authentication.routes.php';
require_once GALETTE_ROOT . 'includes/routes/management.routes.php';
require_once GALETTE_ROOT . 'includes/routes/members.routes.php';
require_once GALETTE_ROOT . 'includes/routes/groups.routes.php';
require_once GALETTE_ROOT . 'includes/routes/contributions.routes.php';
require_once GALETTE_ROOT . 'includes/routes/public_pages.routes.php';
require_once GALETTE_ROOT . 'includes/routes/ajax.routes.php';
require_once GALETTE_ROOT . 'includes/routes/plugins.routes.php';

// Via this middleware you could access the route and routing results from the resolved route
$app->add(function (Request $request, RequestHandler $handler) use ($container) {
    $routeContext = RouteContext::fromRequest($request);
    $route = $routeContext->getRoute();

    // return NotFound for non-existent route
    if (empty($route)) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $name = $route->getName();
    $arguments = $route->getArguments();

    $view = $container->get(Twig::class);
    $view->getEnvironment()->addGlobal('cur_route', $name);
    $view->getEnvironment()->addGlobal('cur_route_args', $arguments);
    $view->getEnvironment()->addGlobal('cur_subroute', array_shift($arguments));

    return $handler->handle($request);
});

// Add Routing Middleware - required for ACLs to work
$app->addRoutingMiddleware();

/**
 * Add Error Handling Middleware
 *
 * @var bool $displayErrorDetails -> Should be set to false in production
 * @var bool $logErrors -> Parameter is passed to the default ErrorHandler
 * @var bool $logErrorDetails -> Display error details in error log
 * @var \Analog\Logger $logger -> Logger instance
 * which can be replaced by a callable of your choice.
 *
 * Note: This middleware should be added last. It will not handle any exceptions/errors
 * for middleware added after it.
 */
$errorMiddleware = $app->addErrorMiddleware(
    Galette::isDebugEnabled(),
    true,
    true,
    $logger
);

/** @var \Slim\Handlers\ErrorHandler $errorHandler */
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->registerErrorRenderer('text/html', \Galette\Renderers\Html::class);

/**
 * Twig-View Middleware
 * At the end, so it can be used to render errors
 */
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));

if (!defined('GALETTE_TESTS')) {
    $app->run();
}

if (isset($profiler)) {
    $profiler->stop();
}
