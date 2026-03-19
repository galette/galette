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

namespace Galette\Api;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Slim\Exception\HttpException;
use Slim\Psr7\Response;

/**
 * Galette API response formatter
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiResponseFormatter
{
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) {
        $statusCode = 500;
        $message = "Une erreur interne est survenue.";

        // Si c'est une exception propre à Slim (ex: 404 Not Found)
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $message = $exception->getMessage();
        }

        // En mode développement, on peut être plus bavard
        if ($displayErrorDetails) {
            $message = $exception->getMessage();
        }

        $payload = [
            'status' => 'error',
            'code' => $statusCode,
            'message' => $message
        ];

        $response = new Response();
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}