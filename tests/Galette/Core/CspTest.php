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
use PHPUnit\Framework\TestCase;

/**
 * Content Security Policy tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CspTest extends TestCase
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
            ->addSource('img-src', 'https://tile.openstreetmap.org')
            ->addSource('IMG-SRC', 'https://tile.openstreetmap.org')
            ->addSource('frame-src', 'https://js.stripe.com');

        $policy = $csp->getFullPolicy();
        $this->assertStringContainsString(
            "img-src 'self' data: blob: https://tile.openstreetmap.org;",
            $policy
        );
        $this->assertSame(1, substr_count($policy, 'openstreetmap'));
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
        foreach (["https://example.org; script-src *", 42, null, new \RuntimeException('No such preference')] as $value) {
            $this->assertSame(["'self'"], $this->csp($value)->getFrameAncestors());
        }
    }
}
