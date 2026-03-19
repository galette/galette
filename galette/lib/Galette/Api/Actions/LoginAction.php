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

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Galette API login
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LoginAction
{
    private $memberService;

    public function __construct($memberService)
    {
        // On injecte ici les services existants de Galette
        $this->memberService = $memberService;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $member = $this->memberService->get($id);

        /*if (!$member) {
            $response->getBody()->write(json_encode(['error' => 'Membre non trouvé']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }*/

        $payload = [
            'iss' => 'galette-api',           // Émetteur
            'aud' => 'galette-app',           // Audience
            'iat' => time(),                  // Temps de création
            'exp' => time() + (3600 * 24),    // Expiration (24h)
            'sub' => $userId                  // ID de l'adhérent
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        /*$payload = [
            'iat'  => time(),
            'exp'  => time() + 3600,
            'sub'  => $userId,
            'role' => $userRole, // Ex: 'admin' ou 'membre'
            'scopes' => ['member:read', 'member:write'] // Ou une liste d'actions précises
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256');*/

        // Renvoyez ensuite ce $jwt en JSON à l'utilisateur
        $response->getBody()->write(json_encode($jwt));


        // 1. Access Token (Court : 15 min)
        $accessTokenPayload = [
            'iat' => time(),
            'exp' => time() + 900,
            'sub' => $userId,
            'role' => $userRole
        ];
        $accessToken = JWT::encode($accessTokenPayload, $secretKey, 'HS256');

        // 2. Refresh Token (Long : 30 jours)
        $refreshToken = bin2hex(random_bytes(40)); // Une chaîne aléatoire forte

        // 3. Stockage du Refresh Token en base (Table Galette à créer : api_refresh_tokens)
        $this->tokenRepository->store($userId, $refreshToken, date('Y-m-d H:i:s', strtotime('+30 days')));

        $response = $response->withJson([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 900
        ]);
        return $response->withHeader('Content-Type', 'application/json');
    }
}