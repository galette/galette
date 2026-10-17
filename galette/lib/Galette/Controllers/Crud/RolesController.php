<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Access\Permissions;
use Galette\Access\Role;
use Galette\Access\Roles;
use Galette\Controllers\Attributes\Route;
use Galette\Controllers\CrudController;
use Galette\Core\Logs;
use Galette\Repository\Groups;
use Galette\Repository\Members;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Throwable;

/**
 * Roles controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RolesController extends CrudController
{
    #[Inject]
    protected Permissions $permissions;

    /**
     * Get roles repository
     */
    private function getRoles(): Roles
    {
        return new Roles($this->zdb, $this->permissions);
    }

    /**
     * Load a role
     *
     * @param Request $request Request
     * @param int     $id      Role identifier
     *
     * @throws HttpNotFoundException
     */
    private function loadRole(Request $request, int $id): Role
    {
        try {
            return new Role($id);
        } catch (Throwable) {
            throw new HttpNotFoundException($request);
        }
    }

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
        name: 'storeRole',
        pattern: '/roles',
        methods: ['POST']
    )]
    public function doAdd(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        $role = new Role();
        $role->setName((string)($post['name'] ?? ''));
        $parent = (int)($post['parent'] ?? 0);
        if ($parent > 0) {
            $role->setParentId($parent);
        }

        try {
            $stored = $role->getName() !== '' && $role->store($this->zdb);
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to add role `' . $role->getName() . '`', Analog::INFO);
            $stored = false;
        }

        if (!$stored) {
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('roles'),
                errors: [
                    sprintf(
                        //TRANS: %1$s is the role name
                        _T('Role \'%1$s\' has not been added; is its name missing or already used?'),
                        $role->getName()
                    )
                ]
            );
        }

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('editRole', ['id' => (string)$role->getId()]),
            successes: [
                sprintf(
                    //TRANS: %1$s is the role name
                    _T('Role \'%1$s\' has been added; choose its permissions.'),
                    $role->getName()
                )
            ]
        );
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Roles list page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
     */
    #[Route(
        name: 'roles',
        pattern: '/roles',
        methods: ['GET']
    )]
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
    {
        $roles = $this->getRoles();

        //system roles are computed from members data
        $members_count = [];
        foreach ($roles->getList() as $id => $role) {
            $members_count[$id] = $role->isSystem()
                ? $roles->countComputedMembersOf($role)
                : count($roles->getMembersOf($id));
        }

        $this->view->render(
            $response,
            'pages/roles_list.html.twig',
            [
                'page_title'    => _T("Roles"),
                'roles'         => $roles->getList(),
                'members_count' => $members_count
            ]
        );
        return $response;
    }

    /**
     * Roles filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        //no filtering
        return $response;
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int $id Role id
     */
    #[Route(
        name: 'editRole',
        pattern: '/roles/edit/{id:\d+}',
        methods: ['GET']
    )]
    public function edit(Request $request, Response $response, int $id): Response
    {
        $role = $this->loadRole($request, $id);
        $roles = $this->getRoles();

        $m = new Members();
        $members = $m->getDropdownMembers($this->zdb, $this->login);
        $dropdown = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];
        if (count($members)) {
            $dropdown['list'] = $members;
        }

        $this->view->render(
            $response,
            'pages/role_form.html.twig',
            [
                'page_title'    => sprintf(
                    //TRANS: %1$s is the role name
                    _T('Role \'%1$s\''),
                    $role->getLabel()
                ),
                'role'          => $role,
                'all_roles'     => $roles->getList(),
                'parents'       => $roles->getPossibleParents($role),
                'catalog'       => $this->permissions,
                'inherited'     => $roles->getInheritedPermissions($role),
                'given'         => $roles->getMembersOf($id),
                'computed'      => $role->isSystem() ? $roles->getComputedMembersOf($role) : null,
                'computed_count' => $role->isSystem() ? $roles->countComputedMembersOf($role) : null,
                'groups'        => Groups::getSimpleList(),
                'members'       => $dropdown
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param int $id Role id
     */
    #[Route(
        name: 'doEditRole',
        pattern: '/roles/edit/{id:\d+}',
        methods: ['POST']
    )]
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        $role = $this->loadRole($request, $id);
        $post = $request->getParsedBody();
        $edit_uri = $this->routeparser->urlFor('editRole', ['id' => (string)$id]);

        if (isset($post['cancel'])) {
            return $this->redirect(response: $response, redirect_url: $this->routeparser->urlFor('roles'));
        }

        try {
            if (!$role->isSystem()) {
                $role->setName((string)($post['name'] ?? ''));
                $parent = (int)($post['parent'] ?? 0);
                $parents = $this->getRoles()->getPossibleParents($role);
                if ($parent > 0 && !isset($parents[$parent])) {
                    throw new \InvalidArgumentException('Parent role would create a cycle.');
                }
                $role->setParentId($parent > 0 ? $parent : null);
            }
            $role->setPermissions(array_keys($post['permissions'] ?? []), $this->permissions);
            $stored = $role->store($this->zdb);
        } catch (Throwable $e) {
            Logs::exception($e, 'Unable to store role #' . $id, Analog::INFO);
            $stored = false;
        }

        if (!$stored) {
            return $this->redirect(
                response: $response,
                redirect_url: $edit_uri,
                errors: [
                    sprintf(
                        //TRANS: %1$s is the role name
                        _T('Role \'%1$s\' has not been modified!'),
                        $role->getLabel()
                    )
                ]
            );
        }

        $this->history->add(
            sprintf(
                //TRANS: %1$s is the role name
                _T('Role \'%1$s\' modified'),
                $role->getLabel()
            )
        );
        return $this->redirect(
            response: $response,
            redirect_url: $edit_uri,
            successes: [
                sprintf(
                    //TRANS: %1$s is the role name
                    _T('Role \'%1$s\' has been successfully modified.'),
                    $role->getLabel()
                )
            ]
        );
    }

    /**
     * Give role to a member
     *
     * @param int $id Role id
     */
    #[Route(
        name: 'giveRole',
        pattern: '/roles/{id:\d+}/members',
        methods: ['POST']
    )]
    public function give(Request $request, Response $response, int $id): Response
    {
        $role = $this->loadRole($request, $id);
        $post = $request->getParsedBody();
        [$successes, $errors] = $this->giveTo(
            $role,
            (int)($post['id_adh'] ?? 0),
            (int)($post['id_group'] ?? 0)
        );

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('editRole', ['id' => (string)$id]),
            successes: $successes,
            errors: $errors
        );
    }

    /**
     * Give a role to a member, and log it
     *
     * @param Role $role   Role
     * @param int  $member Member identifier
     * @param int  $group  Group identifier, 0 for all groups
     *
     * @return array{0: array<string>, 1: array<string>} successes and errors
     */
    private function giveTo(Role $role, int $member, int $group): array
    {
        if ($role->isSystem()) {
            return [[], [_T('System roles are given from members data.')]];
        }
        if ($member <= 0) {
            return [[], [_T('Please choose a member.')]];
        }
        if (!$this->getRoles()->give($member, (int)$role->getId(), $group > 0 ? $group : null)) {
            return [[], [_T('Member already has this role.')]];
        }

        $this->history->add(
            sprintf(
                //TRANS: %1$s is the role name, %2$d the member identifier
                _T('Role \'%1$s\' given to member #%2$d'),
                $role->getLabel(),
                $member
            )
        );
        return [
            [
                sprintf(
                    //TRANS: %1$s is the role name
                    _T('Role \'%1$s\' has been given.'),
                    $role->getLabel()
                )
            ],
            []
        ];
    }

    /**
     * Take a given role back
     *
     * @param int $id         Role id
     * @param int $assignment Assignment id
     */
    #[Route(
        name: 'takeRole',
        pattern: '/roles/{id:\d+}/members/remove/{assignment:\d+}',
        methods: ['POST']
    )]
    public function take(Request $request, Response $response, int $id, int $assignment): Response
    {
        $role = $this->loadRole($request, $id);
        [$successes, $errors] = $this->takenBack($role, $this->getRoles()->take($id, $assignment));

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('editRole', ['id' => (string)$id]),
            successes: $successes,
            errors: $errors
        );
    }

    /**
     * Messages after a role has been taken back
     *
     * @param Role $role  Role
     * @param bool $taken Whether the role has been taken back
     *
     * @return array{0: array<string>, 1: array<string>} successes and errors
     */
    private function takenBack(Role $role, bool $taken): array
    {
        if (!$taken) {
            return [[], [_T('This role was not given.')]];
        }
        return [
            [
                sprintf(
                    //TRANS: %1$s is the role name
                    _T('Role \'%1$s\' has been taken back.'),
                    $role->getLabel()
                )
            ],
            []
        ];
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Removal confirmation
     */
    #[Route(
        name: 'removeRole',
        pattern: '/roles/remove/{id:\d+}',
        methods: ['GET']
    )]
    public function confirmDelete(Request $request, Response $response): Response
    {
        return parent::confirmDelete($request, $response);
    }

    /**
     * Removal
     */
    #[Route(
        name: 'doRemoveRole',
        pattern: '/roles/remove/{id:\d+}',
        methods: ['POST']
    )]
    public function delete(Request $request, Response $response): Response
    {
        return parent::delete($request, $response);
    }

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('roles');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveRole',
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
        $role = new Role((int)$args['id']);
        return sprintf(
            //TRANS: %1$s is the role name
            _T('Remove role %1$s'),
            $role->getLabel()
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
        $role = new Role((int)$args['id']);
        return $role->delete();
    }

    // /CRUD - Delete
}
