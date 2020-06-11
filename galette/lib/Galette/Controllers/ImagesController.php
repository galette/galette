<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette images controller
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

namespace Galette\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\Picture;
use Galette\Entity\Adherent;
use Analog\Analog;

/**
 * Galette images controller
 *
 * @category  Controllers
 * @name      ImageController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

class ImagesController extends AbstractController
{

    /**
     * Send response
     *
     * @param Response $response PSR Response
     * @param Picture  $picture  Picture to output
     *
     * @return Response
     */
    protected function sendResponse(Response $response, Picture $picture): Response
    {
        $response = $response->withHeader('Content-Type', $picture->getMime())
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, file_get_contents($picture->getPath()));
        rewind($stream);

        return $response->withBody(new \Slim\Http\Stream($stream));
    }

    /**
     * Logo route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function logo(Request $request, Response $response): Response
    {
        return $this->sendResponse($response, $this->logo);
    }

    /**
     * Print logo route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function printLogo(Request $request, Response $response): Response
    {
        return $this->sendResponse($response, $this->print_logo);
    }

    /**
     * Photos
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments ['id']
     *
     * @return Response
     */
    public function photo(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        $deps = array(
            'groups'    => false,
            'dues'      => false
        );

        //if loggedin user is a group manager, we have to check
        //he manages a group requested member belongs to.
        if ($this->login->isGroupManager()) {
            $deps['groups'] = true;
        }

        $adh = new Adherent($this->zdb, (int)$id, $deps);

        $picture = null;
        if (
            $adh->canEdit($this->login)
            || $this->preferences->showPublicPages($this->login)
            && $adh->appearsInMembersList()
        ) {
            $picture = $adh->picture;
        } else {
            $picture = new Picture();
        }

        return $this->sendResponse($response, $picture);
    }
}
