<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Middleware;

use Galette\Enums\PublicPageVisibility;
use Galette\Middleware\PublicPages;
use Galette\Tests\GaletteTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;
use Slim\Routing\RouteParser;

/**
 * Public pages middleware tests
 *
 * The fixture plugin-test1 declares a public page for its `plugin1_public_page`
 * route, and leaves `plugin1_public_other` undeclared.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PublicPagesTest extends GaletteTestCase
{
    private const string DECLARED = 'pref_plugin1_publicpages_visibility_page';

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->preferences->load();
        $this->preferences->pref_bool_publicpages = true;
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->preferences->resetValue(self::DECLARED, $this->login);
        //the others were only changed in memory
        $this->preferences->load();
        parent::tearDown();
    }

    /**
     * Set the visibility of the declared page
     *
     * @param int $visibility Visibility
     */
    private function setDeclared(int $visibility): void
    {
        $this->assertTrue(
            $this->preferences->setValue(self::DECLARED, $visibility, $this->login),
            print_r($this->preferences->getErrors(), return: true)
        );
    }

    /**
     * Run the middleware for an anonymous visitor
     *
     * @param string  $path       Requested path
     * @param ?string $route_name Name of the resolved route, if any
     * @param ?string $pattern    Pattern of the resolved route
     *
     * @return bool Whether the page was served
     */
    private function isServed(string $path, ?string $route_name = null, ?string $pattern = null): bool
    {
        $request = new Request(
            method: 'GET',
            uri: (new UriFactory())->createUri('https://galette.example.org' . $path),
            headers: new Headers(),
            cookies: [],
            serverParams: [],
            body: (new StreamFactory())->createStream()
        );

        if ($route_name !== null) {
            $route = $this->createStub(RouteInterface::class);
            $route->method('getName')->willReturn($route_name);
            $route->method('getPattern')->willReturn($pattern ?? $path);
            $request = $request->withAttribute(RouteContext::ROUTE, $route);
        }

        $handler = new class implements RequestHandlerInterface {
            /**
             * Serve the page
             *
             * @param ServerRequestInterface $request PSR7 request
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withStatus(200);
            }
        };

        $middleware = new PublicPages(
            login: $this->login,
            routeparser: $this->container->get(RouteParser::class),
            preferences: $this->preferences,
            flash: $this->flash
        );
        $response = $middleware($request, $handler);

        if ($response->getStatusCode() === 302) {
            return false;
        }
        $this->assertSame(200, $response->getStatusCode());
        return true;
    }

    /**
     * A declared page follows its own visibility, not the default one
     */
    public function testDeclaredPage(): void
    {
        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Everyone->value;
        $this->setDeclared(PublicPageVisibility::Hidden->value);
        $this->assertFalse($this->isServed('/plugins/plugin1/public/page', 'plugin1_public_page'));

        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Hidden->value;
        $this->setDeclared(PublicPageVisibility::Everyone->value);
        $this->assertTrue($this->isServed('/plugins/plugin1/public/page', 'plugin1_public_page'));

        //staff only: not for an anonymous visitor
        $this->setDeclared(PublicPageVisibility::StaffOnly->value);
        $this->assertFalse($this->isServed('/plugins/plugin1/public/page', 'plugin1_public_page'));
    }

    /**
     * A declared page left to inherit follows the default visibility
     */
    public function testDeclaredPageInherits(): void
    {
        $this->setDeclared(PublicPageVisibility::Inherit->value);

        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Everyone->value;
        $this->assertTrue($this->isServed('/plugins/plugin1/public/page', 'plugin1_public_page'));

        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Hidden->value;
        $this->assertFalse($this->isServed('/plugins/plugin1/public/page', 'plugin1_public_page'));
    }

    /**
     * A page its plugin did not declare follows the default visibility
     */
    public function testUndeclaredPage(): void
    {
        $this->setDeclared(PublicPageVisibility::Hidden->value);

        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Everyone->value;
        $this->assertTrue($this->isServed('/plugins/plugin1/public/other', 'plugin1_public_other'));

        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Hidden->value;
        $this->assertFalse($this->isServed('/plugins/plugin1/public/other', 'plugin1_public_other'));

        //no route resolved: the path alone decides, as before
        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Everyone->value;
        $this->assertTrue($this->isServed('/plugins/plugin1/public/page'));
    }

    /**
     * A route named like a declared one, but living under another plugin, is
     * not put behind the declared visibility
     */
    public function testRouteOfAnotherPlugin(): void
    {
        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Everyone->value;
        $this->setDeclared(PublicPageVisibility::Hidden->value);

        $this->assertTrue($this->isServed('/plugins/plugin2/public/page', 'plugin1_public_page'));
    }

    /**
     * Core pages are left as they were
     */
    public function testCorePages(): void
    {
        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Everyone->value;
        $this->preferences->pref_publicpages_visibility_memberslist = PublicPageVisibility::Hidden->value;
        $this->preferences->pref_publicpages_visibility_documents = PublicPageVisibility::Inherit->value;

        $this->assertFalse($this->isServed('/public/members/list', 'publicMembersList'));
        $this->assertTrue($this->isServed('/public/documents', 'documentsPublicList'));

        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Hidden->value;
        $this->assertFalse($this->isServed('/public/documents', 'documentsPublicList'));
    }

    /**
     * Plugins and the core ask the same question the same way
     */
    public function testShowPluginPublicPage(): void
    {
        $this->preferences->pref_publicpages_visibility_generic = PublicPageVisibility::Hidden->value;
        $this->setDeclared(PublicPageVisibility::Everyone->value);

        $this->assertTrue($this->preferences->showPluginPublicPage($this->login, 'plugin1_public_page'));
        $this->assertFalse($this->preferences->showPluginPublicPage($this->login, 'plugin1_public_other'));

        $this->setDeclared(PublicPageVisibility::Inherit->value);
        $this->assertFalse($this->preferences->showPluginPublicPage($this->login, 'plugin1_public_page'));

        //public pages turned off altogether
        $this->preferences->pref_bool_publicpages = false;
        $this->setDeclared(PublicPageVisibility::Everyone->value);
        $this->assertFalse($this->preferences->showPluginPublicPage($this->login, 'plugin1_public_page'));
    }
}
