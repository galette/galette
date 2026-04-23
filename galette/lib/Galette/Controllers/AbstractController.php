<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\I18n;
use Galette\Core\L10n;
use Galette\Core\Login;
use Galette\Core\Logo;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Core\PrintLogo;
use Galette\Entity\FieldsConfig;
use Galette\Entity\ListsConfig;
use Galette\Util\Text;
use Psr\Container\ContainerInterface;
use RKA\Session;
use Slim\Flash\Messages;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;
use DI\Attribute\Inject;
use Slim\Views\Twig;

use function Safe\json_encode;

/**
 * Galette abstract controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class AbstractController
{
    #[Inject]
    protected Db $zdb;
    #[Inject]
    protected Login $login;
    #[Inject]
    protected Preferences $preferences;
    #[Inject]
    protected Twig $view;
    #[Inject]
    protected Logo $logo;
    #[Inject]
    protected PrintLogo $print_logo;
    #[Inject]
    protected Plugins $plugins;
    #[Inject]
    protected RouteParser $routeparser;
    #[Inject]
    protected History $history;
    #[Inject]
    protected I18n $i18n;
    #[Inject]
    protected L10n $l10n;
    #[Inject]
    protected Session $session;
    #[Inject]
    protected Messages $flash;
    #[Inject]
    protected FieldsConfig $fields_config;
    #[Inject]
    protected ListsConfig $lists_config;
    /**
     * @var array<string,mixed>
     */
    #[Inject("members_fields")]
    protected array $members_fields;
    /**
     * @var array<string,mixed>
     */
    #[Inject("members_form_fields")]
    protected array $members_form_fields;
    /**
     * @var array<string,mixed>
     */
    #[Inject("members_fields_cats")]
    protected array $members_fields_cats;

    /**
     * Constructor
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Galette redirection workflow
     * Each user have a default homepage depending on it status (logged in or not, its credentials, etc.
     */
    protected function galetteRedirect(Request $request, Response $response): Response
    {
        //reinject flash messages so they're not lost
        $flashes = $this->flash->getMessages();
        foreach ($flashes as $type => $messages) {
            foreach ($messages as $message) {
                $this->container->get(Messages::class)->addMessage($type, $message);
            }
        }

        if ($this->login->isLogged()) {
            $urlRedirect = null;
            if ($this->session->urlRedirect !== null) {
                $urlRedirect = $this->session->urlRedirect;
                $this->session->urlRedirect = null;
            }

            if ($urlRedirect !== null) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $urlRedirect);
            } elseif (
                $this->login->isSuperAdmin()
                || $this->login->isAdmin()
                || $this->login->isStaff()
            ) {
                if (
                    !isset($_COOKIE['show_galette_dashboard'])
                    || $_COOKIE['show_galette_dashboard'] == 1
                ) {
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->routeparser->urlFor('dashboard'));
                } else {
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->routeparser->urlFor('members'));
                }
            } else {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('dashboard'));
            }
        } else {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('login'));
        }
    }

    /**
     * Get route arguments
     * php-di bridge pass each variable, not an array of all arguments
     *
     * @return array<string,mixed>
     */
    protected function getArgs(Request $request): array
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route->getArguments();
    }

    /**
     * Check if request has been made via AJAX (XMLHttpRequest)
     */
    protected function isAjax(Request $request): bool
    {
        return $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Get a JSON response
     *
     * @param Response            $response Response instance
     * @param array<string,mixed> $data     Data to send
     * @param int                 $status   HTTP status code
     */
    protected function withJson(Response $response, array $data, int $status = 200): Response
    {
        $response = $response->withStatus($status);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data));
        return $response;
    }

    /**
     * Get filter name in session
     *
     * @param string                   $filter_name Filter name
     * @param array<string,mixed>|null $args        Arguments
     */
    public function getFilterName(string $filter_name, ?array $args = null): string
    {
        if (empty($filter_name)) {
            throw new \OutOfBoundsException(
                'Filter name cannot be empty!'
            );
        }

        if (isset($args['prefix'])) {
            $filter_name = $args['prefix'] . '_' . $filter_name;
        }

        if (isset($args['suffix'])) {
            $filter_name .= '_' . $args['suffix'];
        }

        $filter_name .= '_filter';

        return Text::slugify($filter_name);
    }

    /**
     * Redirect with errors
     *
     * @param Response $response     PSR Response
     * @param string[] $errors       Errors to report
     * @param string   $redirect_url URL to redirect to
     */
    protected function redirectWithErrors(Response $response, array $errors, string $redirect_url): Response
    {
        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            errors: $errors
        );
    }

    /**
     * Redirect with errors
     *
     * @param Response $response     PSR Response
     * @param string   $redirect_url URL to redirect to
     * @param string[] $successes    Successes to report
     * @param string[] $warnings     Warnings to report
     * @param string[] $errors       Errors to report
     */
    protected function redirect(
        Response $response,
        string $redirect_url,
        array $successes = [],
        array $warnings = [],
        array $errors = []
    ): Response {
        //report successes
        foreach ($successes as $success) {
            $this->flash->addMessage(
                'success_detected',
                $success
            );
        }

        //report warnings
        foreach ($warnings as $warning) {
            $this->flash->addMessage(
                'warning_detected',
                $warning
            );
        }

        //report errors
        foreach ($errors as $error) {
            $this->flash->addMessage(
                'error_detected',
                $error
            );
        }

        //redirect to calling action
        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }

    /**
     * Get current route information from request
     *
     * @param Request $request PSR7 request
     */
    protected function getRoute(Request $request): ?\Slim\Interfaces\RouteInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        return $routeContext->getRoute();
    }

    /**
     * Get current route name
     *
     * @param Request $request PSR7 request
     */
    protected function getRouteName(Request $request): ?string
    {
        return $this->getRoute($request)?->getName();
    }

    /**
     * Get current route arguments
     *
     * @param Request $request PSR7 request
     *
     * @return array<string, string>
     */
    protected function getRouteArguments(Request $request): array
    {
        return $this->getRoute($request)?->getArguments() ?? [];
    }

    /**
     * Get current route pattern
     *
     * @param Request $request PSR7 request
     */
    protected function getRoutePattern(Request $request): ?string
    {
        return $this->getRoute($request)?->getPattern();
    }

    /**
     * Get current route allowed methods
     *
     * @param Request $request PSR7 request
     *
     * @return string[]
     */
    protected function getRouteMethods(Request $request): array
    {
        return $this->getRoute($request)?->getMethods() ?? [];
    }

    /**
     * Check if current route has a specific name
     *
     * @param Request $request PSR7 request
     * @param string  $name    Route name to check
     */
    protected function isRoute(Request $request, string $name): bool
    {
        return $this->getRouteName($request) === $name;
    }

    /**
     * Generate URL for current route with different parameters
     *
     * @param Request              $request     PSR7 request
     * @param array<string,string> $data        Route parameters
     * @param array<string,mixed>  $queryParams Query string parameters
     */
    protected function urlForCurrentRoute(Request $request, array $data = [], array $queryParams = []): string
    {
        $routeName = $this->getRouteName($request);
        if (!$routeName) {
            throw new \RuntimeException('Cannot generate URL: current route has no name');
        }
        return $this->routeparser->urlFor($routeName, $data, $queryParams);
    }

    /**
     * Get all Route attributes for current method
     * Useful for debugging or logging
     *
     * @return \Galette\Controllers\Attributes\Route[]
     */
    protected function getRouteAttributes(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callerMethod = $backtrace[1]['function'] ?? null;

        if (!$callerMethod) {
            return [];
        }

        try {
            $reflection = new \ReflectionMethod(static::class, $callerMethod);
            $attributes = $reflection->getAttributes(\Galette\Controllers\Attributes\Route::class);

            return array_map(
                fn($attr) => $attr->newInstance(),
                $attributes
            );
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Get route attribute matching the current request
     *
     * @param Request $request Current request
     */
    protected function getCurrentRouteAttribute(Request $request): ?\Galette\Controllers\Attributes\Route
    {
        $currentRouteName = $this->getRouteName($request);

        foreach ($this->getRouteAttributes() as $routeAttr) {
            if ($routeAttr->name === $currentRouteName) {
                return $routeAttr;
            }
        }

        return null;
    }
}
