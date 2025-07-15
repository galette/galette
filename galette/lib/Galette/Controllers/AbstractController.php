<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

/**
 * Galette abstract controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class AbstractController
{
    private ContainerInterface $container;
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
    #[Inject("session")]
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
     *
     * @param ContainerInterface $container Container instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Galette redirection workflow
     * Each user have a default homepage depending on it status (logged in or not, its credentials, etc.
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    protected function galetteRedirect(Request $request, Response $response): Response
    {
        //reinject flash messages so they're not lost
        $flashes = $this->flash->getMessages();
        foreach ($flashes as $type => $messages) {
            foreach ($messages as $message) {
                $this->container->get('flash')->addMessage($type, $message);
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
     * @param Request $request PSR Request
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
     * Get a JSON response
     *
     * @param Response            $response Response instance
     * @param array<string,mixed> $data     Data to send
     * @param int                 $status   HTTP status code
     *
     * @return Response
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
     *
     * @return string
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

        $filter_name = Text::slugify($filter_name);
        return $filter_name;
    }

    /**
     * Redirect with errors
     *
     * @param Response $response     PSR Response
     * @param string[] $errors       Errors to report
     * @param string   $redirect_uri URI to redirect to
     *
     * @return Response
     */
    protected function redirectWithErrors(Response $response, array $errors, string $redirect_uri): Response
    {
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
            ->withHeader('Location', $redirect_uri);
    }
}
