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

use Galette\Api\Actions\Auth\LoginAction;
use Galette\Api\Actions\Auth\RefreshAction;
use Galette\Api\Actions\Auth\TokenAction;
use Galette\Api\Actions\Contribution\CreateContributionAction;
use Galette\Api\Actions\Contribution\GetContributionAction;
use Galette\Api\Actions\Contribution\ListContributionsAction;
use Galette\Api\Actions\Group\GetGroupAction;
use Galette\Api\Actions\Group\ListGroupsAction;
use Galette\Api\Actions\Member\CreateMemberAction;
use Galette\Api\Actions\Member\DeleteMemberAction;
use Galette\Api\Actions\Member\GetMemberAction;
use Galette\Api\Actions\Member\ListMembersAction;
use Galette\Api\Actions\Member\UpdateMemberAction;
use Galette\Api\ApiResponseFormatter;
use Galette\Api\Middleware\JwtMiddleware;
use Galette\Core\Db;
use Galette\Core\I18n;
use Galette\Core\Login;
use Galette\Core\SlimApp;
use Psr\Container\ContainerInterface;

use function Safe\define;
use function Safe\parse_url;

if (!defined('GALETTE_ROOT')) {
    define('GALETTE_ROOT', __DIR__ . '/../../'); //@phpstan-ignore theCodingMachineSafe.function
}

if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', '../../'); //@phpstan-ignore theCodingMachineSafe.function
}

require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';

if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<')) { //@phpstan-ignore if.alwaysFalse
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PHP version too old']);
    die(1);
}

require_once GALETTE_ROOT . '/vendor/autoload.php';

// Bootstrap Galette: loads config, DB ($zdb), preferences ($preferences), plugins ($plugins)
/** @var \Galette\Core\Plugins $plugins */
require_once GALETTE_ROOT . 'includes/galette.inc.php';

/** @var \Galette\Core\Plugins $plugins */
$gapp = new SlimApp(plugins: $plugins);
/** @var \DI\Container $container */
$container = $gapp->getApp()->getContainer();
$app = $gapp->getApp();

// Override the session-backed Login with a stateless one for the API.
// The JWT middleware will call Login::loadById() to hydrate it per-request.
$container->set(Login::class, function (ContainerInterface $c) {
    return new Login(
        $c->get(Db::class),
        $c->get(I18n::class)
    );
});

// Set base path
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

// No session or CSRF for the API — it is stateless (JWT-based).

// JWT middleware instance (applied to protected routes)
$jwtMiddleware = $container->get(JwtMiddleware::class);

// API routes
$app->group('/api/v1', function ($group) use ($jwtMiddleware): void {
    // Auth endpoints — no JWT required
    $group->post('/auth/login', LoginAction::class);
    $group->post('/auth/token', TokenAction::class);
    $group->post('/auth/refresh', RefreshAction::class);

    // Protected endpoints — JWT required
    $group->group('', function ($protected): void {
        // Members
        $protected->get('/members', ListMembersAction::class);
        $protected->post('/members', CreateMemberAction::class);
        $protected->get('/members/{id:\d+}', GetMemberAction::class);
        $protected->put('/members/{id:\d+}', UpdateMemberAction::class);
        $protected->delete('/members/{id:\d+}', DeleteMemberAction::class);

        // Contributions
        $protected->get('/contributions', ListContributionsAction::class);
        $protected->get('/contributions/{id:\d+}', GetContributionAction::class);
        $protected->post('/contributions', CreateContributionAction::class);

        // Groups
        $protected->get('/groups', ListGroupsAction::class);
        $protected->get('/groups/{id:\d+}', GetGroupAction::class);
    })->add($jwtMiddleware);
});

// Error handler — return JSON for all errors
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(new ApiResponseFormatter());

$app->run();
