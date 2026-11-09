<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Middleware;

use Galette\Core\Csp;
use Galette\Middleware\SecurityHeaders;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;

use function Safe\file_get_contents;
use function Safe\preg_match_all;

/**
 * Security headers middleware tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SecurityHeadersTest extends GaletteTestCase
{
    /**
     * Run the middleware on a response of given type
     *
     * @param Csp     $csp  Content Security Policy
     * @param ?string $type Response Content-Type, if any
     */
    private function process(Csp $csp, ?string $type): ResponseInterface
    {
        $handler = new readonly class ($type) implements RequestHandlerInterface {
            /**
             * @param ?string $type Response Content-Type, if any
             */
            public function __construct(private ?string $type)
            {
            }

            /**
             * Handle request
             *
             * @param ServerRequestInterface $request PSR7 request
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response();
                return $this->type === null ? $response : $response->withHeader('Content-Type', $this->type);
            }
        };

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://galette.example.org/');
        return (new SecurityHeaders($csp))($request, $handler);
    }

    /**
     * HTML content types provider
     *
     * @return array<string, array{?string}>
     */
    public static function htmlTypesProvider(): array
    {
        return [
            'no type' => [null],
            'html' => ['text/html'],
            'html with charset' => ['text/html; charset=utf-8'],
        ];
    }

    /**
     * Test headers on HTML responses
     *
     * @param ?string $type Response Content-Type
     */
    #[DataProvider('htmlTypesProvider')]
    public function testHtmlResponse(?string $type): void
    {
        $csp = $this->container->get(Csp::class);
        $response = $this->process($csp, $type);

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->getHeaderLine('Referrer-Policy'));
        $this->assertSame($csp->getEnforcedPolicy(), $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame(
            $csp->getFullPolicy(),
            $response->getHeaderLine('Content-Security-Policy-Report-Only')
        );
    }

    /**
     * Test non HTML responses do not get a policy
     */
    public function testNonHtmlResponse(): void
    {
        foreach (['application/json', 'application/pdf', 'text/csv', 'image/png'] as $type) {
            $response = $this->process($this->container->get(Csp::class), $type);
            $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'), $type);
            $this->assertFalse($response->hasHeader('Content-Security-Policy'), $type);
            $this->assertFalse($response->hasHeader('Content-Security-Policy-Report-Only'), $type);
        }
    }

    /**
     * Test SVG responses cannot run scripts when opened on their own
     */
    public function testSvgResponse(): void
    {
        foreach (['image/svg+xml', 'image/svg+xml; charset=utf-8'] as $type) {
            $response = $this->process($this->container->get(Csp::class), $type);
            $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'), $type);
            $this->assertSame(
                "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
                $response->getHeaderLine('Content-Security-Policy'),
                $type
            );
            $this->assertFalse($response->hasHeader('Content-Security-Policy-Report-Only'), $type);
        }
    }

    /**
     * Test templates use the nonce of the request
     */
    public function testTwigNonce(): void
    {
        $csp = $this->container->get(Csp::class);
        $rendered = $this->container->get(Twig::class)
            ->getEnvironment()
            ->createTemplate('<script nonce="{{ csp_nonce() }}"></script>')
            ->render();

        $this->assertSame('<script nonce="' . $csp->getNonce() . '"></script>', $rendered);
        $this->assertStringContainsString("'nonce-" . $csp->getNonce() . "'", $csp->getFullPolicy());
    }

    /**
     * Test every script of core templates carries the nonce
     *
     * Once the policy is enforced, a script without nonce is not run.
     */
    public function testTemplatesScriptsHaveNonce(): void
    {
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
            $matches = [];
            preg_match_all('/<script\b[^>]*>/i', $contents, $matches);
            foreach ($matches[0] as $tag) {
                if (!str_contains($tag, 'nonce="{{ csp_nonce() }}"')) {
                    $faulty[] = $template->getPathname() . ': ' . $tag;
                }
            }
            if (preg_match_all('/\son[a-z]+\s*=|javascript:/i', $contents) > 0) {
                $faulty[] = $template->getPathname() . ': inline event handler or javascript: URL';
            }
        }

        $this->assertSame([], $faulty, "Scripts that would be blocked:\n" . implode("\n", $faulty));
    }

    /**
     * Test frame ancestors preference is validated
     */
    public function testFrameAncestorsPreference(): void
    {
        $this->logSuperAdmin();
        try {
            $this->assertFalse(
                $this->preferences->setValue(
                    'pref_csp_frame_ancestors',
                    "https://example.org; script-src *",
                    $this->login
                )
            );
            $this->assertCount(1, $this->preferences->getErrors());
            $this->assertSame(Csp::DEFAULT_FRAME_ANCESTORS, $this->preferences->pref_csp_frame_ancestors);

            $this->assertTrue(
                $this->preferences->setValue(
                    'pref_csp_frame_ancestors',
                    "'self'  https://Asso.example.org/",
                    $this->login
                ),
                print_r($this->preferences->getErrors(), return: true)
            );
            $this->assertSame(
                "'self' https://asso.example.org",
                $this->preferences->pref_csp_frame_ancestors
            );
        } finally {
            $this->preferences->setValue('pref_csp_frame_ancestors', Csp::DEFAULT_FRAME_ANCESTORS, $this->login);
        }
    }

    /**
     * Test the middleware is part of the application stack, outermost
     */
    public function testMiddlewareIsRegistered(): void
    {
        $gapp = new \Galette\Core\SlimApp($this->plugins);
        $gapp->configure(
            new \RKA\SessionMiddleware(['name' => 'galette_tests']),
            new \Analog\Logger()
        );

        $dispatcher = (new \ReflectionProperty(\Slim\App::class, 'middlewareDispatcher'))
            ->getValue($gapp->getApp());
        $tip = (new \ReflectionProperty($dispatcher, 'tip'))->getValue($dispatcher);
        $middleware = (new \ReflectionProperty($tip, 'middleware'))->getValue($tip);

        $this->assertSame(
            SecurityHeaders::class,
            is_object($middleware) ? $middleware::class : $middleware,
            'Security headers middleware is not the outermost one anymore!'
        );
    }
}
