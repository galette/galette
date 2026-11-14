<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\Csp;
use Galette\Core\GalettePlugin;
use Galette\Core\Plugins;
use Galette\Core\Plugins\ContentSecurityPolicyProviderInterface;
use Galette\Core\Preferences;
use PHPUnit\Framework\Attributes\DataProvider;
use Galette\Tests\BaseGaletteTestCase;

/**
 * Content Security Policy tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CspTest extends BaseGaletteTestCase
{
    /** Sources maps add to connect-src */
    private const string MAPS_CONNECT = ' ' . Csp::MAPS_GEOCODER
        . ' https://tiles.openfreemap.org https://tiles.versatiles.org https://data.geopf.fr';

    /**
     * Get a policy for given preferences
     *
     * @param mixed               $frame_ancestors  Frame ancestors preference value, or a Throwable to throw
     * @param list<GalettePlugin> $providers        Plugins declaring sources
     * @param ?\Throwable         $plugins_error    Error thrown when getting plugins
     * @param mixed               $external_sources External sources preference value, or a Throwable to throw
     * @param mixed               $enforce          Enforcement preference value, or a Throwable to throw
     */
    private function csp(
        mixed $frame_ancestors = Csp::DEFAULT_FRAME_ANCESTORS,
        array $providers = [],
        ?\Throwable $plugins_error = null,
        mixed $external_sources = '',
        mixed $enforce = false
    ): Csp {
        $values = [
            'pref_csp_frame_ancestors' => $frame_ancestors,
            'pref_csp_external_sources' => $external_sources,
            'pref_csp_enforce' => $enforce,
        ];
        $preferences = $this->createStub(Preferences::class);
        $preferences->method('getConfigValue')->willReturnCallback(
            function (string $name) use ($values): mixed {
                if ($values[$name] instanceof \Throwable) {
                    throw $values[$name];
                }
                return $values[$name];
            }
        );

        $plugins = $this->createStub(Plugins::class);
        if ($plugins_error !== null) {
            $plugins->method('getProviders')->willThrowException($plugins_error);
        } else {
            $plugins->method('getProviders')->willReturnCallback(
                fn(string $interface): array => array_values(
                    array_filter($providers, fn(GalettePlugin $plugin): bool => $plugin instanceof $interface)
                )
            );
        }

        return new Csp($preferences, $plugins);
    }

    /**
     * Get a plugin declaring CSP sources
     *
     * @param array<mixed>|\Throwable $sources Declared sources, or a Throwable to throw
     */
    private function provider(array|\Throwable $sources): GalettePlugin
    {
        return new class ($sources) extends GalettePlugin implements ContentSecurityPolicyProviderInterface {
            /**
             * @param array<mixed>|\Throwable $sources Declared sources, or a Throwable to throw
             */
            public function __construct(private readonly array|\Throwable $sources)
            {
            }

            /**
             * Get the sources the plugin adds to the Content Security Policy
             *
             * @return array<string, list<string>>
             */
            public function getCspSources(): array
            {
                if ($this->sources instanceof \Throwable) {
                    throw $this->sources;
                }
                /** @var array<string, list<string>> wrong types on purpose */
                return $this->sources;
            }

            /**
             * Is plugin fully installed?
             */
            public function isInstalled(): bool
            {
                return true;
            }
        };
    }

    /**
     * Test sources declared by plugins
     */
    public function testPluginsSources(): void
    {
        $csp = $this->csp(providers: [
            $this->provider([
                'frame-src' => ['https://js.stripe.com', 'https://hooks.stripe.com'],
                'CONNECT-SRC' => ['https://api.stripe.com', 'wss://*.example.org:8443/socket'],
                'form-action' => ['https://www.helloasso.com/associations/'],
                'img-src' => ['data:'],
            ]),
            $this->provider(['frame-src' => ['https://js.stripe.com']]),
            //a plugin that does not declare anything is not asked
            new class extends GalettePlugin {
                /**
                 * Is plugin fully installed?
                 */
                public function isInstalled(): bool
                {
                    return true;
                }
            }
        ]);

        $policy = $csp->getFullPolicy();
        $this->assertStringContainsString("; frame-src 'self' https://js.stripe.com https://hooks.stripe.com", $policy);
        $this->assertStringContainsString(
            "connect-src 'self'" . self::MAPS_CONNECT . ' https://api.stripe.com wss://*.example.org:8443/socket;',
            $policy
        );
        $this->assertStringContainsString("form-action 'self' https://www.helloasso.com/associations/;", $policy);
        //already allowed, not repeated
        $this->assertStringContainsString("img-src 'self' data: blob: https:;", $policy);
        //asked once per request
        $this->assertSame($policy, $csp->getFullPolicy());

        //never enforced
        $this->assertStringNotContainsString('stripe', $csp->getEnforcedPolicy());
        $this->expectNoLogEntry();
    }

    /**
     * Plugin sources provider
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function pluginSourcesProvider(): array
    {
        return [
            'origin' => ['frame-src', 'https://js.stripe.com', true],
            'wildcard origin' => ['img-src', 'https://*.tile.openstreetmap.org', true],
            'port and path' => ['connect-src', 'https://example.org:8443/api/v1/', true],
            'websocket' => ['connect-src', 'wss://example.org', true],
            'data image' => ['img-src', 'data:', true],
            'blob worker' => ['worker-src', 'blob:', true],
            'script origin' => ['script-src', 'https://js.stripe.com', true],
            'data script' => ['script-src', 'data:', false],
            'blob script' => ['script-src', 'blob:', false],
            'http' => ['img-src', 'http://example.org', false],
            'scheme only' => ['img-src', 'https:', false],
            'wildcard' => ['img-src', '*', false],
            'keyword' => ['script-src', "'unsafe-inline'", false],
            'self' => ['img-src', "'self'", false],
            'javascript' => ['form-action', 'javascript:', false],
            'no scheme' => ['img-src', 'example.org', false],
            'query' => ['connect-src', 'https://example.org/api?x=1', false],
            'injection' => ['img-src', "https://example.org; script-src 'unsafe-inline'", false],
            'several' => ['img-src', 'https://a.example.org https://b.example.org', false],
            'header splitting' => ['img-src', "https://example.org\r\nX-Foo: bar", false],
            'trailing newline' => ['img-src', "https://example.org\n", false],
            'frame ancestors' => ['frame-ancestors', 'https://example.org', false],
            'object' => ['object-src', 'https://example.org', false],
            'base uri' => ['base-uri', 'https://example.org', false],
            'default' => ['default-src', 'https://example.org', false],
            'report' => ['report-uri', 'https://example.org', false],
            'unknown' => ['foo-src', 'https://example.org', false],
        ];
    }

    /**
     * Test which sources plugins may add
     *
     * @param string $directive Directive
     * @param string $source    Source
     * @param bool   $expected  Expected result
     */
    #[DataProvider('pluginSourcesProvider')]
    public function testIsPluginSource(string $directive, string $source, bool $expected): void
    {
        $this->assertSame($expected, Csp::isPluginSource($directive, $source));
    }

    /**
     * Test invalid sources from plugins are ignored, and logged
     */
    public function testInvalidPluginsSources(): void
    {
        $csp = $this->csp(providers: [
            $this->provider([
                'frame-ancestors' => ['https://evil.example.org'],
                'script-src' => ["'unsafe-inline'", 'https://js.stripe.com'],
                'img-src' => 'https://tile.example.org',
                'connect-src' => [42],
            ]),
            $this->provider(new \RuntimeException('Broken plugin')),
        ]);

        $policy = $csp->getFullPolicy();
        $this->assertStringContainsString(
            "script-src 'nonce-" . $csp->getNonce() . "' 'strict-dynamic' https://js.stripe.com;",
            $policy
        );
        $this->assertStringContainsString("img-src 'self' data: blob: https: https://tile.example.org;", $policy);
        $this->assertStringNotContainsString('evil', $policy);
        $this->assertStringContainsString("connect-src 'self'" . self::MAPS_CONNECT . ';', $policy);

        foreach (
            [
                ['https://evil.example.org', 'frame-ancestors'],
                ["'unsafe-inline'", 'script-src'],
                ['42', 'connect-src'],
            ] as [$source, $directive]
        ) {
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                sprintf('Invalid CSP source "%1$s" for directive "%2$s" from ', $source, $directive)
            );
        }
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Broken plugin');
    }

    /**
     * Test policy is still built when plugins cannot be listed
     */
    public function testPluginsError(): void
    {
        $csp = $this->csp(plugins_error: new \RuntimeException('Module "foo" has no plugin descriptor.'));
        $this->assertStringStartsWith("default-src 'self';", $csp->getFullPolicy());
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Cannot get plugins CSP sources | Module "foo" has no plugin descriptor.'
        );
    }

    /**
     * External sources provider
     *
     * @return array<string, array{string, ?string[]}>
     */
    public static function externalSourcesProvider(): array
    {
        return [
            'empty' => ['', []],
            'blank' => ['  ', []],
            'origins' => [
                ' https://stats.example.org  https://*.example.net:8443/matomo/ wss://live.example.org https://stats.example.org',
                ['https://stats.example.org', 'https://*.example.net:8443/matomo/', 'wss://live.example.org']
            ],
            'http' => ['http://stats.example.org', null],
            'keyword' => ["'unsafe-inline'", null],
            'self' => ["'self'", null],
            'wildcard' => ['*', null],
            'scheme only' => ['https:', null],
            'data' => ['data:', null],
            'no scheme' => ['stats.example.org', null],
            'query' => ['https://stats.example.org/?x=1', null],
            'one invalid' => ["https://stats.example.org 'unsafe-eval'", null],
            'directive injection' => ["https://stats.example.org; script-src 'unsafe-inline'", null],
            'header splitting' => ["https://stats.example.org\r\nX-Foo: bar", null],
        ];
    }

    /**
     * Test external sources parsing
     *
     * @param string    $value    Value to parse
     * @param ?string[] $expected Expected sources, null when invalid
     */
    #[DataProvider('externalSourcesProvider')]
    public function testParseExternalSources(string $value, ?array $expected): void
    {
        $this->assertSame($expected, Csp::parseExternalSources($value));
    }

    /**
     * Test external sources come from preferences, in the full policy only
     */
    public function testExternalSourcesFromPreferences(): void
    {
        $csp = $this->csp(external_sources: 'https://stats.example.org https://*.example.net');
        $policy = $csp->getFullPolicy();
        $sources = ' https://stats.example.org https://*.example.net';

        $this->assertStringContainsString(
            "script-src 'nonce-" . $csp->getNonce() . "' 'strict-dynamic'" . $sources . ';',
            $policy
        );
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'" . $sources . ';', $policy);
        $this->assertStringContainsString("font-src 'self' data:" . $sources . ';', $policy);
        $this->assertStringContainsString("connect-src 'self'" . self::MAPS_CONNECT . $sources . ';', $policy);
        $this->assertStringContainsString("frame-src 'self'" . $sources, $policy);
        //https images are already allowed, forms are not concerned
        $this->assertStringContainsString("img-src 'self' data: blob: https:;", $policy);
        $this->assertStringContainsString("form-action 'self';", $policy);
        //built twice, sources are not repeated
        $this->assertSame($policy, $csp->getFullPolicy());

        $this->assertStringNotContainsString('example', $csp->getEnforcedPolicy());
        $this->expectNoLogEntry();
    }

    /**
     * Test external sources are ignored when preference cannot be used
     */
    public function testExternalSourcesFallback(): void
    {
        foreach (["https://stats.example.org; script-src *", 42, null] as $value) {
            $csp = $this->csp(external_sources: $value);
            $this->assertSame([], $csp->getExternalSources());
            $this->assertStringNotContainsString('example', $csp->getFullPolicy());
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                sprintf('Invalid external sources "%1$s", ignored.', print_r($value, return: true))
            );
        }

        //a missing preference is expected before database update: nothing to report
        $this->assertSame(
            [],
            $this->csp(external_sources: new \RuntimeException('No such preference'))->getExternalSources()
        );
        $this->expectNoLogEntry();
    }

    /**
     * Get a policy for given maps tiles preferences
     *
     * @param array<string, mixed> $values Maps preferences values
     */
    private function mapsCsp(array $values): Csp
    {
        $preferences = $this->createStub(Preferences::class);
        $preferences->method('getConfigValue')->willReturnCallback(
            fn(string $name): mixed => $name === 'pref_csp_frame_ancestors' ? Csp::DEFAULT_FRAME_ANCESTORS : ''
        );
        $preferences->method('__get')->willReturnCallback(fn(string $name): mixed => $values[$name] ?? null);
        return new Csp($preferences, $this->createStub(Plugins::class));
    }

    /**
     * URLs origins provider
     *
     * @return array<string, array{string, ?string}>
     */
    public static function originsProvider(): array
    {
        return [
            'style' => ['https://tiles.openfreemap.org/styles/positron', 'https://tiles.openfreemap.org'],
            'port' => ['https://Tiles.Example.org:8443/{z}/{x}/{y}.png', 'https://tiles.example.org:8443'],
            'subdomains' => ['https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', 'https://*.tile.openstreetmap.fr'],
            'plain http' => ['http://tiles.example.org/{z}/{x}/{y}.png', 'http://tiles.example.org'],
            'host only' => ['https://tiles.example.org', 'https://tiles.example.org'],
            'no scheme' => ['tiles.example.org/{z}/{x}/{y}.png', null],
            'other scheme' => ['ftp://tiles.example.org/', null],
            'injection' => ["https://tiles.example.org; script-src *", null],
            'empty' => ['', null],
        ];
    }

    /**
     * Test origin of URLs
     *
     * @param string  $url      URL
     * @param ?string $expected Expected origin
     */
    #[DataProvider('originsProvider')]
    public function testGetOrigin(string $url, ?string $expected): void
    {
        $this->assertSame($expected, Csp::getOrigin($url));
    }

    /**
     * Test maps backgrounds and address search are allowed
     */
    public function testMapsSources(): void
    {
        $policy = $this->csp()->getFullPolicy();
        $this->assertStringContainsString("connect-src 'self'" . self::MAPS_CONNECT . ';', $policy);
        //raster backgrounds are images, already allowed
        $this->assertStringNotContainsString('openstreetmap.fr', $policy);
        $this->assertStringContainsString("img-src 'self' data: blob: https:;", $policy);

        //own vector background
        $policy = $this->mapsCsp([
            'pref_maps_tiles_provider' => 'custom',
            'pref_maps_tiles_vector' => true,
            'pref_maps_tiles_url' => 'https://maps.example.org/style.json',
        ])->getFullPolicy();
        $this->assertStringContainsString(' https://data.geopf.fr https://maps.example.org;', $policy);

        //own raster background, may be served over http
        $policy = $this->mapsCsp([
            'pref_maps_tiles_provider' => 'custom',
            'pref_maps_tiles_vector' => false,
            'pref_maps_tiles_url' => 'http://{s}.maps.example.org/{z}/{x}/{y}.png',
        ])->getFullPolicy();
        $this->assertStringContainsString("img-src 'self' data: blob: https: http://*.maps.example.org;", $policy);
        $this->expectNoLogEntry();
    }

    /**
     * Test an invalid own background is not allowed
     */
    public function testInvalidMapsSources(): void
    {
        $url = 'maps.example.org/style.json';
        $policy = $this->mapsCsp([
            'pref_maps_tiles_provider' => 'custom',
            'pref_maps_tiles_vector' => true,
            'pref_maps_tiles_url' => $url,
        ])->getFullPolicy();
        $this->assertStringNotContainsString('maps.example.org', $policy);
        $this->expectLogEntry(\Analog\Analog::WARNING, sprintf('Invalid maps tiles URL "%1$s", not allowed.', $url));
    }

    /**
     * Values to redact provider
     *
     * @return array<string, array{mixed, string}>
     */
    public static function redactProvider(): array
    {
        return [
            'keyword' => ["'unsafe-inline'", "'unsafe-inline'"],
            'origin' => ['https://stats.example.org', 'https://stats.example.org'],
            'query' => ['https://stats.example.org/api?token=SECRET#top', 'https://stats.example.org/api'],
            'credentials' => ['https://user:SECRET@stats.example.org/', 'https://stats.example.org/'],
            'token in path' => [
                'https://galette.example.org/password-recovery/' . str_repeat('0123456789abcdef', 4),
                'https://galette.example.org/password-recovery/…'
            ],
            'token then path' => [
                'https://galette.example.org/document/download/' . str_repeat('a1', 16) . '/file',
                'https://galette.example.org/document/download/…/file'
            ],
            'short segments kept' => ['https://galette.example.org/member/edit/42', 'https://galette.example.org/member/edit/42'],
            'several' => ["'self' https://a.example.org?k=SECRET https://b.example.org", "'self' https://a.example.org https://b.example.org"],
            'not a string' => [42, '42'],
        ];
    }

    /**
     * Test values are redacted before being logged
     *
     * @param mixed  $value    Value
     * @param string $expected Expected redacted value
     */
    #[DataProvider('redactProvider')]
    public function testRedact(mixed $value, string $expected): void
    {
        $this->assertSame($expected, Csp::redact($value));
    }

    /**
     * Test rejected preferences do not leak tokens to the logs
     */
    public function testRejectedValuesAreRedacted(): void
    {
        $this->assertSame([], $this->csp(external_sources: 'https://stats.example.org/api?token=SECRET')->getExternalSources());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Invalid external sources "https://stats.example.org/api", ignored.');
    }

    /**
     * Test nonce is stable for a request, and different for another one
     */
    public function testNonce(): void
    {
        $csp = $this->csp();
        $nonce = $csp->getNonce();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]{22}==$/', $nonce);
        $this->assertSame($nonce, $csp->getNonce());
        $this->assertNotSame($nonce, ($this->csp())->getNonce());
    }

    /**
     * Test enforced policy only holds directives that cannot break pages
     */
    public function testEnforcedPolicy(): void
    {
        $csp = $this->csp();
        $this->assertSame(
            "frame-ancestors 'self'; object-src 'none'; base-uri 'self'",
            $csp->getEnforcedPolicy()
        );

        //additional sources are not for the enforced policy
        $csp->addSource('img-src', 'https://tile.openstreetmap.org');
        $this->assertStringNotContainsString('openstreetmap', $csp->getEnforcedPolicy());
    }

    /**
     * Test policies sent, depending on enforcement
     */
    public function testPolicies(): void
    {
        $csp = $this->csp();
        $this->assertFalse($csp->isEnforced());
        $this->assertSame(
            [
                'Content-Security-Policy' => $csp->getEnforcedPolicy(),
                'Content-Security-Policy-Report-Only' => $csp->getFullPolicy(),
            ],
            $csp->getPolicies()
        );

        //value from database, or from a constant
        foreach ([true, '1', 1, 'true'] as $value) {
            $csp = $this->csp(enforce: $value);
            $this->assertTrue($csp->isEnforced(), var_export($value, return: true));
            $this->assertSame(['Content-Security-Policy' => $csp->getFullPolicy()], $csp->getPolicies());
        }
        foreach ([false, '0', 0, ''] as $value) {
            $this->assertFalse($this->csp(enforce: $value)->isEnforced(), var_export($value, return: true));
        }
        $this->expectNoLogEntry();
    }

    /**
     * Test policy is enforced when enforcement value is invalid, report-only when it cannot be read
     */
    public function testEnforceFallback(): void
    {
        foreach (['yes please', [false]] as $value) {
            $this->assertTrue($this->csp(enforce: $value)->isEnforced());
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                sprintf('Invalid CSP enforcement "%1$s", policy is enforced.', print_r($value, return: true))
            );
        }

        //a missing preference is expected before database update: nothing to report
        $this->assertFalse($this->csp(enforce: new \RuntimeException('No such preference'))->isEnforced());
        $this->expectNoLogEntry();
    }

    /**
     * Test complete policy
     */
    public function testFullPolicy(): void
    {
        $csp = $this->csp();
        $policy = $csp->getFullPolicy();

        $this->assertStringContainsString(
            "script-src 'nonce-" . $csp->getNonce() . "' 'strict-dynamic';",
            $policy
        );
        $this->assertStringContainsString("default-src 'self';", $policy);
        $this->assertStringContainsString("img-src 'self' data: blob: https:;", $policy);
        $this->assertStringContainsString("object-src 'none';", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
    }

    /**
     * Test additional sources
     */
    public function testAddSource(): void
    {
        $csp = $this->csp();
        $csp
            ->addSource('connect-src', 'https://api.example.org')
            ->addSource('CONNECT-SRC', 'https://api.example.org')
            ->addSource('frame-src', 'https://js.stripe.com');

        $policy = $csp->getFullPolicy();
        $this->assertStringContainsString("connect-src 'self' https://api.example.org ", $policy);
        $this->assertSame(1, substr_count($policy, 'api.example.org'));
        //undeclared directive falls back on default-src, rather than allowing only the new source
        $this->assertStringContainsString("frame-src 'self' https://js.stripe.com", $policy);
    }

    /**
     * Invalid sources provider
     *
     * @return array<string, array{string, string}>
     */
    public static function invalidSourcesProvider(): array
    {
        return [
            'empty directive' => ['', 'https://example.com'],
            'empty source' => ['img-src', ' '],
            'directive injection' => ['img-src', "https://example.com; script-src 'unsafe-inline'"],
            'several sources' => ['img-src', 'https://a.example.com https://b.example.com'],
            'header splitting' => ['img-src', "https://example.com\r\nX-Foo: bar"],
        ];
    }

    /**
     * Test invalid sources are rejected
     *
     * @param string $directive Directive
     * @param string $source    Source
     */
    #[DataProvider('invalidSourcesProvider')]
    public function testAddInvalidSource(string $directive, string $source): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ($this->csp())->addSource($directive, $source);
    }

    /**
     * Frame ancestors provider
     *
     * @return array<string, array{string, ?string[]}>
     */
    public static function frameAncestorsProvider(): array
    {
        return [
            'self' => ["'self'", ["'self'"]],
            'none' => ["'NONE'", ["'none'"]],
            'origins' => [
                "  'self'   https://Asso.example.org/ http://localhost:8080 https://*.example.org 'self' ",
                ["'self'", 'https://asso.example.org', 'http://localhost:8080', 'https://*.example.org']
            ],
            'empty' => ['', null],
            'blank' => ['  ', null],
            'none with others' => ["'none' https://example.org", null],
            'unquoted keyword' => ['self', null],
            'wildcard' => ['*', null],
            'scheme only' => ['https:', null],
            'no scheme' => ['example.org', null],
            'path' => ['https://example.org/galette', null],
            'directive injection' => ["https://example.org; script-src 'unsafe-inline'", null],
            'header splitting' => ["https://example.org\r\nX-Foo: bar", null],
            'other keyword' => ["'unsafe-inline'", null],
            'javascript' => ['javascript:alert(1)', null],
        ];
    }

    /**
     * Test frame ancestors parsing
     *
     * @param string    $value    Value to parse
     * @param ?string[] $expected Expected sources, null when invalid
     */
    #[DataProvider('frameAncestorsProvider')]
    public function testParseFrameAncestors(string $value, ?array $expected): void
    {
        $this->assertSame($expected, Csp::parseFrameAncestors($value));
    }

    /**
     * Test frame ancestors come from preferences, in both policies
     */
    public function testFrameAncestorsFromPreferences(): void
    {
        $csp = $this->csp("'self' https://asso.example.org");
        $expected = "frame-ancestors 'self' https://asso.example.org;";

        $this->assertStringStartsWith($expected, $csp->getEnforcedPolicy());
        $this->assertStringContainsString($expected, $csp->getFullPolicy());
    }

    /**
     * Test default frame ancestors when preference cannot be used
     *
     * A constant from behavior.inc.php is not validated when set, and the
     * preference does not exist before the database is updated.
     */
    public function testFrameAncestorsFallback(): void
    {
        foreach (["https://example.org; script-src *", 42, null] as $value) {
            $this->assertSame(["'self'"], $this->csp($value)->getFrameAncestors());
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                sprintf('Invalid frame ancestors "%1$s", falling back to default.', print_r($value, return: true))
            );
        }

        //a missing preference is expected before database update: nothing to report
        $this->assertSame(
            ["'self'"],
            $this->csp(new \RuntimeException('No such preference'))->getFrameAncestors()
        );
        $this->expectNoLogEntry();
    }

    /**
     * Test violation reports
     */
    public function testReportUri(): void
    {
        $csp = $this->csp();
        $this->assertNull($csp->getReportingEndpoints());
        $this->assertStringNotContainsString('report-', $csp->getFullPolicy());

        $csp->setReportUri('/galette/csp-report');
        $this->assertSame('csp-endpoint="/galette/csp-report"', $csp->getReportingEndpoints());
        foreach ([$csp->getEnforcedPolicy(), $csp->getFullPolicy()] as $policy) {
            $this->assertStringEndsWith('; report-uri /galette/csp-report; report-to csp-endpoint', $policy);
        }
    }

    /**
     * Test invalid report URIs are rejected
     */
    public function testInvalidReportUri(): void
    {
        foreach (['', '/csp-report; script-src *', '/csp report', '/csp-report"'] as $uri) {
            try {
                $this->csp()->setReportUri($uri);
                $this->fail('Report URI "' . $uri . '" has been accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
