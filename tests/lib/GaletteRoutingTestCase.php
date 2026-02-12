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

namespace Galette\Tests;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

/**
 * Galette routing tests case main class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class GaletteRoutingTestCase extends GaletteTestCase
{
    /**
     * Create Psr7 Request
     *
     * @param string                $route_name   Route name
     * @param array<string, string> $route_args   Route arguments
     * @param string                $method       HTTP method to use
     * @param string                $content_type Content type to use
     * @param array<string, string> $query_params Query parameters to add to URL
     */
    protected function createRequest(
        string $route_name,
        array $route_args = [],
        string $method = 'GET',
        string $content_type = 'text/html',
        array $query_params = []
    ): \Slim\Psr7\Request {
        $route = $this->routeparser->urlFor($route_name, $route_args, $query_params);

        $this->view->getEnvironment()->addGlobal('cur_route', $route_name);
        $this->view->getEnvironment()->addGlobal('cur_route_args', $route_args);
        $this->view->getEnvironment()->addGlobal('cur_subroute', array_shift($route_args));

        $ufactory = new \Slim\Psr7\Factory\UriFactory();
        $sfactory = new \Slim\Psr7\Factory\StreamFactory();

        return new \Slim\Psr7\Request(
            $method,
            $ufactory->createUri($route),
            new \Slim\Psr7\Headers(['Content-Type' => [$content_type]]),
            [],
            [],
            $sfactory->createStream()
        );
    }

    /**
     * Assert request has been refused from authentication middleware
     *
     * @param Response|ResponseInterface $test_response Response to test
     */
    protected function expectAuthMiddlewareRefused(Response|ResponseInterface $test_response): void
    {
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->assertSame(
            ['error_detected' => ['You do not have permission for requested URL.']],
            $this->flash_data['slimFlash']
        );
        $this->flash_data = [];
    }

    /**
     * Assert request requires a logged in user
     *
     * @param Response|ResponseInterface $test_response Response to test
     */
    protected function expectLogin(Response|ResponseInterface $test_response): void
    {
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Login required']]);
    }

    /**
     * Assert request has been successfully processed
     *
     * @param Response|ResponseInterface $test_response Response to test
     * @param array<string, string[]>    $headers       Expected headers
     */
    protected function expectOK(Response|ResponseInterface $test_response, array $headers = []): void
    {
        $this->assertSame($headers, $test_response->getHeaders());
        $this->assertSame(200, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData([]);
    }

    /**
     * Assert Flash data correspond to what is expected, and reset it
     *
     * @param array<string, string[]> $expected Expected flash data
     */
    protected function expectFlashData(array $expected): void
    {
        $this->assertSame($expected, $this->flash_data['slimFlash'] ?? []);
        $this->flash_data = [];
    }
}
