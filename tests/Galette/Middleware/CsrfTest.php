<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Middleware;

use Analog\Analog;
use Galette\Middleware\Csrf;
use Galette\Tests\BaseGaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Exception\HttpForbiddenException;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

use function Safe\file_get_contents;

/**
 * CSRF protection middleware tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CsrfTest extends BaseGaletteTestCase
{
    private const string HOST = 'galette.example.org';

    /**
     * Build a request to run the middleware against
     *
     * @param string                $method     HTTP method
     * @param array<string, string> $headers    Headers to add to the request
     * @param ?string               $route_name Name of the resolved route, if any
     * @param string                $host       Authority Galette is reached at
     */
    private function createRequest(
        string $method = 'POST',
        array $headers = [],
        ?string $route_name = null,
        string $host = self::HOST
    ): Request {
        //the `Host` header is set by Slim from the URI; it cannot be provided here
        $request = new Request(
            method: $method,
            uri: (new UriFactory())->createUri('https://' . $host . '/member/edit/1'),
            headers: new Headers(array_map(fn(string $value): array => [$value], $headers)),
            cookies: [],
            serverParams: [],
            body: (new StreamFactory())->createStream()
        );

        if ($route_name !== null) {
            $route = $this->createStub(RouteInterface::class);
            $route->method('getName')->willReturn($route_name);
            $request = $request->withAttribute(RouteContext::ROUTE, $route);
        }

        return $request;
    }

    /**
     * Get a request handler that flags requests it has handled
     */
    private function getHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            /**
             * Handle the request
             *
             * @param ServerRequestInterface $request PSR7 request
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withHeader('X-Galette-Handled', 'true');
            }
        };
    }

    /**
     * Assert the request went through the middleware
     *
     * @param string[] $exclusions Route names patterns excluded from CSRF checks
     */
    private function assertRequestAccepted(Request $request, array $exclusions = []): void
    {
        $middleware = new Csrf($exclusions);
        $response = $middleware($request, $this->getHandler());
        $this->assertSame(
            ['true'],
            $response->getHeader('X-Galette-Handled'),
            'Request should have been handled.'
        );
    }

    /**
     * Assert the request has been refused by the middleware
     *
     * @param string[] $exclusions Route names patterns excluded from CSRF checks
     */
    private function assertRequestRefused(Request $request, array $exclusions = []): void
    {
        $middleware = new Csrf($exclusions);

        try {
            $middleware($request, $this->getHandler());
            $this->fail('Request should have been refused.');
        } catch (HttpForbiddenException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertSame('Failed CSRF check!', $e->getMessage());
        }

        $this->expectLogEntry(Analog::CRITICAL, 'CSRF check has failed for route');
    }

    /**
     * Bodyless methods are not checked at all
     *
     * @return array<string, array<int, string>>
     */
    public static function bodylessMethodsProvider(): array
    {
        return [
            'get' => ['GET'],
            'head' => ['HEAD'],
            'options' => ['OPTIONS'],
            'trace' => ['TRACE'],
        ];
    }

    /**
     * Test methods that are not supposed to change anything are never refused
     */
    #[DataProvider('bodylessMethodsProvider')]
    public function testBodylessMethodsAreNotChecked(string $method): void
    {
        $this->assertRequestAccepted(
            $this->createRequest(
                method: $method,
                headers: [
                    'Sec-Fetch-Site' => 'cross-site',
                    'Origin' => 'https://evil.example.com'
                ]
            )
        );
    }

    /**
     * Sec-Fetch-Site values, and whether they can be trusted
     *
     * @return array<string, array<int, string|bool>>
     */
    public static function fetchSiteProvider(): array
    {
        return [
            //request comes from Galette itself
            'same-origin' => ['same-origin', true],
            //request comes from a user action (bookmark, typed URL, ...)
            'none' => ['none', true],
            //request comes from another subdomain
            'same-site' => ['same-site', false],
            //request comes from an external site
            'cross-site' => ['cross-site', false],
            //should not happen; refused to be safe
            'unknown' => ['whatever', false],
        ];
    }

    /**
     * Test requests are accepted or refused from their Sec-Fetch-Site header
     */
    #[DataProvider('fetchSiteProvider')]
    public function testSecFetchSite(string $fetch_site, bool $expected): void
    {
        $request = $this->createRequest(headers: ['Sec-Fetch-Site' => $fetch_site]);

        if ($expected) {
            $this->assertRequestAccepted($request);
        } else {
            $this->assertRequestRefused($request);
        }
    }

    /**
     * Test Sec-Fetch-Site takes precedence over Origin
     */
    public function testSecFetchSiteTakesPrecedence(): void
    {
        //a matching Origin cannot save a cross-site request
        $this->assertRequestRefused(
            $this->createRequest(
                headers: [
                    'Sec-Fetch-Site' => 'cross-site',
                    'Origin' => 'https://' . self::HOST
                ]
            )
        );

        //nor can a foreign Origin discard a same-origin one
        $this->assertRequestAccepted(
            $this->createRequest(
                headers: [
                    'Sec-Fetch-Site' => 'same-origin',
                    'Origin' => 'https://evil.example.com'
                ]
            )
        );
    }

    /**
     * Mutating methods are all checked the same way
     *
     * @return array<string, array<int, string>>
     */
    public static function mutatingMethodsProvider(): array
    {
        return [
            'post' => ['POST'],
            'put' => ['PUT'],
            'patch' => ['PATCH'],
            'delete' => ['DELETE'],
        ];
    }

    /**
     * Test all methods that may change something are checked
     */
    #[DataProvider('mutatingMethodsProvider')]
    public function testMutatingMethodsAreChecked(string $method): void
    {
        $this->assertRequestRefused(
            $this->createRequest(
                method: $method,
                headers: ['Sec-Fetch-Site' => 'cross-site']
            )
        );

        $this->assertRequestAccepted(
            $this->createRequest(
                method: $method,
                headers: ['Sec-Fetch-Site' => 'same-origin']
            )
        );
    }

    /**
     * Origin fallback, for browsers that do not send Sec-Fetch-Site
     *
     * @return array<string, array<int, string|bool>>
     */
    public static function originProvider(): array
    {
        return [
            'same host' => ['https://' . self::HOST, true],
            'same host, other scheme' => ['http://' . self::HOST, true],
            'same host, different case' => ['https://GALETTE.example.ORG', true],
            'other host' => ['https://evil.example.com', false],
            'host as a subdomain' => ['https://' . self::HOST . '.evil.example.com', false],
            'other host, ours as a subdomain' => ['https://evil.' . self::HOST, false],
            'malformed' => ['not an url', false],
            'null origin (sandboxed iframe)' => ['null', false],
        ];
    }

    /**
     * Test requests are accepted or refused from their Origin header
     */
    #[DataProvider('originProvider')]
    public function testOriginFallback(string $origin, bool $expected): void
    {
        $request = $this->createRequest(headers: ['Origin' => $origin]);

        if ($expected) {
            $this->assertRequestAccepted($request);
        } else {
            $this->assertRequestRefused($request);
        }
    }

    /**
     * Origin and Host ports handling
     *
     * @return array<string, array<int, string|bool>>
     */
    public static function originPortProvider(): array
    {
        return [
            'same host and port' => ['https://' . self::HOST . ':8443', self::HOST . ':8443', true],
            'port on origin only' => ['https://' . self::HOST . ':8443', self::HOST, true],
            'port on host only' => ['https://' . self::HOST, self::HOST . ':8443', true],
            'different ports' => ['https://' . self::HOST . ':8443', self::HOST . ':9443', false],
            'different host, same port' => ['https://evil.example.com:8443', self::HOST . ':8443', false],
        ];
    }

    /**
     * Test ports are only compared when both are known
     */
    #[DataProvider('originPortProvider')]
    public function testOriginPort(string $origin, string $host, bool $expected): void
    {
        $request = $this->createRequest(
            headers: ['Origin' => $origin],
            host: $host
        );

        if ($expected) {
            $this->assertRequestAccepted($request);
        } else {
            $this->assertRequestRefused($request);
        }
    }

    /**
     * IPv6 authorities, which are bracketed on both sides of the comparison
     *
     * @return array<string, array<int, string|bool>>
     */
    public static function ipv6Provider(): array
    {
        return [
            'same address and port' => ['https://[::1]:8443', '[::1]:8443', true],
            'port on host only' => ['https://[::1]', '[::1]:8443', true],
            'port on origin only' => ['https://[::1]:8443', '[::1]', true],
            'no port at all' => ['https://[::1]', '[::1]', true],
            'full address, different case' => ['https://[2001:DB8::1]:8443', '[2001:db8::1]:8443', true],
            'different address' => ['https://[2001:db8::1]', '[::1]', false],
            'different ports' => ['https://[::1]:9443', '[::1]:8443', false],
        ];
    }

    /**
     * Test IPv6 hosts are not mistaken for a host and port couple
     */
    #[DataProvider('ipv6Provider')]
    public function testIpv6Origin(string $origin, string $host, bool $expected): void
    {
        $request = $this->createRequest(
            headers: ['Origin' => $origin],
            host: $host
        );

        if ($expected) {
            $this->assertRequestAccepted($request);
        } else {
            $this->assertRequestRefused($request);
        }
    }

    /**
     * Origin check behind a reverse proxy
     *
     * @return array<string, array<int, string|bool>>
     */
    public static function forwardedHostProvider(): array
    {
        return [
            'forwarded host matches' => [
                'https://public.example.org',
                'public.example.org',
                '',
                true
            ],
            'forwarded host matches, with port' => [
                'https://public.example.org:8443',
                'public.example.org',
                '8443',
                true
            ],
            'forwarded host matches, wrong port' => [
                'https://public.example.org:9443',
                'public.example.org',
                '8443',
                false
            ],
            'chained proxies' => [
                'https://public.example.org',
                'public.example.org, inner.example.org',
                '',
                true
            ],
            'forwarded host does not match' => [
                'https://evil.example.com',
                'public.example.org',
                '',
                false
            ],
            //the backend host stays valid, for direct accesses
            'backend host still matches' => [
                'https://' . self::HOST,
                'public.example.org',
                '',
                true
            ],
            'ipv6 forwarded host' => [
                'https://[2001:db8::1]',
                '[2001:db8::1]',
                '',
                true
            ],
            'ipv6 forwarded host, with port' => [
                'https://[2001:db8::1]:8443',
                '[2001:db8::1]',
                '8443',
                true
            ],
            'ipv6 forwarded host does not match' => [
                'https://[2001:db8::2]',
                '[2001:db8::1]',
                '',
                false
            ],
            //RFC 7239 requires brackets, not every proxy complies
            'unbracketed ipv6 forwarded host' => [
                'https://[2001:db8::1]',
                '2001:db8::1',
                '',
                true
            ],
            'unbracketed ipv6 forwarded host, with port' => [
                'https://[2001:db8::1]:8443',
                '2001:db8::1',
                '8443',
                true
            ],
            'unbracketed ipv6 forwarded host does not match' => [
                'https://[2001:db8::2]',
                '2001:db8::1',
                '',
                false
            ],
        ];
    }

    /**
     * Test the host a reverse proxy has been reached at is taken into account
     */
    #[DataProvider('forwardedHostProvider')]
    public function testForwardedHost(
        string $origin,
        string $forwarded_host,
        string $forwarded_port,
        bool $expected
    ): void {
        $headers = [
            'Origin' => $origin,
            'X-Forwarded-Host' => $forwarded_host
        ];
        if ($forwarded_port !== '') {
            $headers['X-Forwarded-Port'] = $forwarded_port;
        }

        $request = $this->createRequest(headers: $headers);

        if ($expected) {
            $this->assertRequestAccepted($request);
        } else {
            $this->assertRequestRefused($request);
        }
    }

    /**
     * Test requests that do not come from a browser are accepted
     *
     * Payment gateways notifications (IPN, webhooks, ...) rely on this.
     */
    public function testNonBrowserRequest(): void
    {
        $this->assertRequestAccepted($this->createRequest());
    }

    /**
     * Test cross-site requests are accepted on excluded routes only
     */
    public function testExclusions(): void
    {
        $exclusions = ['/paypal_(success|notify|cancelled)/'];

        $this->assertRequestAccepted(
            $this->createRequest(
                headers: ['Sec-Fetch-Site' => 'cross-site'],
                route_name: 'paypal_success'
            ),
            $exclusions
        );

        $this->assertRequestRefused(
            $this->createRequest(
                headers: ['Sec-Fetch-Site' => 'cross-site'],
                route_name: 'store_paypal_preferences'
            ),
            $exclusions
        );

        //without any route, nothing can be excluded
        $this->assertRequestRefused(
            $this->createRequest(headers: ['Sec-Fetch-Site' => 'cross-site']),
            $exclusions
        );
    }

    /**
     * Test refusals are logged with enough details to be understood
     */
    public function testFailureIsLogged(): void
    {
        $middleware = new Csrf();
        $request = $this->createRequest(
            headers: [
                'Sec-Fetch-Site' => 'cross-site',
                'Origin' => 'https://evil.example.com'
            ],
            route_name: 'store_member'
        );

        try {
            $middleware($request, $this->getHandler());
            $this->fail('Request should have been refused.');
        } catch (HttpForbiddenException) {
            //expected
        }

        $this->expectLogEntry(
            Analog::CRITICAL,
            'CSRF check has failed for route "store_member" (POST /member/edit/1) - '
            . 'Sec-Fetch-Site: cross-site; Origin: https://evil.example.com; expected host: ' . self::HOST
        );
    }

    /**
     * Test no template relies on CSRF tokens anymore
     *
     * Protection is handled from HTTP headers; a token in a form would only be dead
     * weight, and the Twig globals it relies on no longer exist.
     */
    public function testNoTokenInTemplates(): void
    {
        $forbidden = [
            'components/forms/csrf.html.twig',
            'csrf_name_key',
            'csrf_name',
            'csrf_value_key',
            'csrf_value'
        ];

        $templates = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                GALETTE_ROOT . 'templates',
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $faulty = [];
        foreach ($templates as $template) {
            if (!$template->isFile() || $template->getExtension() !== 'twig') {
                continue;
            }

            $contents = file_get_contents($template->getPathname());
            foreach ($forbidden as $needle) {
                if (str_contains($contents, $needle)) {
                    $faulty[] = $template->getPathname() . ' (' . $needle . ')';
                }
            }
        }

        $this->assertSame([], $faulty, "CSRF tokens found in:\n" . implode("\n", $faulty));
    }

    /**
     * Test the middleware is still part of the application stack
     *
     * Nothing would fail if it were dropped, protection would just silently vanish.
     */
    public function testMiddlewareIsRegistered(): void
    {
        $this->assertStringContainsString(
            '$app->add(\Galette\Middleware\Csrf::class);',
            file_get_contents(GALETTE_ROOT . 'includes/main.inc.php'),
            'CSRF middleware is not added to the application anymore!'
        );
    }
}
