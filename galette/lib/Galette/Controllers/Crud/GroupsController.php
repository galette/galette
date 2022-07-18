<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette groups controller
 *
 * PHP version 5
 *
 * Copyright © 2020-2022 The Galette Team
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
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */

namespace Galette\Controllers\Crud;

use Throwable;
use Galette\Controllers\CrudController;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Analog\Analog;

/**
 * Galette groups controller
 *
 * @category  Controllers
 * @name      GroupsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-06
 */

class GroupsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function add(Request $request, Response $response): Response
    {
        //no new page (included on list), just to satisfy inheritance
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $name     Group name
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, string $name = null): Response
    {
        $group = new Group();
        $group->setLogin($this->login);
        $group->setName($name);
        $group->store();
        if (!$this->login->isSuperAdmin()) {
            $group->setManagers(new Adherent($this->zdb, $this->login->id));
        }
        $id = $group->getId();

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('groups', ['id' => $id]));
    }


    /**
     * Check uniqueness
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function checkUniqueness(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        if (!isset($post['gname']) || $post['gname'] == '') {
            Analog::log(
                'Trying to check if group name is unique without name specified',
                Analog::INFO
            );
            return $response->withJson(
                ['success' => false, 'message' => htmlentities(_T("Group name is missing!"))]
            );
        } else {
            return $response->withJson(
                ['success' => Groups::isUnique($this->zdb, $post['gname'])]
            );
        }
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request        $request  PSR Request
     * @param Response       $response PSR Response
     * @param string         $option   One of 'page' or 'order'
     * @param string|integer $value    Value of the option
     * @param integer        $id       Member id to check rights
     *
     * @return Response
     */
    public function list(Request $request, Response $response, $option = null, $value = null, $id = null): Response
    {
        $groups = new Groups($this->zdb, $this->login);
        $group = new Group();
        $group->setLogin($this->login);

        $groups_root = $groups->getList(false);
        $groups_list = $groups->getList();

        if ($id !== null) {
            if ($this->login->isGroupManager($id)) {
                $group->load($id);
            } else {
                Analog::log(
                    'Trying to display group ' . $id . ' without appropriate permissions',
                    Analog::INFO
                );
                $response->setStatus(403);
                return $response;
            }
        }

        if ($id === null && count($groups_list) > 0) {
            $group = current($groups_list);
            if (!$this->login->isGroupManager($id)) {
                foreach ($groups_list as $g) {
                    if ($this->login->isGroupManager($g->getId())) {
                        $group = $g;
                        break;
                    }
                }
            }
        }

        //Active tab on page
        $tab_param = $request->getQueryParam('tab', $default = null);
        if (isset($tab_param)) {
            $tab = $tab_param;
        } else {
            $tab = 'group_information';
        }

        // display page
        $this->view->render(
            $response,
            'pages/groups_list.html.twig',
            array(
                'page_title'            => _T("Groups"),
                'groups_root'           => $groups_root,
                'groups'                => $groups_list,
                'group'                 => $group,
                'tab'                   => $tab
            )
        );
        return $response;
    }

    /**
     * Group page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function getGroup(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $id = $post['id_group'];
        $group = new Group((int)$id);
        if (!$group->canEdit($this->login)) {
            throw new \RuntimeException('Trying to edit group without appropriate permissions');
        }

        $groups = new Groups($this->zdb, $this->login);

        // display page
        $this->view->render(
            $response,
            'elements/group.html.twig',
            array(
                'mode'      => 'ajax',
                'groups'    => $groups->getList(),
                'group'     => $group
            )
        );
        return $response;
    }

    /**
     * Groups list page for ajax calls
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function simpleList(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $groups = new Groups($this->zdb, $this->login);

        // display page
        $this->view->render(
            $response,
            'elements/ajax_groups.html.twig',
            array(
                'mode'              => 'ajax',
                'groups_list'       => $groups->getList(),
                'selected_groups'   => (isset($post['groups']) ? $post['groups'] : [])
            )
        );
        return $response;
    }

    /**
     * Groups list page for ajax calls
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function ajaxMembers(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $ids = $post['persons'];
        $mode = $post['person_mode'];

        if (!$ids || !$mode) {
            Analog::log(
                'Missing persons and mode for ajaxGroupMembers',
                Analog::INFO
            );
            die();
        }

        $m = new Members();
        $persons = $m->getArrayList($ids);

        // display page
        $this->view->render(
            $response,
            'elements/group_persons.html.twig',
            [
                'persons'       => $persons,
                'person_mode'   => $mode
            ]
        );
        return $response;
    }

    /**
     * Filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function filter(Request $request, Response $response): Response
    {
        //no filters
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Record id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        //no edit page (included on list), just to satisfy inheritance
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Group id
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        $post = $request->getParsedBody();
        $group = new Group($id);
        if (!$group->canEdit($this->login)) {
            throw new \RuntimeException('Trying to edit group without appropriate permissions');
        }

        $group->setName($post['group_name']);
        try {
            if ($post['parent_group'] !== '') {
                $group->setParentGroup((int)$post['parent_group']);
            } else {
                $group->detach();
            }

            //handle group managers
            $managers_id = [];
            if (isset($post['managers'])) {
                $managers_id = $post['managers'];
            }
            $m = new Members();
            $managers = $m->getArrayList($managers_id);
            $group->setManagers($managers);

            //handle group members
            $members_id = [];
            if (isset($post['members'])) {
                $members_id = $post['members'];
            }
            $members = $m->getArrayList($members_id);
            $group->setMembers($members);

            $store = $group->store();
            if ($store === true) {
                $this->flash->addMessage(
                    'success_detected',
                    str_replace(
                        '%groupname',
                        $group->getName(),
                        _T("Group `%groupname` has been successfully saved.")
                    )
                );
            } else {
                //something went wrong :'(
                $this->flash->addMessage(
                    'error_detected',
                    _T("An error occurred while storing the group.")
                );
            }
        } catch (Throwable $e) {
            $this->flash->addMessage(
                'error_detected',
                $e->getMessage()
            );
        }

        if (isset($post['tab']) && $post['tab'] != 'general') {
            $tab = '?tab=' . $post['tab'];
        } else {
            $tab = '';
        }
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('groups', ['id' => $group->getId()]) . $tab);
    }

    /**
     * Reoder action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function reorder(Request $request, Response $response): Response
    {
        if (
            !$this->login->isAdmin()
            && !$this->login->isStaff()
            && !($this->login->isGroupManager() && $this->preferences->pref_bool_groupsmanagers_edit_groups)
        ) {
            throw new \RuntimeException('Trying to reorder groups without appropriate permissions');
        }

        $post = $request->getParsedBody();
        if (!isset($post['to']) || !isset($post['id_group']) || $post['id_group'] == '') {
            Analog::log(
                'Trying to reorder without required parameters!',
                Analog::INFO
            );
            $result = false;
        } else {
            $id = $post['id_group'];
            $group = new Group((int)$id);
            if (!empty($post['to'])) {
                $group->setParentGroup((int)$post['to']);
            } else {
                $group->detach();
            }
            $result = $group->store();
        }

        return $response->withJson(
            [
                'success'   =>  $result
            ]
        );
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args)
    {
        return $this->router->pathFor('groups');
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args)
    {
        return $this->router->pathFor(
            'doRemoveGroup',
            ['id' => (int)$args['id']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args)
    {
        $group = new Group((int)$args['id']);
        return sprintf(
            _T('Remove group %1$s'),
            $group->getFullName()
        );
    }

    /**
     * Remove object
     *
     * @param array $args Route arguments
     * @param array $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post)
    {
        $group = new Group((int)$post['id']);
        $group->setLogin($this->login);
        $cascade = isset($post['cascade']);
        $is_deleted = $group->remove($cascade);

        if ($is_deleted !== true && $group->isEmpty() === false) {
            $this->flash->addMessage(
                'error_detected',
                _T("Group is not empty, it cannot be deleted. Use cascade delete instead.")
            );
        }

        return $is_deleted;
    }

    /**
     * Removal confirmation parameters, can be overriden
     *
     * @param Request $request PSR Request
     *
     * @return array
     */
    protected function getconfirmDeleteParams(Request $request): array
    {
        return parent::getconfirmDeleteParams($request) + ['with_cascade' => true];
    }

    // CRUD - Delete
}
