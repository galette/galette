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

namespace Galette\Api\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Galette API create member
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MembersActionList
{
    private $memberService;

    public function __construct($memberService)
    {
        // On injecte ici les services existants de Galette
        $this->memberService = $memberService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        // Valeurs par défaut avec bridage de sécurité
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 20;
        $offset = ($page - 1) * $limit;

        // Appel au service Galette avec LIMIT et OFFSET
        $members = $this->memberService->getList($limit, $offset);
        $total = $this->memberService->countAll();

        $payload = [
            'data' => $members,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_records' => $total,
                'total_pages' => ceil($total / $limit),
                'links' => [
                    'next' => "/api/members?page=" . ($page + 1) . "&limit=$limit",
                    'prev' => $page > 1 ? "/api/members?page=" . ($page - 1) . "&limit=$limit" : null,
                ]
            ]
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }
}