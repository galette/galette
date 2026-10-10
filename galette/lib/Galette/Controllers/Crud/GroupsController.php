<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Throwable;
use Galette\Controllers\Attributes\Route;
use Galette\Controllers\CrudController;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Analog\Analog;
use Galette\Core\Logs;

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
     */
    public function add(Request $request, Response $response): Response
    {
        //no new page (included on list), just to satisfy inheritance
        return $response;
    }

    /**
     * Add action
     */
    #[Route(
        name: 'doAddGroup',
        pattern: '/group/add',
        methods: ['POST']
    )]
    public function doAdd(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if (!isset($post['gname']) || $post['gname'] == '') {
            return $this->withJson(
                $response,
                [
                    'success' => false,
                    'message' => htmlentities(_T("Group name is missing!"))
                ]
            );
        }
        $name = $post['gname'];

        //check group name uniqueness
        if (!Groups::isUnique($this->zdb, $post['gname'])) {
            return $this->withJson(
                $response,
                [
                    'success' => false,
                    'message' => htmlentities(_T("Group name already exists!"))
                ]
            );
        }

        $group = new Group();
        $group
            ->setLogin($this->login)
            ->setName($name)
            ->store();
        if (!$this->login->isSuperAdmin()) {
            $group->setManagers([new Adherent($this->zdb, $this->login->id)]);
        }
        $id = $group->getId();

        $redirect = $this->routeparser->urlFor('doEditGroup', ['id' => (string)$id]);
        if ($this->isAjax($request)) {
            $this->flash->addMessage(
                'success_detected',
                _T("Group added")
            );
            return $this->withJson(
                $response,
                [
                    'success' => true,
                    'redirect' => $redirect
                ]
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
     */
    #[Route(
        name: 'groups',
        pattern: '/groups',
        methods: ['GET']
    )]
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null
    ): Response {
        $groups = new Groups($this->zdb, $this->login);
        $group = new Group();
        $group->setLogin($this->login);

        $groups_root = $groups->getList(full: false);
        $groups_list = $groups->getList();

        //group managers only see the groups they manage, and their ancestors to keep the tree readable
        $visible_groups = null;
        if ($this->accessControl->getGroupScope('group:read') !== null) {
            $visible_groups = [];
            foreach ($groups_list as $visible_group) {
                do {
                    $visible_groups[] = $visible_group->getId();
                } while ($visible_group = $visible_group->getParentGroup());
            }
            $visible_groups = array_values(array_unique($visible_groups));
            $groups_root = array_filter(
                $groups_root,
                fn(Group $root): bool => in_array($root->getId(), $visible_groups, strict: true)
            );
        }

        // display page
        $this->view->render(
            $response,
            'pages/groups_list.html.twig',
            [
                'page_title'            => _T("Groups"),
                'groups_root'           => $groups_root,
                'visible_groups'        => $visible_groups,
                'is_paginated'          => false,
                'form'                  => true,
                'table'                 => [
                    'class' => 'unstackable',
                    'tbody' => [
                        'id'    => 'listed_groups',
                        'class' => 'sortable-items'
                    ]
                ],
                'nb'                    => count($groups_list)
            ]
        );
        return $response;
    }

    /**
     * List reorder
     */
    #[Route(
        name: 'reorderGroups',
        pattern: '/groups/reorder',
        methods: ['POST']
    )]
    public function reorderList(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $list = '<ul>';
        if (isset($post['reordered']) && !empty($post['reordered'])) {
            foreach ($post['reordered'] as $value) {
                $item = explode('|', (string)$value);
                $id = $item[0];
                $parentId = $item[1];
                $group = new Group((int)$id);
                if (!$group->canEdit($this->login)) {
                    Analog::log(
                        'Trying to reorder group ' . $id . ' without appropriate permissions',
                        Analog::WARNING
                    );
                    continue;
                }
                if (!$this->canMoveUnder($group, (int)$parentId)) {
                    continue;
                }
                $parentGroup = new Group((int)$parentId);
                if ($parentId != '0') {
                    $group->setParentGroup((int)$parentId);
                    $change = sprintf(
                        _T('New parent is %1$s'),
                        $parentGroup->getName()
                    );
                } else {
                    $group->detach();
                    $change = _T("Parent removed");
                }
                $group->store();
                $list .= '<li>' . $group->getName() . ' (' . $change . ')</li>';
            }
            $list .= '</ul>';
            $this->flash->addMessage(
                'success_detected',
                sprintf(
                    _T('The parent of the following groups has been successfully modified : %1$s'),
                    $list
                )
            );
        }
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('groups'));
    }

    /**
     * Group page
     */
    #[Route(
        name: 'ajax_group',
        pattern: '/ajax/group',
        methods: ['POST']
    )]
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
            [
                'mode'      => 'ajax',
                'groups'    => $groups->getList(),
                'group'     => $group
            ]
        );
        return $response;
    }

    /**
     * Groups list page for ajax calls
     */
    #[Route(
        name: 'ajax_groups',
        pattern: '/ajax/groups',
        methods: ['POST']
    )]
    public function simpleList(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $groups = new Groups($this->zdb, $this->login);

        // display page
        $this->view->render(
            $response,
            'elements/ajax_groups.html.twig',
            [
                'mode'              => 'ajax',
                'groups_list'       => $groups->getList(),
                'selected_groups'   => ($post['groups'] ?? [])
            ]
        );
        return $response;
    }

    /**
     * Group members ajax loader
     */
    #[Route(
        name: 'ajaxGroupMembers',
        pattern: '/ajax/group/members',
        methods: ['POST']
    )]
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
     * @param int $id Record id
     */
    #[Route(
        name: 'editGroup',
        pattern: '/group/edit/{id:\d+}',
        methods: ['GET']
    )]
    public function edit(Request $request, Response $response, int $id): Response
    {
        $groups = new Groups($this->zdb, $this->login);
        $group = new Group();
        $group->setLogin($this->login);

        $groups_list = $groups->getList();

        if (!$group->load($id) || !$group->canShow($this->login)) {
            Analog::log(
                'Trying to display group ' . $id . ' without appropriate permissions',
                Analog::INFO
            );
            throw new HttpForbiddenException($request);
        }

        $parent_groups = [];
        foreach ($groups_list as $parent_group) {
            if ($group->canSetParentGroup($parent_group)) {
                $parent_groups[] = $parent_group;
            }
        }

        // display page
        $this->view->render(
            $response,
            'pages/group_form.html.twig',
            [
                'page_title'            => sprintf('%1$s - %2$s', _T('Group'), $group->getName()),
                'parent_groups'         => $parent_groups,
                'group'                 => $group
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param int $id Group id
     */
    #[Route(
        name: 'doEditGroup',
        pattern: '/group/edit/{id:\d+}',
        methods: ['POST']
    )]
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        $post = $request->getParsedBody();
        $group = new Group($id);
        if (!$group->canEdit($this->login)) {
            throw new \RuntimeException('Trying to edit group without appropriate permissions');
        }

        $group->setName($post['group_name']);
        try {
            if (!$this->canMoveUnder($group, (int)$post['parent_group'])) {
                throw new \RuntimeException(
                    sprintf(
                        _T('Group `%1$s` cannot be set as parent!'),
                        (new Group((int)$post['parent_group']))->getName()
                    )
                );
            }
            if ($post['parent_group'] !== '') {
                $group->setParentGroup((int)$post['parent_group']);
            } else {
                $group->detach();
            }

            //handle group managers
            if (isset($post['managers'])) {
                $managers = $this->getPostedPersons($group, $post['managers'], Group::MANAGER_TYPE);
                if (is_array($managers)) {
                    $group->setManagers($managers);
                }
            }

            //handle group members
            if (isset($post['members'])) {
                $members = $this->getPostedPersons($group, $post['members'], Group::MEMBER_TYPE);
                if (is_array($members)) {
                    $group->setMembers($members);
                }
            }

            $store = $group->store();
            if ($store === true) {
                $this->flash->addMessage(
                    'success_detected',
                    sprintf(
                        _T('Group `%1$s` has been successfully saved.'),
                        $group->getName(),
                    )
                );
            } else {
                //something went wrong :'(
                $this->flash->addMessage(
                    'error_detected',
                    _T("An error occurred while saving the group.")
                );
            }
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to store group #' . $id, Analog::INFO);
            $this->flash->addMessage(
                'error_detected',
                $e->getMessage()
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('groups', ['id' => (string)$group->getId()]));
    }

    /**
     * Reorder action
     */
    #[Route(
        name: 'ajax_groups_reorder',
        pattern: '/ajax/groups/reorder',
        methods: ['POST']
    )]
    public function reorder(Request $request, Response $response): Response
    {
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
            if (!$group->canEdit($this->login)) {
                throw new \RuntimeException('Trying to reorder groups without appropriate permissions');
            }
            if (!$this->canMoveUnder($group, (int)$post['to'])) {
                $result = false;
            } else {
                if (!empty($post['to'])) {
                    $group->setParentGroup((int)$post['to']);
                } else {
                    $group->detach();
                }
                $result = $group->store();
            }
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
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('groups');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
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
     */
    protected function doDelete(array $args, array $post): bool
    {
        $group = new Group((int)$post['id']);
        $group->setLogin($this->login);
        $cascade = isset($post['cascade']);
        $is_deleted = $group->remove($cascade);

        if ($is_deleted !== true) {
            $blockers = $group->getRemovalBlockers();
            foreach ($blockers as $blocker) {
                $this->flash->addMessage('error_detected', $blocker);
            }
            if (count($blockers) === 0 && $group->isEmpty() === false) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("Group is not empty, it cannot be deleted. Use cascade delete instead.")
                );
            }
        }

        return $is_deleted;
    }

    /**
     * Removal confirmation parameters, can be overridden
     *
     * @return array<string,mixed>
     */
    protected function getconfirmDeleteParams(Request $request): array
    {
        return parent::getconfirmDeleteParams($request) + ['with_cascade' => true];
    }

    // CRUD - Delete

    /**
     * Can current user move a group under given parent?
     *
     * As on the groups list, group managers can only move a group under a group
     * they manage. Moving at the top, or keeping current parent, is always allowed.
     *
     * @param Group $group     Group to move
     * @param int   $parent_id Parent group id, 0 for none
     */
    private function canMoveUnder(Group $group, int $parent_id): bool
    {
        if (
            $parent_id === 0
            || $parent_id === $group->getParentGroup()?->getId()
            || (new Group($parent_id))->canEdit($this->login)
        ) {
            return true;
        }

        Analog::log(
            'Trying to move group ' . $group->getId() . ' under group ' . $parent_id
                . ' without appropriate permissions',
            Analog::WARNING
        );
        return false;
    }

    /**
     * Get persons posted for a group.
     *
     * Only admin and staff can change managers and members from the group page,
     * as the user interface does. Group managers change members of their groups
     * from member pages. The group form always posts current persons back, so
     * a warning is logged only if a group manager tries to change them.
     *
     * @param Group        $group Group instance, before changes
     * @param array<mixed> $ids   Posted persons ids
     * @param int          $type  Either Group::MEMBER_TYPE or Group::MANAGER_TYPE
     *
     * @return array<int, Adherent>|false
     */
    private function getPostedPersons(Group $group, array $ids, int $type): array|false
    {
        if ($this->accessControl->getGroupScope('member:read') === null) {
            $m = new Members();
            return $m->getArrayList($ids);
        }

        $current = $type === Group::MANAGER_TYPE ? $group->getManagers() : $group->getMembers();
        $current = array_map(fn(Adherent $person): int => (int)$person->id, $current);
        $ids = array_unique(array_map(intval(...), $ids));
        sort($current);
        sort($ids);
        if ($ids !== $current) {
            Analog::log(
                sprintf(
                    'Trying to change %1$s of group %2$s without appropriate permissions',
                    $type === Group::MANAGER_TYPE ? 'managers' : 'members',
                    $group->getId()
                ),
                Analog::WARNING
            );
        }

        return false;
    }
}
