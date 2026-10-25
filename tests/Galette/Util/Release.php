<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Util;

use Galette\Core\Plugins;
use Galette\Tests\GaletteTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;

use function Safe\file_get_contents;
use function Safe\json_encode;

/**
 * Release tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AllowMockObjectsWithoutExpectations]
class Release extends GaletteTestCase
{
    /**
     * Releases provider
     *
     * @return array<int, array<int, string|bool>>
     */
    public static function releasesProvider(): array
    {
        return [
            ['0.7.0', '1.1.0', true],
            ['1.0.0', '1.1.0', true],
            ['1.0.1', '1.1.0', true],
            ['v1.0.1', '1.1.0', true],
            ['1.1.0', '1.1.0', false],
            ['1.2.0', '1.1.0', false],
            ['1.2.0-dev', '1.3.0', false],
        ];
    }

    /**
     * Test checkNewRelease
     *
     * @param string $current  Current release
     * @param string $latest   Latest release
     * @param bool   $expected Expected result
     */
    #[DataProvider('releasesProvider')]
    public function testNewRelease(string $current, string $latest, bool $expected): void
    {
        $release = $this->getMockBuilder(\Galette\Util\Release::class)
            ->setConstructorArgs([true])
            ->onlyMethods(['getCurrentRelease', 'getLatestRelease'])
            ->getMock();

        $release->method('getCurrentRelease')->willReturn($current);
        $release->method('getLatestRelease')->willReturn($latest);

        $this->assertSame($expected, $release->checkNewRelease());
    }

    /**
     * Get a client serving responses per repository
     *
     * @param array<string, Response|\Throwable> $responses Responses, per repository
     */
    private function getClient(array $responses): Client
    {
        $handler = function (RequestInterface $request) use ($responses): PromiseInterface {
            foreach ($responses as $repository => $response) {
                if (str_contains($request->getUri()->getPath(), '/' . $repository . '/releases/latest')) {
                    if ($response instanceof \Throwable) {
                        return Create::rejectionFor($response);
                    }
                    return Create::promiseFor($response);
                }
            }
            return Create::promiseFor(new Response(404));
        };

        return new Client(['handler' => HandlerStack::create($handler)]);
    }

    /**
     * Get a release checker using a mocked client
     *
     * @param array<string, Response|\Throwable> $responses Responses, per repository
     * @param ?Plugins                           $plugins   Plugins
     */
    private function getRelease(array $responses, ?Plugins $plugins = null): \Galette\Util\Release
    {
        $release = $this->getMockBuilder(\Galette\Util\Release::class)
            ->setConstructorArgs([true, $plugins])
            ->onlyMethods(['setupClient', 'getCurrentRelease'])
            ->getMock();

        $client = $this->getClient($responses);
        $release->method('setupClient')->willReturnCallback(fn() => $client);
        $release->method('getCurrentRelease')->willReturn('1.3.0');

        return $release;
    }

    /**
     * Get a GitHub latest release response
     *
     * @param string $tag Release tag
     */
    private function getReleaseResponse(string $tag): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'html_url' => 'https://github.com/owner/repo/releases/tag/' . $tag,
                'tag_name' => $tag,
                'name' => $tag,
                'draft' => false,
                'prerelease' => false
            ])
        );
    }

    /**
     * Get plugins stub
     *
     * @param array<string, array<string, mixed>> $modules Modules
     */
    private function getPlugins(array $modules): Plugins
    {
        $plugins = $this->createStub(Plugins::class);
        $plugins->method('getModules')->willReturn($modules);
        return $plugins;
    }

    /**
     * Test Galette latest release, from the real API response fixture
     */
    public function testGetLatestRelease(): void
    {
        $release = $this->getRelease([
            \Galette\Util\Release::CORE_REPOSITORY => new Response(
                200,
                [],
                file_get_contents(GALETTE_TESTS_PATH . '/releases.json')
            )
        ]);

        $this->assertSame('1.3.1', $release->getLatestRelease());
        $this->assertTrue($release->checkNewRelease());
        $this->assertSame(
            [
                \Galette\Util\Release::CORE_REPOSITORY => [
                    'status' => \Galette\Util\Release::STATUS_FOUND,
                    'version' => '1.3.1',
                    'url' => 'https://github.com/galette/galette/releases/tag/1.3.1'
                ]
            ],
            $release->getReleases()
        );
    }

    /**
     * Test releases lookup failures
     */
    public function testFindLatestReleasesErrors(): void
    {
        $client = $this->getClient([
            'owner/tagged' => $this->getReleaseResponse('v2.1.0'),
            'owner/limited' => new Response(403, [], '{"message": "API rate limit exceeded"}'),
            'owner/invalid' => new Response(200, [], 'not json'),
            'owner/offline' => new ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'https://api.github.com/')
            ),
        ]);
        $release = $this->getMockBuilder(\Galette\Util\Release::class)
            ->setConstructorArgs([true])
            ->onlyMethods(['setupClient'])
            ->getMock();
        $release->method('setupClient')->willReturn($client);

        $releases = $release->findLatestReleases([
            'owner/tagged',
            'owner/missing',
            'owner/limited',
            'owner/invalid',
            'owner/offline'
        ]);

        $this->assertSame(
            [
                'owner/tagged' => [
                    'status' => \Galette\Util\Release::STATUS_FOUND,
                    'version' => '2.1.0',
                    'url' => 'https://github.com/owner/repo/releases/tag/v2.1.0'
                ],
                'owner/missing' => ['status' => \Galette\Util\Release::STATUS_NOT_FOUND],
                'owner/limited' => ['status' => \Galette\Util\Release::STATUS_ERROR],
                'owner/invalid' => ['status' => \Galette\Util\Release::STATUS_ERROR],
                'owner/offline' => ['status' => \Galette\Util\Release::STATUS_ERROR],
            ],
            $releases
        );

        $this->expectLogEntry(\Analog\Analog::WARNING, 'No release found in owner/missing repository.');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error while trying to get latest release of owner/limited');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to read latest release of owner/invalid');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error while trying to get latest release of owner/offline');
    }

    /**
     * Get a module definition
     *
     * @param string  $name       Module name
     * @param string  $version    Module version
     * @param ?string $repository Module repository
     *
     * @return array<string, mixed>
     */
    private function getModule(string $name, string $version, ?string $repository): array
    {
        $module = [
            'root' => '/tmp',
            'name' => $name,
            'desc' => '',
            'author' => '',
            'version' => $version,
            'acls' => null,
            'date' => null,
            'priority' => 1000,
            'route' => null,
            'dbversion' => null
        ];
        if ($repository !== null) {
            $module['repository'] = $repository;
        }
        return $module;
    }

    /**
     * Test plugins updates and releases status
     */
    public function testPluginsUpdates(): void
    {
        $plugins = $this->getPlugins([
            'plugin-newer' => $this->getModule('Newer', '1.0.0', 'galette-plugins/plugin-newer'),
            'plugin-same' => $this->getModule('Same', '2.0.0', 'galette-plugins/plugin-same'),
            'plugin-older' => $this->getModule('Older', '3.1.0', 'galette-plugins/plugin-older'),
            'plugin-dev' => $this->getModule('Dev', '1.0.0-dev', 'galette-plugins/plugin-dev'),
            'renamed' => $this->getModule('Renamed', '1.0.0', 'galette-plugins/renamed'),
            'third' => $this->getModule('Third party', 'v1.0.0', 'someone/galette-third'),
            'broken' => $this->getModule('broken', '', repository: null),
        ]);

        $release = $this->getRelease(
            [
                \Galette\Util\Release::CORE_REPOSITORY => $this->getReleaseResponse('1.3.1'),
                'galette-plugins/plugin-newer' => $this->getReleaseResponse('1.1.0'),
                'galette-plugins/plugin-same' => $this->getReleaseResponse('2.0.0'),
                'galette-plugins/plugin-older' => $this->getReleaseResponse('3.0.0'),
                'galette-plugins/plugin-dev' => $this->getReleaseResponse('1.0.0'),
                'someone/galette-third' => $this->getReleaseResponse('v1.2.0'),
            ],
            $plugins
        );

        $this->assertSame(
            [
                \Galette\Util\Release::CORE_REPOSITORY => '',
                'galette-plugins/plugin-newer' => 'plugin-newer',
                'galette-plugins/plugin-same' => 'plugin-same',
                'galette-plugins/plugin-older' => 'plugin-older',
                'galette-plugins/plugin-dev' => 'plugin-dev',
                'galette-plugins/renamed' => 'renamed',
                'someone/galette-third' => 'third',
            ],
            $release->getRepositories()
        );

        $this->assertSame(
            [
                'plugin-newer' => [
                    'name' => 'Newer',
                    'current' => '1.0.0',
                    'latest' => '1.1.0',
                    'url' => 'https://github.com/owner/repo/releases/tag/1.1.0'
                ],
                'third' => [
                    'name' => 'Third party',
                    'current' => 'v1.0.0',
                    'latest' => '1.2.0',
                    'url' => 'https://github.com/owner/repo/releases/tag/v1.2.0'
                ]
            ],
            $release->getPluginsUpdates()
        );

        $status = $release->getPluginsReleasesStatus();
        $this->assertCount(6, $status);
        $this->assertArrayNotHasKey('broken', $status);
        $this->assertSame(
            ['repository' => 'galette-plugins/renamed', 'status' => \Galette\Util\Release::STATUS_NOT_FOUND],
            $status['renamed']
        );
        $this->assertSame(
            ['repository' => 'someone/galette-third', 'status' => \Galette\Util\Release::STATUS_FOUND],
            $status['third']
        );

        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Unable to check for new releases of plugin "renamed": no release found in galette-plugins/renamed repository.'
        );
    }

    /**
     * Test cache content
     */
    public function testCache(): void
    {
        $plugins = $this->getPlugins([
            'plugin-one' => $this->getModule('One', '1.0.0', 'galette-plugins/plugin-one'),
        ]);
        $release = $this->getRelease(
            [
                \Galette\Util\Release::CORE_REPOSITORY => $this->getReleaseResponse('1.3.1'),
                'galette-plugins/plugin-one' => new Response(500),
            ],
            $plugins
        );

        $get_data = new \ReflectionMethod($release, 'getDataTocache');
        $cache_loaded = new \ReflectionMethod($release, 'cacheLoaded');

        $release->getReleases();
        $cached = $get_data->invoke($release);
        $this->assertStringContainsString('"status":"error"', $cached);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error while trying to get latest release of galette-plugins/plugin-one');

        //cache is loaded back by another instance
        $other = $this->getRelease([], $plugins);
        $this->assertTrue($cache_loaded->invoke($other, $cached));
        $this->assertSame($release->getReleases(), $other->getReleases());
        $this->assertSame('1.3.1', $other->getLatestRelease());

        //cache is invalidated when plugins have changed
        $more_plugins = $this->getPlugins([
            'plugin-one' => $this->getModule('One', '1.0.0', 'galette-plugins/plugin-one'),
            'plugin-two' => $this->getModule('Two', '1.0.0', 'galette-plugins/plugin-two'),
        ]);
        $changed = $this->getRelease([], $more_plugins);
        $this->assertFalse($cache_loaded->invoke($changed, $cached));

        //invalid contents
        $this->assertFalse($cache_loaded->invoke($other, 'not json'));
        $this->assertFalse($cache_loaded->invoke($other, '1.3.1'));
        $this->assertFalse($cache_loaded->invoke($other, null));
    }

    /**
     * Test a cache that cannot be written does not prevent releases check
     */
    public function testUnwritableCache(): void
    {
        $release = $this->getMockBuilder(\Galette\Util\Release::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setupClient', 'getCacheFilename'])
            ->getMock();
        $client = $this->getClient([
            \Galette\Util\Release::CORE_REPOSITORY => $this->getReleaseResponse('1.3.1')
        ]);
        $release->method('setupClient')->willReturn($client);
        $release->method('getCacheFilename')->willReturn('/proc/galette/releases.cache');

        $release->__construct(false);
        $this->assertSame('1.3.1', $release->getLatestRelease());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Unable to use releases cache');
    }
}
