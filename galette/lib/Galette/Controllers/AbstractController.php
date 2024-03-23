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
     * @var Db
     */
    #[Inject]
    protected Db $zdb;
    /**
     * @var Login
     */
    #[Inject]
    protected Login $login;
    /**
     * @var Preferences
     */
    #[Inject]
    protected Preferences $preferences;
    /**
     * @var Twig
     */
    #[Inject]
    protected Twig $view;
    /**
     * @var Logo
     */
    #[Inject]
    protected Logo $logo;
    /**
     * @var PrintLogo
     */
    #[Inject]
    protected PrintLogo $print_logo;
    /**
     * @var Plugins
     */
    #[Inject]
    protected Plugins $plugins;
    /**
     * @var RouteParser
     */
    #[Inject]
    protected RouteParser $routeparser;
    /**
     * @var History
     */
    #[Inject]
    protected History $history;
    /**
     * @var I18n
     */
    #[Inject]
    protected I18n $i18n;
    /**
     * @var L10n
     */
    #[Inject]
    protected L10n $l10n;
    /**
     * @var Session
     */
    #[Inject("session")]
    protected Session $session;
    /**
     * @var Messages
     */
    #[Inject]
    protected Messages $flash;
    /**
     * @var FieldsConfig
     */
    #[Inject]
    protected FieldsConfig $fields_config;
    /**
     * @var ListsConfig
     */
    #[Inject]
    protected ListsConfig $lists_config;
    /**
     * @var array
     */
    #[Inject("members_fields")]
    protected array $members_fields;
    /**
     * @var array
     */
    #[Inject("members_form_fields")]
    protected array $members_form_fields;
    /**
     * @var array
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
