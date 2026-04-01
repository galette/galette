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

namespace Galette\Api\Actions\Group;

use Galette\Api\Controllers\AbstractApiController;
use Galette\Api\Dto\GroupDto;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/v1/groups
 *
 * List all groups. Requires staff access or higher.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListGroupsAction extends AbstractApiController
{
    /**
     * Handle GET /api/v1/groups
     *
     * @param array<string, string> $args Route arguments (unused)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        if (!$this->checkPermission($request, 'staff')) {
            return $this->forbidden($response);
        }

        $login = $this->getLogin($request);
        $repo = new Groups($this->zdb, $login);
        $groups = $repo->getList(true);

        $data = array_map(
            static fn(Group $g) => GroupDto::fromGroup($g)->toArray(),
            $groups
        );

        return $this->json($response, ['data' => $data]);
    }
}
