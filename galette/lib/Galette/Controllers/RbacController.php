<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Controllers;

use Analog\Analog;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * RBAC administration controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RbacController extends AbstractController
{
    /**
     * Display RBAC matrix
     */
    public function index(Response $response): ResponseInterface
    {
        // 1. Fetch all permissions
        $select = $this->zdb->select(
            'permissions'
        );
        $select->order('nom_perm');
        $perms = $this->zdb->execute($select)->toArray();

        // 2. Fetch all roles
        $select = $this->zdb->select(
            'roles'
        );
        $select->order('id_role');
        $roles = $this->zdb->execute($select)->toArray();

        // 3. Fetch role_permissions mappings
        $select = $this->zdb->select(
            'role_permissions'
        );
        $select->columns(['id_role', 'id_perm']);
        $mapping = $this->zdb->execute($select)->toArray();

        $role_perms = [];
        foreach ($mapping as $m) {
            $role_perms[(int)$m['id_role']][] = (int)$m['id_perm'];
        }

        $grouped_perms = [];
        foreach ($perms as $p) {
            $domain = 'other';
            if (str_contains($p['nom_perm'], ':')) {
                $domain = explode(':', $p['nom_perm'], 2)[0];
            }
            $grouped_perms[$domain][] = $p;
        }

        return $this->view->render(
            $response,
            'pages/rbac_matrix.html.twig',
            [
                'page_title' => _T('Permissions Management'),
                'grouped_permissions' => $grouped_perms,
                'roles'       => $roles,
                'role_perms'  => $role_perms
            ]
        );
    }

    /**
     * Save RBAC matrix
     */
    public function save(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $matrix = $post['matrix'] ?? [];

        $this->zdb->beginTransaction();
        try {
            // TODO: check for rights validity. For example, a Group manager cannot have group:assign if he do not have member:list. Either make it implicit (not a good idea IMHO), or just add and validate dependencies between rights (usine à gaz powered :/)
            $delete = $this->zdb->delete('role_permissions');
            $this->zdb->execute($delete);

            foreach ($matrix as $roleId => $perms) {
                foreach ($perms as $permId => $val) {
                    if ($val === '1') {
                        // TODO: use prepared statement
                        $insert = $this->zdb->insert('role_permissions');
                        $insert->values([
                            'id_role' => (int)$roleId,
                            'id_perm' => (int)$permId
                        ]);
                        $this->zdb->execute($insert);
                    }
                }
            }
            $this->zdb->commit();
            $success = [_T('Permissions updated successfully.')];
        } catch (Throwable $e) {
            $this->zdb->rollback();
            Analog::log('Failed to save RBAC matrix: ' . $e->getMessage(), Analog::ERROR);
            $error = [_T('Failed to update permissions.')];
        }

        return $this->redirect(
            $response,
            $this->routeparser->urlFor('rbac_matrix'),
            $success ?? [],
            [],
            $error ?? []
        );
    }
}
