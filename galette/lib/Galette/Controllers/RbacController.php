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

use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Laminas\Db\Sql\Insert;

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
        $prefix = defined('PREFIX_DB') ? PREFIX_DB : 'galette_';

        // 1. Fetch all permissions
        $perms = $this->zdb->db->query(
            "SELECT * FROM {$prefix}permissions ORDER BY nom_perm",
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        )->toArray();

        // 2. Fetch all roles
        $roles = $this->zdb->db->query(
            "SELECT * FROM {$prefix}roles ORDER BY nom_role",
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        )->toArray();

        // 3. Fetch role_permissions mappings
        $mapping = $this->zdb->db->query(
            "SELECT id_role, id_perm FROM {$prefix}role_permissions",
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        )->toArray();

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

        $prefix = defined('PREFIX_DB') ? PREFIX_DB : 'galette_';

        $this->zdb->beginTransaction();
        try {
            $this->zdb->db->query(
                "DELETE FROM {$prefix}role_permissions",
                \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
            );

            foreach ($matrix as $roleId => $perms) {
                foreach ($perms as $permId => $val) {
                    if ($val === '1') {
                        $insert = new Insert($prefix . 'role_permissions');
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
        } catch (\Throwable $e) {
            $this->zdb->rollback();
            \Analog\Analog::log('Failed to save RBAC matrix: ' . $e->getMessage(), \Analog\Analog::ERROR);
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
