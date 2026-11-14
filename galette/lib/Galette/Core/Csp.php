<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Galette\Maps\TileProviders;
use Throwable;

use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\preg_replace_callback;
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
 *   browsers consoles without blocking anything; external sites come from
 *   plugins and from the pref_csp_external_sources preference; servers of maps
 *   backgrounds and address search are allowed as well.
 *
 * Once the pref_csp_enforce preference is on, the complete policy alone is
 * sent, and enforced.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Csp
{
    public const string DEFAULT_FRAME_ANCESTORS = "'self'";

    /** Reporting API endpoint name */
    public const string REPORT_GROUP = 'csp-endpoint';

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

    /** Address search of maps, run from browsers */
    public const string MAPS_GEOCODER = 'https://nominatim.openstreetmap.org';

    private bool $maps_sources = false;

    /** Directives plugins may add sources to */
    public const array PLUGIN_DIRECTIVES = [
        'connect-src',
        'font-src',
        'form-action',
        'frame-src',
        'img-src',
        'media-src',
        'script-src',
        'style-src',
        'worker-src',
    ];

    private const string HOST_LABEL = '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?';

    /** Directives external sites set by administrators are added to */
    public const array EXTERNAL_DIRECTIVES = [
        'script-src',
        'style-src',
        'font-src',
        'connect-src',
        'frame-src',
    ];

    private ?string $report_uri = null;

    /** @var string[]|null */
    private ?array $external_sources = null;

    private bool $plugins_sources = false;

    private ?bool $enforced = null;

    /**
     * @param Preferences $preferences Preferences instance
     * @param Plugins     $plugins     Plugins instance
     */
    public function __construct(
        private readonly Preferences $preferences,
        private readonly Plugins $plugins
    ) {
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
     * Set where browsers send violation reports
     *
     * @param string $uri Report URI, relative to the instance
     */
    public function setReportUri(string $uri): self
    {
        if ($uri === '' || preg_match('/[;,\s"]/', $uri)) {
            throw new \InvalidArgumentException(sprintf('Invalid CSP report URI "%1$s"', $uri));
        }
        $this->report_uri = $uri;
        return $this;
    }

    /**
     * Get the Reporting-Endpoints header value, if reports are expected
     */
    public function getReportingEndpoints(): ?string
    {
        return $this->report_uri === null ? null : sprintf('%1$s="%2$s"', self::REPORT_GROUP, $this->report_uri);
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

            $label = self::HOST_LABEL;
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
     * Is a source one a plugin may add?
     *
     * Plugins only add origins they talk to: no keyword, no wildcard, and no
     * directive that would weaken the enforced policy.
     *
     * @param string $directive Directive name
     * @param string $source    Source expression
     */
    public static function isPluginSource(string $directive, string $source): bool
    {
        if (!in_array($directive, self::PLUGIN_DIRECTIVES, strict: true)) {
            return false;
        }

        if ($source === 'data:' || $source === 'blob:') {
            //would let any injected content run as a script
            return $directive !== 'script-src';
        }

        return self::isOrigin($source);
    }

    /**
     * Is a source an https:// or wss:// origin?
     *
     * An optional wildcard on the first label and an optional path are accepted.
     *
     * @param string $source Source expression
     */
    private static function isOrigin(string $source): bool
    {
        $label = self::HOST_LABEL;
        return preg_match(
            '~^(?:https|wss)://(?:\*\.)?' . $label . '(?:\.' . $label . ')*(?::[0-9]{1,5})?(?:/[a-z0-9._\~%/-]*)?$~iD',
            $source
        ) === 1;
    }

    /**
     * Parse a list of external sites pages may use
     *
     * @param string $value Space separated https:// or wss:// origins; may be empty
     *
     * @return string[]|null Sources, null when value is invalid
     */
    public static function parseExternalSources(string $value): ?array
    {
        $sources = preg_split(pattern: '/\s+/', subject: trim($value), limit: -1, flags: PREG_SPLIT_NO_EMPTY);
        foreach ($sources as $source) {
            if (!self::isOrigin($source)) {
                return null;
            }
        }
        return array_values(array_unique($sources));
    }

    /**
     * Get external sites pages may use
     *
     * Set by administrators, for the tracking script of the theme or any
     * customization loading resources from elsewhere.
     *
     * @return string[]
     */
    public function getExternalSources(): array
    {
        if ($this->external_sources !== null) {
            return $this->external_sources;
        }

        try {
            $value = $this->preferences->getConfigValue('pref_csp_external_sources');
        } catch (Throwable) {
            // no log: preference may not exist yet, when database needs an update
            return $this->external_sources = [];
        }

        //checked again here: the value may come from a constant, which is never validated
        $sources = is_string($value) ? self::parseExternalSources($value) : null;
        if ($sources === null) {
            Analog::log(
                sprintf('Invalid external sources "%1$s", ignored.', self::redact($value)),
                Analog::WARNING
            );
            $sources = [];
        }

        return $this->external_sources = $sources;
    }

    /**
     * Add sources declared by plugins
     *
     * Done once, when the full policy is first built: plugins are loaded by then.
     */
    private function addPluginsSources(): void
    {
        if ($this->plugins_sources) {
            return;
        }
        $this->plugins_sources = true;

        try {
            $providers = $this->plugins->getProviders(Plugins\ContentSecurityPolicyProviderInterface::class);
        } catch (Throwable $e) {
            Logs::exception($e, 'Cannot get plugins CSP sources');
            return;
        }

        foreach ($providers as $plugin) {
            try {
                $this->addDeclaredSources($plugin->getCspSources(), $plugin::class);
            } catch (Throwable $e) {
                Logs::exception($e, sprintf('Cannot get CSP sources of %1$s', $plugin::class));
            }
        }
    }

    /**
     * Add sources declared by a plugin
     *
     * Plugin code is not trusted to respect the declared types.
     *
     * @param array<mixed> $declared Sources, indexed by directive
     * @param string       $plugin   Plugin class, for logs
     */
    private function addDeclaredSources(array $declared, string $plugin): void
    {
        foreach ($declared as $directive => $sources) {
            $directive = strtolower((string)$directive);
            foreach (is_array($sources) ? $sources : [$sources] as $source) {
                if (!is_string($source) || !self::isPluginSource($directive, $source)) {
                    Analog::log(
                        sprintf(
                            'Invalid CSP source "%1$s" for directive "%2$s" from %3$s, ignored.',
                            self::redact($source),
                            $directive,
                            $plugin
                        ),
                        Analog::WARNING
                    );
                    continue;
                }
                $this->addSource($directive, $source);
            }
        }
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
                sprintf('Invalid frame ancestors "%1$s", falling back to default.', self::redact($value)),
                Analog::WARNING
            );
            $sources = $default;
        }

        return $this->frame_ancestors = $sources;
    }

    /**
     * Is the complete policy enforced?
     */
    public function isEnforced(): bool
    {
        if ($this->enforced !== null) {
            return $this->enforced;
        }

        try {
            $value = $this->preferences->getConfigValue('pref_csp_enforce');
        } catch (Throwable) {
            // no log: preference may not exist yet, when database needs an update
            return $this->enforced = false;
        }

        //checked here: the value may come from a constant, which is never validated
        $enforced = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enforced === null) {
            Analog::log(
                sprintf('Invalid CSP enforcement "%1$s", policy is report-only.', self::redact($value)),
                Analog::WARNING
            );
            $enforced = false;
        }

        return $this->enforced = $enforced;
    }

    /**
     * Get policies to send, by header name
     *
     * @return array<string, string>
     */
    public function getPolicies(): array
    {
        if ($this->isEnforced()) {
            //complete policy includes the directives of the minimal one
            return ['Content-Security-Policy' => $this->getFullPolicy()];
        }

        return [
            'Content-Security-Policy' => $this->getEnforcedPolicy(),
            'Content-Security-Policy-Report-Only' => $this->getFullPolicy(),
        ];
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
        $this->addMapsSources();
        $this->addPluginsSources();
        foreach ($this->getExternalSources() as $source) {
            foreach (self::EXTERNAL_DIRECTIVES as $directive) {
                $this->addSource($directive, $source);
            }
        }
        $directives = self::FULL;
        $directives['frame-ancestors'] = $this->getFrameAncestors();
        foreach ($this->extra_sources as $directive => $sources) {
            //a directive that is not declared falls back to default-src
            $directives[$directive] = array_merge($directives[$directive] ?? self::FULL['default-src'], $sources);
        }
        return $this->build($directives);
    }

    /**
     * Allow what maps load from browsers
     *
     * Raster backgrounds are images, already allowed; vector ones are fetched
     * from the server of their style, which serves tiles and fonts as well.
     * Every vector preset is allowed: maps settings preview them all.
     */
    private function addMapsSources(): void
    {
        if ($this->maps_sources) {
            return;
        }
        $this->maps_sources = true;

        $this->addSource('connect-src', self::MAPS_GEOCODER);
        foreach (TileProviders::getPresets() as $preset) {
            $origin = $preset['vector'] ? self::getOrigin($preset['url']) : null;
            if ($origin !== null) {
                $this->addSource('connect-src', $origin);
            }
        }

        try {
            $tiles = TileProviders::resolve($this->preferences);
        } catch (Throwable) {
            // no log: preferences may not exist yet, when database needs an update
            return;
        }
        if ($tiles['id'] !== TileProviders::CUSTOM) {
            return;
        }

        $origin = self::getOrigin((string)$tiles['url']);
        if ($origin === null) {
            Analog::log(
                sprintf('Invalid maps tiles URL "%1$s", not allowed.', self::redact($tiles['url'])),
                Analog::WARNING
            );
            return;
        }
        $this->addSource($tiles['vector'] ? 'connect-src' : 'img-src', $origin);
    }

    /**
     * Make a value safe to log
     *
     * URLs may carry credentials or tokens: user information, query strings
     * and fragments are dropped, path segments looking like a token are
     * masked, as the hash of a password recovery link.
     *
     * @param mixed $value Value, a string may hold several URLs
     */
    public static function redact(mixed $value): string
    {
        if (!is_string($value)) {
            return print_r($value, return: true);
        }

        return preg_replace_callback(
            '/\S+/',
            fn(array $url): string => preg_replace(
                ['~^([a-z][a-z0-9+.-]*://)[^/@?#]*@~i', '/[?#].*$/s', '~(?<=/)[A-Za-z0-9_=+-]{20,}(?=/|$)~'],
                ['$1', '', '…'],
                $url[0]
            ),
            $value
        );
    }

    /**
     * Get the origin of an URL
     *
     * The subdomains placeholder of tiles URLs, {s}, becomes a wildcard.
     *
     * @param string $url URL
     */
    public static function getOrigin(string $url): ?string
    {
        $matches = [];
        if (
            preg_match(
                '~^(https?)://(\{s\}\.)?([a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(:[0-9]{1,5})?(?:[/?#]|$)~iD',
                $url,
                $matches
            ) !== 1
        ) {
            return null;
        }

        return strtolower($matches[1]) . '://' . ($matches[2] !== '' ? '*.' : '')
            . strtolower($matches[3]) . ($matches[4] ?? '');
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
        if ($this->report_uri !== null) {
            //report-uri for browsers that do not know about report-to (Firefox)
            $parts[] = 'report-uri ' . $this->report_uri;
            $parts[] = 'report-to ' . self::REPORT_GROUP;
        }
        return implode('; ', $parts);
    }
}
