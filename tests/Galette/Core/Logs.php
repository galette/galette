<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;

use function Safe\ini_set;
use function Safe\touch;
use function Safe\strtotime;

/**
 * Logs tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Logs extends GaletteTestCase
{
    /**
     * Test cleanup
     */
    public function testCleanup(): void
    {
        //create a fake old log file
        touch(GALETTE_LOGS_PATH . '/my.log', strtotime('-2 months'));
        $this->assertFileExists(GALETTE_LOGS_PATH . '/my.log');

        \Galette\Core\Logs::cleanup();

        $this->assertFileDoesNotExist(GALETTE_LOGS_PATH . '/my.log');
    }

    /**
     * Test exception logging
     */
    public function testException(): void
    {
        global $galette_log_var;

        $previous = new \LogicException('Root cause');
        $e = new \RuntimeException('Something failed', 0, $previous);
        \Galette\Core\Logs::exception($e, 'Doing something');
        $log = (string)$galette_log_var;

        $this->assertStringContainsString('ERROR - Doing something | Something failed', $log);
        $this->assertStringContainsString('Exception: RuntimeException at ' . __FILE__, $log);
        $this->assertStringContainsString('Caused by: LogicException: Root cause at ' . __FILE__, $log);
        $this->assertStringContainsString('Stack trace (LogicException):', $log);
        $this->assertStringContainsString(self::class . '->testException()', $log);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Doing something | Something failed');
    }

    /**
     * Test an exception is logged with details only once
     */
    public function testExceptionLoggedOnce(): void
    {
        global $galette_log_var;

        $previous = new \LogicException('Root cause');
        \Galette\Core\Logs::exception($previous, 'First', \Analog\Analog::WARNING);
        $this->expectLogEntry(\Analog\Analog::WARNING, 'First | Root cause');

        //same exception again
        \Galette\Core\Logs::exception($previous, 'Again');
        $log = (string)$galette_log_var;
        $this->assertStringContainsString('Again | Root cause (LogicException, details already logged)', $log);
        $this->assertStringNotContainsString('Stack trace', $log);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Again | Root cause');

        //wrapping exception
        $e = new \RuntimeException('Wrapped', 0, $previous);
        \Galette\Core\Logs::exception($e, 'Caller');
        $log = (string)$galette_log_var;
        $this->assertStringContainsString('Caller | Wrapped (RuntimeException, details already logged)', $log);
        $this->assertStringNotContainsString('Stack trace', $log);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Caller | Wrapped');
    }

    /**
     * Test call arguments are not written in the stack trace
     */
    public function testExceptionTraceWithoutArguments(): void
    {
        global $galette_log_var;

        //arguments are part of PHP traces depending on configuration
        $ignore_args = ini_set('zend.exception_ignore_args', '0');
        $max_len = ini_set('zend.exception_string_param_max_len', '15');
        $e = $this->failWith('mysecret');
        $this->assertStringContainsString('mysecret', $e->getTraceAsString());

        \Galette\Core\Logs::exception($e);
        ini_set('zend.exception_ignore_args', $ignore_args);
        ini_set('zend.exception_string_param_max_len', $max_len);
        $log = (string)$galette_log_var;

        $this->assertStringContainsString('ERROR - Failure', $log);
        $this->assertStringContainsString(self::class . '->failWith()', $log);
        $this->assertStringNotContainsString('mysecret', $log);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Failure');
    }

    /**
     * Build an exception from a method that receives a sensitive argument
     *
     * @param string      $password Sensitive argument
     * @param ?\Throwable $previous Previous exception
     */
    private function failWith(string $password, ?\Throwable $previous = null): \RuntimeException
    {
        return new \RuntimeException('Failure', 0, $previous);
    }

    /**
     * Test expected cases are logged on a single line
     */
    public function testExceptionBelowWarning(): void
    {
        global $galette_log_var;

        $e = new \RuntimeException('Bad date', 0, new \LogicException('Root cause'));
        \Galette\Core\Logs::exception($e, 'Parsing date', \Analog\Analog::INFO);
        $log = (string)$galette_log_var;
        $this->assertStringContainsString('INFO - Parsing date | Bad date (RuntimeException)', $log);
        $this->assertStringNotContainsString('Stack trace', $log);
        $this->assertStringNotContainsString('Root cause', $log);
        $this->expectLogEntry(\Analog\Analog::INFO, 'Parsing date | Bad date (RuntimeException)');

        //not marked as logged: details are written if it becomes an error
        \Galette\Core\Logs::exception($e, 'Storing');
        $this->assertStringContainsString('Stack trace', (string)$galette_log_var);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Storing | Bad date');
    }
}
