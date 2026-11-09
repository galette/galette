<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\Csp;
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
    /**
     * Get a policy for given frame ancestors preference
     *
     * @param mixed $frame_ancestors Preference value, or a Throwable to throw
     */
    private function csp(mixed $frame_ancestors = Csp::DEFAULT_FRAME_ANCESTORS): Csp
    {
        $preferences = $this->createStub(Preferences::class);
        if ($frame_ancestors instanceof \Throwable) {
            $preferences->method('getConfigValue')->willThrowException($frame_ancestors);
        } else {
            $preferences->method('getConfigValue')->willReturn($frame_ancestors);
        }
        return new Csp($preferences);
    }

    /**
     * Get a policy for given maps tiles preferences
     *
     * @param array<string, mixed> $values Maps preferences values
     */
    private function mapsCsp(array $values): Csp
    {
        $preferences = $this->createStub(Preferences::class);
        $preferences->method('getConfigValue')->willReturn(Csp::DEFAULT_FRAME_ANCESTORS);
        $preferences->method('__get')->willReturnCallback(fn(string $name): mixed => $values[$name] ?? null);
        return new Csp($preferences);
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
        $this->assertStringContainsString(
            "connect-src 'self' " . Csp::MAPS_GEOCODER
                . ' https://tiles.openfreemap.org https://tiles.versatiles.org https://data.geopf.fr;',
            $policy
        );
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
