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
 * Galette API get member
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberActionGet
{
    private $memberService;

    public function __construct($memberService)
    {
        // On injecte ici les services existants de Galette
        $this->memberService = $memberService;
    }

    /**
     * @OA\Get(
     * path="/api/members/{id}",
     * summary="Récupère les détails d'un adhérent",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID de l'adhérent",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Succès",
     * @OA\JsonContent(ref="#/components/schemas/Member")
     * ),
     * @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $member = $this->memberService->get($id);

        if (!$member) {
            $response->getBody()->write(json_encode(['error' => 'Membre non trouvé']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($member));
        return $response->withHeader('Content-Type', 'application/json');
    }
}