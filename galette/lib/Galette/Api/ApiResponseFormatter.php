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
use Slim\Exception\HttpException;
use Slim\Psr7\Response;
use Throwable;

use function Safe\json_encode;

/**
 * Galette API response formatter
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiResponseFormatter
{
    /**
     * Format any exception as a JSON error response.
     *
     * @param ServerRequestInterface $request             Request
     * @param Throwable              $exception           Exception
     * @param bool                   $displayErrorDetails Show error details
     * @param bool                   $logErrors           Log errors
     * @param bool                   $logErrorDetails     Log error details
     */
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response {
        $statusCode = 500;
        $message = 'An internal error occurred.';

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $message = $exception->getMessage();
        }

        if ($displayErrorDetails) {
            $message = $exception->getMessage();
        }

        $payload = [
            'status'  => 'error',
            'code'    => $statusCode,
            'message' => $message,
        ];

        $response = new Response();
        $response->getBody()->write(
            (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}
