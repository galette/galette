<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Throwable;
use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Analog\Analog;

/**
 * Galette groups controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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
    public function doAdd(Request $request, Response $response, ?string $name = null): Response
    {
        $group = new Group();
        $group->setLogin($this->login);
        $group->setName($name);
        $group->store();
        if (!$this->login->isSuperAdmin()) {
            $group->setManagers([new Adherent($this->zdb, $this->login->id)]);
        }
        $id = $group->getId();

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('groups', ['id' => (string)$id]));
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
            return $this->withJson(
                $response,
                [
                    'success' => false,
                    'message' => htmlentities(_T("Group name is missing!"))
                ]
            );
        } else {
            return $this->withJson(
                $response,
                [
                    'success' => Groups::isUnique($this->zdb, $post['gname'])
                ]
            );
        }
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     * @param ?integer            $id       Member id to check rights
     *
     * @return Response
     */
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null,
        ?int $id = null
    ): Response {
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
                return $response->withStatus(403);
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

        $parent_groups = [];
        foreach ($groups_list as $parent_group) {
            if ($group->canSetParentGroup($parent_group)) {
                $parent_groups[] = $parent_group;
            }
        }

        //Active tab on page
        $tab = $request->getQueryParams['tab'] ?? 'group_information';

        // display page
        $this->view->render(
            $response,
            'pages/groups_list.html.twig',
            array(
                'page_title'            => _T("Groups"),
                'groups_root'           => $groups_root,
                'parent_groups'         => $parent_groups,
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
        return $response;
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
        return $response;
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

            $m = new Members();

            //handle group managers
            if (isset($post['managers'])) {
                $managers_id = $post['managers'];
                $managers = $m->getArrayList($managers_id);
                if (is_array($managers)) {
                    $group->setManagers($managers);
                }
            }

            //handle group members
            if (isset($post['members'])) {
                $members_id = $post['members'];
                $members = $m->getArrayList($members_id);
                if (is_array($members)) {
                    $group->setMembers($members);
                }
            }

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
            ->withHeader('Location', $this->routeparser->urlFor('groups', ['id' => (string)$group->getId()]) . $tab);
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

        return $this->withJson(
            $response,
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
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('groups');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveGroup',
            ['id' => (string)$args['id']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args): string
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
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post): bool
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
     * Removal confirmation parameters, can be override
     *
     * @param Request $request PSR Request
     *
     * @return array<string,mixed>
     */
    protected function getconfirmDeleteParams(Request $request): array
    {
        return parent::getconfirmDeleteParams($request) + ['with_cascade' => true];
    }

    // CRUD - Delete
}
