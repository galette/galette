<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Middleware;

use Galette\Core\I18n;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteParser;

/**
 * Galette Slim middleware for maintenance and needs update pages display.
 *
 * Relies on Slim modes. Set 'MAINT' for maintenance mode, and 'NEED_UPDATE' for the need update one.
 * Maintenance mode page will be displayed if current logged in user is not super admin.
 *
 * Renders maintenance and needs update pages, as 503 (service not available)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpdateAndMaintenance
{
    public const MAINTENANCE = 0;
    public const NEED_UPDATE = 1;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var I18n
     */
    protected I18n $i18n;

    /**
     * @var RouteParser
     */
    protected RouteParser $routeParser;

    /**
     * Constructor
     *
     * @param I18n         $i18n        I18n instance
     * @param RouteParser  $routeParser Route parser
     * @param callable|int $callback    Callable or local constant
     */
    public function __construct(I18n $i18n, RouteParser $routeParser, callable|int $callback = self::MAINTENANCE)
    {
        $this->i18n = $i18n;
        $this->routeParser = $routeParser;

        if ($callback === self::MAINTENANCE) {
            $this->callback = array($this, 'maintenancePage');
        } elseif ($callback === self::NEED_UPDATE) {
            $this->callback = array($this, 'needsUpdatePage');
        } else {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('argument callback must be callable');
            } else {
                $this->callback = $callback;
            }
        }
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler PSR7 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = new \Slim\Psr7\Response();
        $response
            ->withStatus(503)
            ->withHeader('Content-type', 'text/html')
            ->getBody()->write(call_user_func($this->callback, $request));
        return $response;
    }

    /**
     * Renders the page
     *
     * @param Request $request  PSR7 request
     * @param string  $contents HTML page contents
     *
     * @return string
     */
    private function renderPage(Request $request, string $contents): string
    {
        $path = $this->routeParser->urlFor('slash');

        //add ending / if missing
        if (
            $path === ''
            || $path !== '/'
            && substr($path, -1) !== '/'
        ) {
            $path .= '/';
        }

        $theme_path = $path . GALETTE_THEME;

        $body = "<!DOCTYPE html>
<html class=\"public_page\" lang=\"" . $this->i18n->getAbbrev() . "\">
    <head>
        <title>" . _T("Galette needs update!") . "</title>
        <meta charset=\"UTF-8\"/>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"" . $theme_path . "ui/semantic.min.css\"/>
        <link rel=\"shortcut icon\" href=\"" . $theme_path . "images/favicon.png\"/>
    </head>
    <body class=\"notup2date pushable\">
        <div class=\"pusher\">
            <div id=\"main\" class=\"ui container\">
                <div class=\"ui basic segment\">
                    <div class=\"ui basic center aligned fitted segment\">
                        <img src=\"" . $theme_path . "images/galette.png\" alt=\"[ Galette ]\"/>
                    </div>
                    <div class=\"ui center aligned message\">" . $contents . "</div>
                </div>
            </div>
        </div>
    </body>
</html>";
        return $body;
    }

    /**
     * Displays maintenance page
     *
     * @param Request $request PSR7 request
     *
     * @return string
     */
    private function maintenancePage(Request $request): string
    {
        $contents = "<div class=\"header\">" . _T("Galette is currently under maintenance!") . "</div>
            <p>" . _T("The Galette instance you are requesting is currently under maintenance. Please come back later.") . "</p>";
        return $this->renderPage($request, $contents);
    }

    /**
     * Displays needs update page
     *
     * @param Request $request PSR7 request
     *
     * @return string
     */
    private function needsUpdatePage(Request $request): string
    {
        $contents = "<h1>" . _T("Galette needs update!") . "</h1>
            <p>" . _T("Your Galette database is not present, or not up to date.") . "</p>
            <p><em>" . _T("Please run install or upgrade procedure (check the documentation)") . "</em></p>";
        return $this->renderPage($request, $contents);
    }
}
