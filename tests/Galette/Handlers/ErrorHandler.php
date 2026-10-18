<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Handlers;

use Galette\Tests\BaseGaletteTestCase;
use Slim\CallableResolver;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Error handler tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ErrorHandler extends BaseGaletteTestCase
{
    /**
     * Run the handler on an exception
     *
     * @param \Throwable                                $e       Exception
     * @param ?\Psr\Http\Message\ServerRequestInterface $request Request
     */
    private function handle(\Throwable $e, ?\Psr\Http\Message\ServerRequestInterface $request = null): int
    {
        $handler = new \Galette\Handlers\ErrorHandler(new CallableResolver(), new ResponseFactory());
        $request ??= (new ServerRequestFactory())->createServerRequest('POST', '/document/add?token=secret');

        return $handler($request, $e, false, true, true)->getStatusCode();
    }

    /**
     * Test an uncaught exception is logged with its trace and the request
     */
    public function testUncaughtException(): void
    {
        global $galette_log_var;

        $this->assertSame(500, $this->handle(new \RuntimeException('Disk full')));
        $log = (string)$galette_log_var;

        $this->assertStringContainsString('ERROR - Uncaught exception on POST /document/add | Disk full', $log);
        $this->assertStringContainsString('Exception: RuntimeException at ' . __FILE__, $log);
        $this->assertStringContainsString('Stack trace:', $log);
        //query string may hold tokens
        $this->assertStringNotContainsString('secret', $log);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Uncaught exception on POST /document/add | Disk full');
    }

    /**
     * Test an exception already logged is not detailed again
     */
    public function testAlreadyLoggedException(): void
    {
        global $galette_log_var;

        $e = new \RuntimeException('Disk full');
        \Galette\Core\Logs::exception($e, 'Storing document');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Storing document | Disk full');

        $this->handle($e);
        $this->assertStringNotContainsString('Stack trace', (string)$galette_log_var);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Disk full (RuntimeException, details already logged)');
    }

    /**
     * Test client errors are logged on a single line, without trace
     */
    public function testClientErrors(): void
    {
        global $galette_log_var;

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/nowhere');
        $this->assertSame(404, $this->handle(new HttpNotFoundException($request), $request));
        $this->expectLogEntry(\Analog\Analog::INFO, '404 Not Found | GET /nowhere');

        $this->assertSame(403, $this->handle(new HttpForbiddenException($request), $request));
        $this->assertStringNotContainsString('Stack trace', (string)$galette_log_var);
        $this->expectLogEntry(\Analog\Analog::WARNING, '403 Forbidden | GET /nowhere');
    }
}
