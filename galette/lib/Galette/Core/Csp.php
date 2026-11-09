<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Throwable;

use function Safe\preg_match;
use function Safe\preg_split;

/**
 * Content Security Policy for the current request
 *
 * Holds the per-request nonce that inline and external scripts must carry,
 * and builds the policies sent by the SecurityHeaders middleware.
 *
 * Two policies are sent:
 * - an enforced one, limited to directives that cannot break pages
 *   (framing, plugins, base URI); sites allowed to frame Galette come from
 *   the pref_csp_frame_ancestors preference;
 * - a report-only one, with the complete policy, so violations show up in
 *   browsers consoles without blocking anything.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Csp
{
    public const string DEFAULT_FRAME_ANCESTORS = "'self'";

    private const array ENFORCED = [
        'frame-ancestors' => ["'self'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
    ];

    private const array FULL = [
        'default-src' => ["'self'"],
        'script-src' => ['%nonce%', "'strict-dynamic'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        //images do not run anything: any https one is allowed, for stored
        //contents (mailings, texts) that point to images hosted elsewhere
        'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
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

    /** @var string[]|null */
    private ?array $frame_ancestors = null;

    /**
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(private readonly Preferences $preferences)
    {
    }

    /**
     * Get the nonce of current request
     */
    public function getNonce(): string
    {
        $this->nonce ??= base64_encode(random_bytes(16));
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
        if (!in_array($source, $this->extra_sources[$directive] ?? [], strict: true)) {
            $this->extra_sources[$directive][] = $source;
        }
        return $this;
    }

    /**
     * Parse a list of sites allowed to display Galette in a frame
     *
     * Accepts 'self', 'none' (alone), and http(s) origins, with an optional
     * wildcard on the first label: https://*.example.org
     *
     * @param string $value Space separated sources
     *
     * @return string[]|null Normalized sources, null when value is invalid
     */
    public static function parseFrameAncestors(string $value): ?array
    {
        $tokens = preg_split(pattern: '/\s+/', subject: trim($value), limit: -1, flags: PREG_SPLIT_NO_EMPTY);
        if ($tokens === []) {
            return null;
        }

        $sources = [];
        foreach ($tokens as $token) {
            $keyword = strtolower($token);
            if ($keyword === "'self'" || $keyword === "'none'") {
                $sources[] = $keyword;
                continue;
            }

            $label = '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?';
            if (!preg_match('~^https?://(?:\*\.)?' . $label . '(?:\.' . $label . ')*(?::[0-9]{1,5})?/?$~i', $token)) {
                return null;
            }
            $sources[] = rtrim(strtolower($token), '/');
        }

        $sources = array_values(array_unique($sources));
        if (in_array("'none'", $sources, strict: true) && count($sources) > 1) {
            //'none' along with other sources is ignored by browsers
            return null;
        }

        return $sources;
    }

    /**
     * Get sites allowed to display Galette in a frame
     *
     * @return string[]
     */
    public function getFrameAncestors(): array
    {
        if ($this->frame_ancestors !== null) {
            return $this->frame_ancestors;
        }

        $default = [self::DEFAULT_FRAME_ANCESTORS];
        try {
            $value = $this->preferences->getConfigValue('pref_csp_frame_ancestors');
        } catch (Throwable) {
            // no log: preference may not exist yet, when database needs an update
            return $this->frame_ancestors = $default;
        }

        //checked again here: the value may come from a constant, which is never validated
        $sources = is_string($value) ? self::parseFrameAncestors($value) : null;
        if ($sources === null) {
            Analog::log(
                sprintf('Invalid frame ancestors "%1$s", falling back to default.', print_r($value, return: true)),
                Analog::WARNING
            );
            $sources = $default;
        }

        return $this->frame_ancestors = $sources;
    }

    /**
     * Get enforced policy
     */
    public function getEnforcedPolicy(): string
    {
        $directives = self::ENFORCED;
        $directives['frame-ancestors'] = $this->getFrameAncestors();
        return $this->build($directives);
    }

    /**
     * Get complete policy
     */
    public function getFullPolicy(): string
    {
        $directives = self::FULL;
        $directives['frame-ancestors'] = $this->getFrameAncestors();
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
