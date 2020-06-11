<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Slim middleware for maintenance and needs update pages display.
 *
 * Relies on Slim modes. Set 'MAINT' for maintenance mode, and 'NEED_UPDATE' for the need update one.
 * Maintenance mode page will be displayed if current logged in user is not super admin.
 *
 * PHP version 5
 *
 * Copyright © 2015-2020 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2015-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2015-10-31
 */

namespace Galette\Middleware;

use Galette\Core\I18n;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Galette's Slim middleware for Update and Maintenance
 *
 * Renders maintainance and needs update pages, as 503 (service not available)
 *
 * @category  Middleware
 * @name      UpdateAndMaintenance
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2015-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2015-10-31
 */
class UpdateAndMaintenance
{
    const MAINTENANCE = 0;
    const NEED_UPDATE = 1;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var I18n
     */
    protected $i18n;

    /**
     * Constructor
     *
     * @param I18n         $i18n     I18n instance
     * @param callable|int $callback Callable or local constant
     */
    public function __construct(I18n $i18n, $callback = self::MAINTENANCE)
    {
        $this->i18n = $i18n;

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
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, $next): Response
    {
        $response
            ->withStatus(503)
            ->withHeader('Content-type', 'text/html')
            ->getBody()->write(call_user_func($this->callback, $request));
        return $response;
    }

    /**
     * Renders the page
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param string                                   $contents HTML page contents
     *
     * @return string
     */
    private function renderPage(Request $request, $contents)
    {
        $path = str_replace(
            'index.php',
            '',
            $request->getUri()->getBasePath()
        );

        //add ending / if missing
        if (
            $path === ''
            || $path !== '/'
            && substr($path, -1) !== '/'
        ) {
            $path .= '/';
        }

        $css_path = $path . GALETTE_THEME;

        $body = "<!DOCTYPE html>
<html lang=\"" . $this->i18n->getAbbrev() . "\">
    <head>
        <title>" . _T("Galette needs update!") . "</title>
        <meta charset=\"UTF-8\"/>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"" . $css_path . "galette.css\"/>
    </head>
    <body class=\"notup2date\">
        <p class=\"center\">
            <img src=\"" . $css_path . "images/galette.png\" alt=\"\"/>
        </p>
        <div id=\"errorbox\">" . $contents . "</div>
    </body>
</html>";
        return $body;
    }

    /**
     * Displays maintenance page
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR7 request
     *
     * @return string
     */
    private function maintenancePage(Request $request)
    {
        $contents = "<h1>" . _T("Galette is currently under maintenance!") . "</h1>
            <p>" . _T("The Galette instance you are requesting is currently under maintenance. Please come back later.") . "</p>";
        return $this->renderPage($request, $contents);
    }

    /**
     * Displays needs update page
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR7 request
     *
     * @return string
     */
    private function needsUpdatePage(Request $request)
    {
        $contents = "<h1>" . _T("Galette needs update!") . "</h1>
            <p>" . _T("Your Galette database is not present, or not up to date.") . "</p>
            <p><em>" . _T("Please run install or upgrade procedure (check the documentation)") . "</em></p>";
        return $this->renderPage($request, $contents);
    }
}
