<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette main controller
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
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
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

namespace Galette\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\SysInfos;
use Galette\Core\GaletteMail;
use Analog\Analog;

/**
 * Galette main controller
 *
 * @category  Controllers
 * @name      GaletteController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-02
 */

class GaletteController extends AbstractController
{
    /**
     * Main route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return void
     */
    public function slash(Request $request, Response $response, array $args = [])
    {
        return $this->galetteRedirect($request, $response, $args);
    }

    /**
     * Logo route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param Logo     $logo     Logo instance
     *
     * @return void
     */
    public function logo(Request $request, Response $response, \galette\Core\Logo $logo)
    {
        return $logo->display($response);
    }

    /**
     * Print logo route
     *
     * @param Request   $request  PSR Request
     * @param Response  $response PSR Response
     * @param PrintLogo $logo     Print logo instance
     *
     * @return void
     */
    public function printLogo(Request $request, Response $response, \Galette\Core\PrintLogo $logo)
    {
        return $logo->display($response);
    }

    /**
     * System informations
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param Plugins  $plugins  Plugins instance
     *
     * @return void
     */
    public function sysInfos(Request $request, Response $response, \Galette\Core\Plugins $plugins)
    {
        $sysinfos = new SysInfos();
        $sysinfos->grab();

        // display page
        $this->view->render(
            $response,
            'sysinfos.tpl',
            array(
                'page_title'    => _T("System informations"),
                'rawinfos'      => $sysinfos->getRawData($this->plugins)
            )
        );
        return $response;
    }

    /**
     * Lost password page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return void
     */
    public function lostpasswd(Request $request, Response $response)
    {
        if ($this->preferences->pref_mail_method === GaletteMail::METHOD_DISABLED) {
            throw new \RuntimeException('Mailing disabled.');
        }
        // display page
        $this->view->render(
            $response,
            'lostpasswd.tpl',
            array(
                'page_title'    => _T("Password recovery")
            )
        );
        return $response;
    }
}
