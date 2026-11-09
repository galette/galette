<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\Csp;
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
     * Test nonce is stable for a request, and different for another one
     */
    public function testNonce(): void
    {
        $csp = new Csp();
        $nonce = $csp->getNonce();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]{22}==$/', $nonce);
        $this->assertSame($nonce, $csp->getNonce());
        $this->assertNotSame($nonce, (new Csp())->getNonce());
    }

    /**
     * Test enforced policy only holds directives that cannot break pages
     */
    public function testEnforcedPolicy(): void
    {
        $csp = new Csp();
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
        $csp = new Csp();
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
        $csp = new Csp();
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
        (new Csp())->addSource($directive, $source);
    }
}
