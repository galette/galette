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

namespace Galette\Api\Actions\Contribution;

use Galette\Api\Controllers\AbstractApiController;
use Galette\Api\Dto\ContributionDto;
use Galette\Entity\Contribution;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * POST /api/v1/contributions
 *
 * Create a new contribution. Requires admin access.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CreateContributionAction extends AbstractApiController
{
    /**
     * Handle POST /api/v1/contributions
     *
     * @param array<string, string> $args Route arguments (unused)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        if (!$this->checkPermission($request, 'admin')) {
            return $this->forbidden($response);
        }

        $login = $this->getLogin($request);
        $body = (array)($request->getParsedBody() ?? []);

        $contribution = new Contribution($this->zdb, $login);
        $errors = $contribution->check($body, [], []);

        if (is_array($errors) && count($errors) > 0) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        try {
            $result = $contribution->store();
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }

        if (!$result) {
            return $this->json($response, ['error' => 'Contribution could not be saved'], 500);
        }

        return $this->json($response, ContributionDto::fromContribution($contribution)->toArray(), 201);
    }
}
