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
use Galette\Filters\ContributionsList;
use Galette\Repository\Contributions;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/v1/contributions
 *
 * List contributions with pagination. Requires staff access or higher.
 * Query params: page, per_page, id_adh (optional member filter)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListContributionsAction extends AbstractApiController
{
    /**
     * Handle GET /api/v1/contributions
     *
     * @param array<string, string> $args Route arguments (unused)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        if (!$this->checkPermission($request, 'staff')) {
            return $this->forbidden($response);
        }

        $login = $this->getLogin($request);
        $params = $request->getQueryParams();
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 20)));

        $filters = new ContributionsList();
        $filters->current_page = $page;
        $filters->show = $perPage;

        if (isset($params['id_adh'])) {
            $filters->filtre_cotis_adh = (int)$params['id_adh'];
        }

        $repo = new Contributions($this->zdb, $login, $filters);
        $contributions = $repo->getList(true);

        $data = array_map(
            static fn(Contribution $c) => ContributionDto::fromContribution($c)->toArray(),
            $contributions
        );

        return $this->json($response, [
            'data'       => $data,
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $filters->counter,
            ],
        ]);
    }
}
