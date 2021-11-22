<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Slim middleware to navigate beetween members
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */

namespace Galette\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Galette\Entity\Adherent;
use Galette\Filters\MembersList;
use Galette\Repository\Members;
use DI\Container;

/**
 * Galette Slim middleware to navigate beetween members
 *
 * @category  Middleware
 * @name      MembersNavigate
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */
class MembersNavigate
{
    /**
     * @var Galette\Core\Login
     */
    private $login;

    private $session;

    private $view;

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        $this->login = $container->get('login');
        $this->session = $container->get('session');
        $this->view = $container->get('view');
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
        $navigate = array();
        $route = $request->getAttribute('route');
        $args = $route->getArguments();

        if (!isset($args['id'])) {
            //not viewing an exiting member
            return $next($request, $response);
        }

        if (isset($this->session->filter_members)) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        if (
            $this->login->isAdmin()
            || $this->login->isStaff()
            || $this->login->isGroupManager()
        ) {
            $m = new Members($filters);

            $ids = array();
            $fields = [Adherent::PK, 'nom_adh', 'prenom_adh'];
            if ($this->login->isAdmin() || $this->login->isStaff()) {
                $ids = $m->getMembersList(false, $fields);
            } else {
                $ids = $m->getManagedMembersList(false, $fields);
            }

            $ids = $ids->toArray();
            foreach ($ids as $k => $m) {
                if ($m['id_adh'] == $args['id']) {
                    $navigate = array(
                        'cur'  => $m['id_adh'],
                        'count' => count($ids),
                        'pos' => $k + 1
                    );
                    if ($k > 0) {
                        $navigate['prev'] = $ids[$k - 1]['id_adh'];
                    }
                    if ($k < count($ids) - 1) {
                        $navigate['next'] = $ids[$k + 1]['id_adh'];
                    }
                    break;
                }
            }
        }
        $this->view->getSmarty()->assign('navigate', $navigate);

        return $next($request, $response);
    }
}
