<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette abstract controller
 *
 * PHP version 5
 *
 * Copyright © 2019-2023 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

namespace Galette\Controllers;

use Psr\Container\ContainerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;

/**
 * Galette abstract controller
 *
 * @category  Controllers
 * @name      AbstractController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

abstract class AbstractController
{
    private $container;
    /**
     * @var \Galette\Core\Db
     */
    #[Inject]
    protected $zdb;
    /**
     * @var \Galette\Core\Login
     */
    #[Inject]
    protected $login;
    /**
     * @var \Galette\Core\Preferences
     */
    #[Inject]
    protected $preferences;
    /**
     * @var \Slim\Views\Twig
     */
    protected $view;
    /**
     * @var \Galette\Core\Logo
     */
    #[Inject]
    protected $logo;
    /**
     * @var \Galette\Core\PrintLogo
     */
    #[Inject]
    protected $print_logo;
    /**
     * @var \Galette\Core\Plugins
     */
    #[Inject]
    protected $plugins;
    /**
     * @var \Slim\Routing\RouteParser
     */
    #[Inject]
    protected $routeparser;
    /**
     * @var \Galette\Core\History
     */
    #[Inject]
    protected $history;
    /**
     * @var \Galette\Core\I18n
     */
    #[Inject]
    protected $i18n;
    /**
     * @var \Galette\Core\L10n
     */
    #[Inject]
    protected $l10n;
    /**
     * Session
     */
    #[Inject("session")]
    protected $session;
    /**
     * @var \Slim\Flash\Messages
     */
    #[Inject]
    protected $flash;
    /**
     * @var \Galette\Entity\FieldsConfig
     */
    #[Inject]
    protected $fields_config;
    /**
     * @var \Galette\Entity\ListsConfig
     */
    #[Inject]
    protected $lists_config;
    /**
     * @var array
     */
    #[Inject("members_fields")]
    protected $members_fields;
    /**
     * @var array
     */
    #[Inject("members_form_fields")]
    protected $members_form_fields;
    /**
     * @var array
     */
    #[Inject("members_fields_cats")]
    protected $members_fields_cats;

    /**
     * Constructor
     *
     * @param ContainerInterface $container Container instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        //set various services we need
        $this->zdb = $container->get('zdb');
        $this->login = $container->get('login');
        $this->preferences = $container->get('preferences');
        $this->view = $container->get(\Slim\Views\Twig::class);
        $this->logo = $container->get('logo');
        $this->print_logo = $container->get('print_logo');
        $this->routeparser = $container->get(RouteParser::class);
        $this->history = $container->get('history');
        $this->i18n = $container->get('i18n');
        $this->l10n = $container->get('l10n');
        $this->session = $container->get('session');
        $this->flash = $container->get('flash');
        $this->fields_config = $container->get('fields_config');
        $this->lists_config = $container->get('lists_config');
        $this->members_fields = $container->get('members_fields');
        $this->members_form_fields = $container->get('members_form_fields');
        $this->members_fields_cats = $container->get('members_fields_cats');
        $this->plugins = $container->get('plugins');
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
    protected function galetteRedirect(Request $request, Response $response)
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
            } else {
                if (
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
     * @return array
     */
    protected function getArgs(Request $request): array
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $args = $route->getArguments();
        return $args;
    }

    /**
     * Get a JSON response
     *
     * @param Response $response Response instance
     * @param array    $data     Data to send
     * @param int      $status   HTTP status code
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
}
