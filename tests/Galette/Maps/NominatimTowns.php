<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Analog\Analog;
use Galette\Tests\GaletteTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

use function Safe\glob;
use function Safe\json_encode;
use function Safe\rmdir;
use function Safe\touch;
use function Safe\unlink;

/**
 * Nominatim towns search tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class NominatimTowns extends GaletteTestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $requests = [];

    /**
     * Get a search instance answering given responses
     *
     * @param array<int, Response|\Throwable> $responses Responses
     * @param string                          $cache_dir Where answers are kept, none by default
     */
    private function getSearch(array $responses, string $cache_dir = ''): \Galette\Maps\NominatimTowns
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));
        return new \Galette\Maps\NominatimTowns(
            $this->preferences,
            new Client(['handler' => $stack]),
            $cache_dir
        );
    }

    /**
     * Towns are extracted from the answer, other places and duplicates are ignored
     */
    public function testSearch(): void
    {
        $places = [
            ['lat' => '50.3620', 'lon' => '3.4729', 'display_name' => 'Valenciennes, Nord', 'address' => ['city' => 'Valenciennes']],
            ['lat' => '50.3620', 'lon' => '3.4729', 'display_name' => 'Valenciennes again', 'address' => ['town' => 'Valenciennes']],
            ['lat' => '45.1', 'lon' => '1.2', 'display_name' => 'Somewhere', 'address' => ['village' => 'Petit Valenciennes']],
            ['lat' => '46.1', 'lon' => '1.3', 'display_name' => 'Municipality', 'address' => ['municipality' => 'Valenciennes-Commune']],
            ['lat' => '47.1', 'lon' => '1.4', 'display_name' => 'Hamlet', 'address' => ['hamlet' => 'Les Valenciennes']],
            ['lat' => '44.0', 'lon' => '2.0', 'display_name' => 'Rue de Valenciennes', 'address' => ['road' => 'Rue de Valenciennes']],
        ];
        $search = $this->getSearch([new Response(200, [], (string)json_encode($places))]);

        $this->assertSame(
            [
                ['full_name' => 'Valenciennes', 'latitude' => '50.3620', 'longitude' => '3.4729'],
                ['full_name' => 'Petit Valenciennes', 'latitude' => '45.1', 'longitude' => '1.2'],
                ['full_name' => 'Valenciennes-Commune', 'latitude' => '46.1', 'longitude' => '1.3'],
                ['full_name' => 'Les Valenciennes', 'latitude' => '47.1', 'longitude' => '1.4'],
            ],
            $search->search('Valenciennes', 'France')
        );
        $this->expectLogEntry(Analog::INFO, 'Town is already in list, ignore.');
        $this->expectLogEntry(Analog::INFO, 'Nominatim result "Rue de Valenciennes" is not a town');

        $this->assertCount(1, $this->requests);
        /** @var Request $request */
        $request = $this->requests[0]['request'];
        $this->assertSame('https', $request->getUri()->getScheme());
        $this->assertSame('nominatim.openstreetmap.org', $request->getUri()->getHost());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(
            ['format' => 'jsonv2', 'addressdetails' => '1', 'city' => 'Valenciennes', 'country' => 'France'],
            $query
        );
        $this->assertStringStartsWith('Galette (', $request->getHeaderLine('User-Agent'));
    }

    /**
     * An empty town does not query the service
     */
    public function testEmptyTown(): void
    {
        $search = $this->getSearch([]);
        $this->assertSame([], $search->search('  '));
        $this->assertCount(0, $this->requests);
    }

    /**
     * Service failures are reported with an exception
     */
    public function testFailures(): void
    {
        $failures = [
            new Response(503),
            new Response(200, [], 'not json'),
            new \GuzzleHttp\Exception\ConnectException('Timeout', new Request('GET', 'https://nominatim.openstreetmap.org')),
        ];
        foreach ($failures as $failure) {
            $search = $this->getSearch([$failure]);
            try {
                $search->search('Valenciennes');
                $this->fail('An exception was expected');
            } catch (\RuntimeException $e) {
                $this->assertStringStartsWith('Error on nominatim request for "Valenciennes"', $e->getMessage());
            }
        }
    }

    /**
     * Answers are kept a few days, per town and country; failures are not
     */
    public function testCache(): void
    {
        $cache_dir = sys_get_temp_dir() . '/galette-nominatim-' . bin2hex(random_bytes(4));
        $places = [
            ['lat' => '50.3620', 'lon' => '3.4729', 'display_name' => 'Valenciennes', 'address' => ['city' => 'Valenciennes']],
        ];
        $expected = [['full_name' => 'Valenciennes', 'latitude' => '50.3620', 'longitude' => '3.4729']];
        try {
            $search = $this->getSearch(
                [
                    new Response(200, [], (string)json_encode($places)),
                    new Response(200, [], '[]'),
                    new Response(503),
                    new Response(200, [], (string)json_encode($places)),
                ],
                $cache_dir
            );

            $this->assertSame($expected, $search->search('Valenciennes', 'France'));
            //case and spaces do not make another search
            $this->assertSame($expected, $search->search(' valenciennes ', 'FRANCE'));
            $this->assertCount(1, $this->requests);

            //an empty answer is kept as well
            $this->assertCount(0, $search->search('Nowhere', 'France'));
            $this->assertCount(0, $search->search('Nowhere', 'France'));
            $this->assertCount(2, $this->requests);

            //another country is another search; its failure is not kept
            try {
                $search->search('Valenciennes', 'Belgique');
                $this->fail('An exception was expected');
            } catch (\RuntimeException $e) {
                $this->assertStringStartsWith('Error on nominatim request', $e->getMessage());
            }
            $this->assertCount(3, $this->requests);

            //too old, the answer is searched again
            $files = glob($cache_dir . '/*.json');
            $this->assertCount(2, $files);
            foreach ($files as $file) {
                touch($file, time() - \Galette\Maps\NominatimTowns::CACHE_TTL - 1);
            }
            $this->assertSame($expected, $search->search('Valenciennes', 'France'));
            $this->assertCount(4, $this->requests);
        } finally {
            if (is_dir($cache_dir)) {
                foreach (glob($cache_dir . '/*.json') as $file) {
                    unlink($file);
                }
                rmdir($cache_dir);
            }
        }
    }
}
