<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug;

use DebugBar\HttpDriverInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Debug bar HTTP driver that writes to a PSR-7 response
 *
 * Headers and output are buffered, then applied to the response by the
 * middleware; session values go to the native session RKA already started.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Psr7HttpDriver implements HttpDriverInterface
{
    /** @var array<string, string> */
    private array $headers = [];
    private string $output = '';

    /**
     * Set HTTP headers
     *
     * @param array<string, string> $headers Headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = array_merge($this->headers, $headers);
    }

    /**
     * Output content
     *
     * @param string $content Content
     */
    public function output(string $content): void
    {
        $this->output .= $content;
    }

    /**
     * Is session started
     */
    public function isSessionStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Set session value
     *
     * @param string $name  Name
     * @param mixed  $value Value
     */
    public function setSessionValue(string $name, mixed $value): void
    {
        $_SESSION[$name] = $value;
    }

    /**
     * Has session value
     *
     * @param string $name Name
     */
    public function hasSessionValue(string $name): bool
    {
        return isset($_SESSION) && array_key_exists($name, $_SESSION);
    }

    /**
     * Get session value
     *
     * @param string $name Name
     */
    public function getSessionValue(string $name): mixed
    {
        return $_SESSION[$name] ?? null;
    }

    /**
     * Delete session value
     *
     * @param string $name Name
     */
    public function deleteSessionValue(string $name): void
    {
        unset($_SESSION[$name]);
    }

    /**
     * Apply buffered headers and output to a response, then reset buffers
     *
     * @param ResponseInterface $response Response
     */
    public function apply(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        if ($this->output !== '') {
            $response->getBody()->write($this->output);
        }
        $this->headers = [];
        $this->output = '';
        return $response;
    }
}
