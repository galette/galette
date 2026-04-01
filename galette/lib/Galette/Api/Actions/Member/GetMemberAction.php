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
use Galette\Api\Dto\MemberDto;
use Galette\Entity\Adherent;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/v1/members/{id}
 *
 * Get a single member. Staff/admin can access any member;
 * a regular member can only access their own profile.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GetMemberAction extends AbstractApiController
{
    /**
     * Handle GET /api/v1/members/{id}
     *
     * @param array<string, string> $args Route arguments
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $login = $this->getLogin($request);
        $id = (int)$args['id'];

        $isOwnProfile = $login->isLogged() && $login->id === $id;
        if (!$isOwnProfile && !$this->checkPermission($request, 'staff')) {
            return $this->forbidden($response);
        }

        $member = new Adherent($this->zdb, $id);
        if ($member->id === null) {
            return $this->notFound($response, 'Member not found');
        }

        return $this->json($response, MemberDto::fromAdherent($member)->toArray());
    }
}
