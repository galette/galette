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

namespace Galette\Api\Actions\Member;

use Galette\Api\Controllers\AbstractApiController;
use Galette\Entity\Adherent;
use Galette\Repository\Members;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * DELETE /api/v1/members/{id}
 *
 * Delete a member. Requires admin access.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DeleteMemberAction extends AbstractApiController
{
    /**
     * Handle DELETE /api/v1/members/{id}
     *
     * @param array<string, string> $args Route arguments
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        if (!$this->checkPermission($request, 'admin')) {
            return $this->forbidden($response);
        }

        $id = (int)$args['id'];
        $member = new Adherent($this->zdb, $id);
        if ($member->id === null) {
            return $this->notFound($response, 'Member not found');
        }

        try {
            $repo = new Members();
            $result = $repo->removeMembers($id);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }

        if (!$result) {
            return $this->json($response, ['error' => 'Member could not be deleted'], 500);
        }

        return $this->json($response, null, 204);
    }
}
