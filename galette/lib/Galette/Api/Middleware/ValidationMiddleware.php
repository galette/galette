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

namespace Galette\Api\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/**
 * Galette API validation middleware
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ValidationMiddleware
{
    private array $rules;

    public function __construct(array $rules)
    {
        // Exemple de règles : ['nom' => 'required', 'email' => 'email']
        $this->rules = $rules;
    }

    public function __invoke(Request $request, Handler $handler): Response
    {
        $data = $request->getParsedBody();
        $errors = [];

        foreach ($this->rules as $field => $rule) {
            if ($rule === 'required' && (empty($data[$field]))) {
                $errors[$field] = "Le champ '$field' est obligatoire.";
            }
            // Ajoutez ici d'autres vérifications (format email, longueur, etc.)
        }

        if (!empty($errors)) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'errors' => $errors
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}