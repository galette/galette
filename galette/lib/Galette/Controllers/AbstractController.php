<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette abstract controller
 *
 * PHP version 5
 *
 * Copyright © 2019-2020 The Galette Team
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
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

namespace Galette\Controllers;

use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Galette abstract controller
 *
 * @category  Controllers
 * @name      AbstractController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

abstract class AbstractController
{
    private $container;
    /**
     * @Inject
     * @var Galette\Core\Db
     */
    protected $zdb;
    /**
     * @Inject
     * @var Galette\Core\Login
     */
    protected $login;
    /**
     * @Inject
     * @var Galette\Core\Preferences
     */
    protected $preferences;
    /**
     * @Inject
     * @var Slim\Views\Smarty
     */
    protected $view;
    /**
     * @Inject
     * @var Galette\Core\Logo
     */
    protected $logo;
    /**
     * @Inject
     * @var Galette\Core\Plugins
     */
    protected $plugins;
    /**
     * @Inject
     * @var Slim\Router
     */
    protected $router;
    /**
     * @Inject
     * @var Galette\Core\History
     */
    protected $history;
    /**
     * @Inject
     * @var Galette\Core\I18n
     */
    protected $i18n;
    /**
     * @Inject("session")
     */
    protected $session;
    /**
     * @Inject
     * @var Slim\Flash\Messages
     */
    protected $flash;
    /**
     * @Inject
     * @var Galette\Entity\FieldsConfig
     */
    protected $fields_config;
    /**
     * @Inject
     * @var Galette\Entity\ListsConfig
     */
    protected $lists_config;
    /**
     * @Inject
     * @var array
     */
    protected $members_fields;
    /**
     * @Inject
     * @var array
     */
    protected $members_form_fields;
    /**
     * @Inject
     * @var array
     */
    protected $members_fields_cats;

    /**
     * @Inject
     * @var Galette\Handlers\NotFound
     */
    protected $notFoundHandler;

    /**
     * Constructor
     *
     * @param ContainerInterface $container Container instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        //set variosu services we need
        $this->zdb = $container->get('zdb');
        $this->login = $container->get('login');
        $this->preferences = $container->get('preferences');
        $this->view = $container->get('view');
        $this->logo = $container->get('logo');
        $this->print_logo = $container->get('print_logo');
        $this->plugins = $container->get('plugins');
        $this->router = $container->get('router');
        $this->history = $container->get('history');
        $this->i18n = $container->get('i18n');
        $this->session = $container->get('session');
        $this->flash = $container->get('flash');
        $this->fields_config = $container->get('fields_config');
        $this->lists_config = $container->get('lists_config');
        $this->notFoundHandler = $container->get('notFoundHandler');
        $this->members_fields = $container->get('members_fields');
        $this->members_form_fields = $container->get('members_form_fields');
        $this->members_fields_cats = $container->get('members_fields_cats');
    }

    /**
     * Galette redirection workflow
     * Each user have a default homepage depending on it status (logged in or not, its credentials, etc.
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments ['r']
     *
     * @return void
     */
    protected function galetteRedirect(Request $request, Response $response, array $args = [])
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
                $urlRedirect = $this->getGaletteBaseUrl($request) . $this->session->urlRedirect;
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
                            ->withHeader('Location', $this->router->pathFor('dashboard'));
                    } else {
                        return $response
                            ->withStatus(301)
                            ->withHeader('Location', $this->router->pathFor('members'));
                    }
                } else {
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->router->pathFor('dashboard'));
                }
            }
        } else {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('login'));
        }
    }

    /**
     * Get base URL fixed for proxies
     *
     * @param Request $request PSR Request
     *
     * @return string
     */
    private function getGaletteBaseUrl(Request $request)
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
}
