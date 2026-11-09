<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use function Safe\preg_match;

/**
 * Content Security Policy for the current request
 *
 * Holds the per-request nonce that inline and external scripts must carry,
 * and builds the policies sent by the SecurityHeaders middleware.
 *
 * Two policies are sent:
 * - an enforced one, limited to directives that cannot break pages
 *   (framing, plugins, base URI);
 * - a report-only one, with the complete policy, so violations show up in
 *   browsers consoles without blocking anything.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Csp
{
    private const array ENFORCED = [
        'frame-ancestors' => ["'self'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
    ];

    private const array FULL = [
        'default-src' => ["'self'"],
        'script-src' => ['%nonce%', "'strict-dynamic'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'blob:'],
        'font-src' => ["'self'", 'data:'],
        'connect-src' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'self'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
    ];

    private ?string $nonce = null;

    /** @var array<string, string[]> */
    private array $extra_sources = [];

    /**
     * Get the nonce of current request
     */
    public function getNonce(): string
    {
        if ($this->nonce === null) {
            $this->nonce = base64_encode(random_bytes(16));
        }
        return $this->nonce;
    }

    /**
     * Allow an additional source for a directive of the full policy
     *
     * @param string $directive Directive name (img-src, form-action, ...)
     * @param string $source    Source expression (https://example.com, ...)
     */
    public function addSource(string $directive, string $source): self
    {
        $directive = strtolower(trim($directive));
        $source = trim($source);
        if ($directive === '' || $source === '' || preg_match('/[;,\s]/', $directive . $source)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid CSP source "%1$s" for directive "%2$s"', $source, $directive)
            );
        }
        if (!in_array($source, $this->extra_sources[$directive] ?? [], true)) {
            $this->extra_sources[$directive][] = $source;
        }
        return $this;
    }

    /**
     * Get enforced policy
     */
    public function getEnforcedPolicy(): string
    {
        return $this->build(self::ENFORCED);
    }

    /**
     * Get complete policy
     */
    public function getFullPolicy(): string
    {
        $directives = self::FULL;
        foreach ($this->extra_sources as $directive => $sources) {
            //a directive that is not declared falls back to default-src
            $directives[$directive] = array_merge($directives[$directive] ?? self::FULL['default-src'], $sources);
        }
        return $this->build($directives);
    }

    /**
     * Build a policy string
     *
     * @param array<string, string[]> $directives Directives and their sources
     */
    private function build(array $directives): string
    {
        $parts = [];
        foreach ($directives as $directive => $sources) {
            $sources = array_map(
                fn(string $source): string => $source === '%nonce%' ? "'nonce-" . $this->getNonce() . "'" : $source,
                $sources
            );
            $parts[] = $directive . ' ' . implode(' ', array_unique($sources));
        }
        return implode('; ', $parts);
    }
}
