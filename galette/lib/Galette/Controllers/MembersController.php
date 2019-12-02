<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette members controller
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
use Analog\Analog;
use Galette\Entity\Adherent;

/**
 * Galette members controller
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

class MembersController extends AbstractController
{
    /**
     * Photos route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments ['id']
     *
     * @return void
     */
    public function photo(Request $request, Response $response, array $args)
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

        $adh = new Adherent($this->zdb, $id, $deps);

        $is_manager = false;
        if (!$this->login->isAdmin()
            && !$this->login->isStaff()
            && $this->login->isGroupManager()
        ) {
            $groups = $adh->groups;
            foreach ($groups as $group) {
                if ($this->login->isGroupManager($group->getId())) {
                    $is_manager = true;
                    break;
                }
            }
        }

        $picture = null;
        if ($this->login->isAdmin()
            || $this->login->isStaff()
            || $this->preferences->showPublicPages($this->login)
            && $adh->appearsInMembersList()
            || $this->login->login == $adh->login
            || $is_manager
        ) {
            $picture = $adh->picture;
        } else {
            $picture = new Picture();
        }
        $picture->display();
    }
}
