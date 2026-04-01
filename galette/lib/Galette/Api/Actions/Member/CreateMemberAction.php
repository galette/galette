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
use Throwable;

/**
 * POST /api/v1/members
 *
 * Create a new member. Requires admin access.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CreateMemberAction extends AbstractApiController
{
    /**
     * Handle POST /api/v1/members
     *
     * @param array<string, string> $args Route arguments (unused)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        if (!$this->checkPermission($request, 'admin')) {
            return $this->forbidden($response);
        }

        $body = (array)($request->getParsedBody() ?? []);
        $member = new Adherent($this->zdb);
        $errors = $member->check($body, [], []);

        if (is_array($errors) && count($errors) > 0) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        try {
            $result = $member->store();
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }

        if (!$result) {
            return $this->json($response, ['error' => 'Member could not be saved'], 500);
        }

        return $this->json($response, MemberDto::fromAdherent($member)->toArray(), 201);
    }
}
