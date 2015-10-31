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
 * Copyright © 2015 The Galette Team
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
 * @copyright 2015 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9dev - 2015-10-31
 */

namespace Galette\Core;

use Galette\Core\I18n;
use Galette\Core\Login;

/**
 * Galette's Slim middleware
 *
 * Renders maintainance and needs update pages, as 503 (service not available)
 */
class Middleware extends \Slim\Middleware
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
     * @var Login
     */
    protected $login;

    /**
     * Constructor
     *
     * @param I18n         $i18n     I18n instance
     * @param Login        $login    Login instance
     * @param callable|int $callback Callable or local constant
     */
    public function __construct(I18n $i18n, $login = null, $callback = self::MAINTENANCE)
    {
        $this->i18n = $i18n;
        $this->login = $login;

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
     * Call
     *
     * @return void
     */
    public function call()
    {
        $mode = $this->app->getMode();
        if ('MAINT' === $mode && !$this->login->isSuperAdmin()
            || 'NEED_UPDATE' === $mode
        ) {
            call_user_func($this->callback);
        } else {
            $this->next->call();
        }
    }

    /**
     * Renders the page
     *
     * @param string $contents HTML page contents
     *
     * @return void
     */
    private function renderPage($contents)
    {
        $view = $this->app->view();

        $path = str_replace(
            'index.php',
            '',
            $this->app->request()->getRootUri()
        );

        //add ending / if missing
        if ($path === ''
            || $path !== '/'
            && substr($path, -1) !== '/'
        ) {
            $path .= '/';
        }

        $path .= $view->get('galette_base_path');
        $css_path = $path . GALETTE_THEME;

        $body = "<!DOCTYPE html>
<html lang=\"" . $this->i18n->getAbbrev() . "\">
    <head>
        <title>" . _T("Galette is currently under maintenance!") . "</title>
        <meta charset=\"UTF-8\"/>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"" . $css_path . "galette.css\"/>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"" . $css_path . "jquery-ui/jquery-ui-" . JQUERY_UI_VERSION . ".custom.css\"/>
        <script type=\"text/javascript\" src=\"" . $path . "js/jquery/jquery-" . JQUERY_VERSION . ".min.js\"></script>
        <script type=\"text/javascript\" src=\"" . $path . "js/jquery/jquery-migrate-" . JQUERY_MIGRATE_VERSION . ".min.js\"></script>
        <script type=\"text/javascript\" src=\"" . $path . "js/jquery/jquery-ui-" . JQUERY_UI_VERSION . "/jquery.ui.widget.min.js\"></script>
        <script type=\"text/javascript\" src=\"" . $path . "js/jquery/jquery-ui-" . JQUERY_UI_VERSION . "/jquery.ui.button.min.js\"></script>
        <script type=\"text/javascript\" src=\"" . $path . "js/jquery/jquery-ui-" . JQUERY_UI_VERSION . "/jquery.ui.position.min.js\"></script>
        <script type=\"text/javascript\" src=\"" . $path . "js/jquery/jquery-ui-" . JQUERY_UI_VERSION . "/jquery.ui.tooltip.min.js\"></script>
        <script type=\"text/javascript\" src=\"" . $path . "js/jquery/jquery.bgFade.js\"></script>
        <script type=\"text/javascript\" src=\"" . $path . "js/common.js\"></script>
        <!--[if lte IE 9]>
            <script type=\"text/javascript\" src=\"" . $path . "js/html5-ie.js\"></script>
        <!endif]-->
    </head>
    <body class=\"notup2date\">
        <p class=\"center\">
            <img src=\"" . $css_path . "images/galette.png\" alt=\"\"/>
        </p>
        <div id=\"errorbox\">" . $contents . "</div>
    </body>
</html>";
        $this->app->contentType('text/html');
        $this->app->response()->status(503);
        $this->app->response()->body($body);

    }

    /**
     * Displays maintenance page
     *
     * @return void
     */
    private function maintenancePage()
    {
        $contents = "<h1>" . _T("Galette is currently under maintenance!") . "</h1>
            <p>" . _T("The Galette instance you are requesting is currently under maintenance. Please come back later.") . "</p>";
        $this->renderPage($contents);
    }

    /**
     * Displays needs update page
     *
     * @return void
     */
    private function needsUpdatePage()
    {
        $contents = "<h1>" . _T("Galette needs update!") . "</h1>
            <p>" . _T("Your Galette database is not present, or not up to date.") . "</p>
            <p><em>" . _T("Please run install or upgrade procedure (check the documentation)") . "</em></p>";
        $this->renderPage($contents);
    }
}
