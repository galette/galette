<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Maps\Coordinates;
use Galette\Maps\Precision;
use Galette\Maps\TileProviders;
use Galette\Tests\GaletteRoutingTestCase;

use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\preg_match;
use function Safe\putenv;

/**
 * Maps controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MapsController extends GaletteRoutingTestCase
{
    protected int $seed = 20260925101512;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->preferences->pref_maps_enabled = true;
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->preferences->pref_maps_enabled = false;
        $this->preferences->pref_bool_groupsmanagers_edit_member = false;
        $this->preferences->pref_bool_publicpages = true;
        $this->preferences->pref_publicpages_visibility_generic = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
        $this->preferences->pref_publicpages_visibility_maps = \Galette\Enums\PublicPageVisibility::Inherit->value;
        $this->zdb->execute($this->zdb->delete(Coordinates::TABLE));
        parent::tearDown();
    }

    /**
     * Log in given member
     *
     * @param array<string,mixed> $mdata Member data
     */
    private function logMember(array $mdata): void
    {
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
    }

    /**
     * Make member two manager of a group
     *
     * @param Adherent[] $members Group members
     */
    private function makeMemberTwoManager(array $members): void
    {
        $group = new \Galette\Entity\Group();
        $group->setName('Maps group');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setManagers([$this->getMemberTwo()]));
        $this->assertTrue($group->setMembers($members));
    }

    /**
     * Get maps configuration the page gives to its script
     *
     * @param string $body Page body
     *
     * @return array<string, mixed>
     */
    private function getMapsConfig(string $body): array
    {
        $this->assertSame(
            1,
            preg_match('@<script type="application/json" id="maps-config">(.*?)</script>@s', $body, $matches)
        );
        $config = json_decode($matches[1], associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($config);
        return $config;
    }

    /**
     * Post coordinates for a member
     *
     * @param ?int                 $id_adh Member ID, null for logged-in one
     * @param array<string,string> $data   Posted data
     */
    private function postCoords(?int $id_adh, array $data = ['latitude' => '50.362038', 'longitude' => '3.472998']): \Psr\Http\Message\ResponseInterface
    {
        $request = $this->createRequest(
            'mapsStorePosition',
            $id_adh === null ? [] : ['id' => (string)$id_adh],
            'POST'
        )->withParsedBody($data);
        return $this->app->handle($request);
    }

    /**
     * Assert coordinates change has been refused
     *
     * @param \Psr\Http\Message\ResponseInterface $test_response Response
     * @param int                                 $id_adh        Target member ID
     */
    private function expectCoordsRefused(\Psr\Http\Message\ResponseInterface $test_response, int $id_adh): void
    {
        $this->assertSame(403, $test_response->getStatusCode());
        $this->assertSame(
            //message comes in the language of the logged-in member
            ['res' => false, 'message' => _T('You do not have permission for requested URL.')],
            json_decode((string)$test_response->getBody(), associative: true)
        );
        $this->expectLogEntry(
            Analog::WARNING,
            'has tried to change coordinates of member #' . $id_adh
        );
        $this->expectNoLogEntry();
    }

    /**
     * A member cannot change nor remove coordinates of another member
     */
    public function testMemberCannotChangeOtherMemberCoords(): void
    {
        //member two speaks Catalan, member one gets messages in English
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $coords = new Coordinates($this->zdb, $this->login);
        $coords->set($member_two->id, 48.85, 2.35);

        $this->logMember($this->dataAdherentOne());
        $this->expectCoordsRefused($this->postCoords($member_two->id), $member_two->id);
        $this->expectCoordsRefused($this->postCoords($member_two->id, ['remove' => '1']), $member_two->id);

        $this->assertSame(
            ['latitude' => '48.850000', 'longitude' => '2.350000'],
            $coords->get($member_two->id)
        );
    }

    /**
     * A member can change its own coordinates, with or without its ID in the route
     */
    public function testMemberChangesOwnCoords(): void
    {
        $member_one = $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());

        foreach ([null, $member_one->id] as $id_adh) {
            $test_response = $this->postCoords($id_adh);
            $this->assertSame(200, $test_response->getStatusCode());
            $this->assertSame(
                ['res' => true, 'message' => 'New coordinates have been stored!'],
                json_decode((string)$test_response->getBody(), associative: true)
            );
        }
        $this->assertNotNull((new Coordinates($this->zdb, $this->login))->get($member_one->id));
    }

    /**
     * Group managers change coordinates of their members only when core allows them to edit members
     */
    public function testManagerChangesCoordsAsCoreAllows(): void
    {
        $member_one = $this->getMemberOne();
        $this->makeMemberTwoManager([$member_one]);
        $this->logMember($this->dataAdherentTwo());

        $this->expectCoordsRefused($this->postCoords($member_one->id), $member_one->id);
        $this->assertNull((new Coordinates($this->zdb, $this->login))->get($member_one->id));

        $this->preferences->pref_bool_groupsmanagers_edit_member = true;
        $test_response = $this->postCoords($member_one->id);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertNotNull((new Coordinates($this->zdb, $this->login))->get($member_one->id));
    }

    /**
     * Coordinates out of bounds, not numeric or missing are refused
     */
    public function testInvalidCoords(): void
    {
        $member_one = $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());

        $invalid = [
            ['latitude' => '91', 'longitude' => '3'],
            ['latitude' => '50', 'longitude' => '-180.5'],
            ['latitude' => 'north', 'longitude' => '3'],
            ['latitude' => '50'],
            [],
        ];
        foreach ($invalid as $data) {
            $test_response = $this->postCoords(id_adh: null, data: $data);
            $this->assertSame(400, $test_response->getStatusCode(), print_r($data, return: true));
            $this->assertSame(
                ['res' => false, 'message' => 'Invalid coordinates.'],
                json_decode((string)$test_response->getBody(), associative: true)
            );
        }
        $this->assertNull((new Coordinates($this->zdb, $this->login))->get($member_one->id));

        //bounds are included
        $test_response = $this->postCoords(id_adh: null, data: ['latitude' => '-90', 'longitude' => '180']);
        $this->assertSame(200, $test_response->getStatusCode());
    }

    /**
     * Removing coordinates of a member that has none is not an error
     */
    public function testRemoveMissingCoords(): void
    {
        $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());
        $test_response = $this->postCoords(id_adh: null, data: ['remove' => '1']);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(
            ['res' => true, 'message' => 'Coordinates have been removed!'],
            json_decode((string)$test_response->getBody(), associative: true)
        );
    }

    /**
     * Towns matching member town are proposed when member has no coordinates
     */
    public function testTownsProposed(): void
    {
        $member_one = $this->getMemberOne();
        $update = $this->zdb->update(Adherent::TABLE);
        $update->set(['ville_adh' => 'Valenciennes'])->where([Adherent::PK => $member_one->id]);
        $this->zdb->execute($update);
        $places = [
            ['lat' => '50.3620', 'lon' => '3.4729', 'display_name' => 'Valenciennes', 'address' => ['city' => 'Valenciennes']],
            ['lat' => '45.1', 'lon' => '1.2', 'display_name' => 'Somewhere', 'address' => ['village' => '<b>Petit</b> Valenciennes']],
        ];
        $client = new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create(
                new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(200, [], (string)json_encode($places))])
            )
        ]);
        $this->container->set(
            \Galette\Maps\NominatimTowns::class,
            new \Galette\Maps\NominatimTowns($this->preferences, $client)
        );

        $this->logMember($this->dataAdherentOne());
        $test_response = $this->app->handle($this->createRequest('mapsLocalizeMe'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('id="possible_towns"', $body);
        $this->assertStringContainsString('In the database, town is set to: &#039;Valenciennes&#039;', $body);
        $this->assertStringContainsString('data-lat="50.3620" data-lng="3.4729"', $body);
        $this->assertStringContainsString('&lt;b&gt;Petit&lt;/b&gt; Valenciennes', $body);
        $this->assertStringNotContainsString('<b>Petit</b>', $body);
    }

    /**
     * Unreachable towns search does not prevent to display the map
     */
    public function testTownsSearchUnavailable(): void
    {
        $member_one = $this->getMemberOne();
        $this->assertNotEmpty($member_one->town);
        $this->logMember($this->dataAdherentOne());

        //no proxy listens there: the request fails at once
        putenv('HTTPS_PROXY=http://127.0.0.1:1');
        try {
            $test_response = $this->app->handle($this->createRequest('mapsLocalizeMe'));
        } finally {
            putenv('HTTPS_PROXY');
        }
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString(
            'Town search is not available for now, you can still search or click on the map.',
            (string)$test_response->getBody()
        );
        $this->expectLogEntry(Analog::WARNING, 'Unable to search towns for member #' . $member_one->id);
        $this->expectNoLogEntry();
    }

    /**
     * A member cannot display coordinates of another member
     */
    public function testMemberCannotShowOtherMemberCoords(): void
    {
        //member two speaks Catalan, member one gets messages in English
        $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        (new Coordinates($this->zdb, $this->login))->set($member_two->id, 48.85, 2.35);

        $this->logMember($this->dataAdherentOne());
        $request = $this->createRequest('mapsLocalizeMember', ['id' => (string)$member_two->id]);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('me')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['You do not have permission for requested URL.']]);
        $this->expectLogEntry(
            Analog::WARNING,
            'has tried to display coordinates of member #' . $member_two->id
        );
        $this->expectNoLogEntry();
    }

    /**
     * Group managers display coordinates of their members, and change them only when core allows them to
     */
    public function testManagerShowsCoords(): void
    {
        $member_one = $this->getMemberOne();
        $this->makeMemberTwoManager([$member_one]);
        (new Coordinates($this->zdb, $this->login))->set($member_one->id, 48.85, 2.35);
        $this->logMember($this->dataAdherentTwo());

        $request = $this->createRequest('mapsLocalizeMember', ['id' => (string)$member_one->id]);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $member = $this->getMapsConfig((string)$test_response->getBody())['member'];
        $this->assertSame(['latitude' => '48.850000', 'longitude' => '2.350000'], $member['position']);
        $this->assertFalse($member['can_edit']);

        $this->preferences->pref_bool_groupsmanagers_edit_member = true;
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $member = $this->getMapsConfig((string)$test_response->getBody())['member'];
        $this->assertTrue($member['can_edit']);
        $this->assertSame(
            $this->routeparser->urlFor('mapsStorePosition', ['id' => (string)$member_one->id]),
            $member['store_url']
        );
    }

    /**
     * Nicknames and company names reach the script as data, never as markup
     *
     * Member form strips tags, stored values may not have been through it.
     * The script shows them as text; the page must not hold them as HTML.
     */
    public function testMapEscapesNames(): void
    {
        $member_one = $this->getMemberOne();
        $update = $this->zdb->update(Adherent::TABLE);
        $update->set([
            'pseudo_adh' => '<b>nick</b>',
            'societe_adh' => '<img src=x onerror=alert(1)>',
        ])->where([Adherent::PK => $member_one->id]);
        $this->zdb->execute($update);
        (new Coordinates($this->zdb, $this->login))->set($member_one->id, 48.85, 2.35);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->createRequest('maps'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringNotContainsString('<b>', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
        $markers = $this->getMapsConfig($body)['markers'];
        $this->assertCount(1, $markers);
        $this->assertSame('<b>nick</b>', $markers[0]['nickname']);
        $this->assertSame('<img src=x onerror=alert(1)>', $markers[0]['company']);
        //member IDs are not published
        $this->assertArrayNotHasKey('id_adh', $markers[0]);
    }

    /**
     * Map is displayed to visitors only when public pages allow it
     */
    public function testPublicMap(): void
    {
        $member_one = $this->getMemberOne();
        (new Coordinates($this->zdb, $this->login))->set($member_one->id, 48.85, 2.35);
        $request = $this->createRequest('maps');

        $this->preferences->pref_bool_publicpages = false;
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['Unauthorized']]);

        $this->preferences->pref_bool_publicpages = true;
        $this->preferences->pref_publicpages_visibility_generic = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;
        //the map has its own visibility
        $this->preferences->pref_publicpages_visibility_maps = \Galette\Enums\PublicPageVisibility::Hidden->value;
        $test_response = $this->app->handle($request);
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['Unauthorized']]);

        //which inherits the default one
        $this->preferences->pref_publicpages_visibility_maps = \Galette\Enums\PublicPageVisibility::Inherit->value;
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        //member one does not display its information
        $this->assertSame([], $this->getMapsConfig((string)$test_response->getBody())['markers']);
    }

    /**
     * Snapped positions cap the map zoom; exact ones do not
     */
    public function testMapPrecision(): void
    {
        $member_one = $this->getMemberOne();
        $update = $this->zdb->update(Adherent::TABLE);
        $update->set([
            'bool_display_info' => new \Laminas\Db\Sql\Expression('true'),
            'bool_exempt_adh' => new \Laminas\Db\Sql\Expression('true'),
        ])->where([Adherent::PK => $member_one->id]);
        $this->zdb->execute($update);
        (new Coordinates($this->zdb, $this->login))->set($member_one->id, 50.362038, 3.472998);
        $this->preferences->pref_publicpages_visibility_generic = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;
        $request = $this->createRequest('maps');

        //visitor, default precision
        $config = $this->getMapsConfig((string)$this->app->handle($request)->getBody());
        $this->assertSame(['50.36', '3.47'], [$config['markers'][0]['lat'], $config['markers'][0]['lng']]);
        $this->assertSame(Precision::getMaxZoom(Precision::DEFAULT), $config['max_zoom']);

        try {
            $this->assertTrue($this->preferences->setValue(Precision::PREF, '100km', $this->login));
            $config = $this->getMapsConfig((string)$this->app->handle($request)->getBody());
            $this->assertSame(['50', '4'], [$config['markers'][0]['lat'], $config['markers'][0]['lng']]);
            $this->assertSame(Precision::getMaxZoom('100km'), $config['max_zoom']);

            $this->assertTrue($this->preferences->setValue(Precision::PREF, Precision::EXACT, $this->login));
            $config = $this->getMapsConfig((string)$this->app->handle($request)->getBody());
            $this->assertSame('50.362038', $config['markers'][0]['lat']);
            $this->assertNull($config['max_zoom']);
        } finally {
            $this->assertTrue($this->preferences->setValue(Precision::PREF, Precision::DEFAULT, $this->login));
        }

        //member itself: exact, no cap
        $this->logMember($this->dataAdherentOne());
        $config = $this->getMapsConfig((string)$this->app->handle($request)->getBody());
        $this->assertSame('50.362038', $config['markers'][0]['lat']);
        $this->assertNull($config['max_zoom']);
    }

    /**
     * Own localization page, with and without coordinates
     */
    public function testOwnPage(): void
    {
        $member_one = $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());
        $request = $this->createRequest('mapsLocalizeMe');

        //no town search without a town
        $update = $this->zdb->update(Adherent::TABLE);
        $update->set(['ville_adh' => ''])->where([Adherent::PK => $member_one->id]);
        $this->zdb->execute($update);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringNotContainsString('id="possible_towns"', $body);
        $config = $this->getMapsConfig($body);
        $this->assertNull($config['member']['position']);
        $this->assertTrue($config['member']['can_edit']);
        $this->assertSame($this->routeparser->urlFor('mapsStorePosition'), $config['member']['store_url']);
        $this->assertTrue($config['locate']);

        (new Coordinates($this->zdb, $this->login))->set($member_one->id, 48.85, 2.35);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Maps - ' . $member_one->sfullname . ' geographic position', $body);
        $config = $this->getMapsConfig($body);
        $this->assertSame(['lat' => '48.850000', 'lng' => '2.350000', 'zoom' => 12], $config['center']);
        $this->assertSame('You clicked at %1$s', $config['strings']['clicked_at']);
        $this->assertSame(['latitude' => '48.850000', 'longitude' => '2.350000'], $config['member']['position']);
        $this->assertSame('I live here!', $config['strings']['lives_here']);
    }

    /**
     * Only administrators reach preferences
     */
    public function testPreferencesAccess(): void
    {
        $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());
        $this->expectAuthMiddlewareRefused($this->app->handle($this->createRequest('mapsSettings')));
        $this->login->logout();

        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->createRequest('mapsSettings'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('id="pref_maps_tiles_provider"', $body);
        $this->assertStringContainsString('OpenFreeMap, light grey', $body);
    }

    /**
     * Store preferences
     */
    public function testStorePreferences(): void
    {
        $this->logSuperAdmin();
        $store = function (array $data): \Psr\Http\Message\ResponseInterface {
            $request = $this->createRequest('doMapsSettings', [], 'POST')->withParsedBody($data);
            $test_response = $this->app->handle($request);
            $this->assertSame(
                ['Location' => [$this->routeparser->urlFor('mapsSettings')]],
                $test_response->getHeaders()
            );
            $this->assertSame(302, $test_response->getStatusCode());
            return $test_response;
        };

        try {
            $store(['pref_maps_enabled' => '1', TileProviders::PREF_PROVIDER => 'osm', Precision::PREF => '50km']);
            $this->expectFlashData(['success_detected' => ['Maps settings have been saved.']]);
            $this->preferences->load();
            $this->assertTrue($this->preferences->pref_maps_enabled);
            $this->assertSame('osm', TileProviders::resolve($this->preferences)['id']);
            $this->assertSame('50km', Precision::resolve($this->preferences));

            $store([TileProviders::PREF_PROVIDER => 'osm', Precision::PREF => '1km']);
            $this->expectFlashData(['error_detected' => ['Unknown position precision.']]);
            $this->preferences->load();
            $this->assertSame('50km', Precision::resolve($this->preferences));

            //own values need an address
            $store([TileProviders::PREF_PROVIDER => TileProviders::CUSTOM, TileProviders::PREF_URL => '  ']);
            $this->expectFlashData(['error_detected' => ['An address is required to use your own background map.']]);
            $this->preferences->load();
            $this->assertSame('osm', TileProviders::resolve($this->preferences)['id']);

            //unticked vector box is not posted
            $store([
                TileProviders::PREF_PROVIDER => TileProviders::CUSTOM,
                TileProviders::PREF_URL => 'https://tiles.example.org/{z}/{x}/{y}.png',
                TileProviders::PREF_ATTRIBUTION => 'Example',
                TileProviders::PREF_MAXZOOM => '17',
                TileProviders::PREF_SUBDOMAINS => '',
            ]);
            $this->expectFlashData(['success_detected' => ['Maps settings have been saved.']]);
            $this->preferences->load();
            //unticked toggle is not posted either
            $this->assertFalse($this->preferences->pref_maps_enabled);
            $tiles = TileProviders::resolve($this->preferences);
            $this->assertSame(TileProviders::CUSTOM, $tiles['id']);
            $this->assertFalse($tiles['vector']);
            $this->assertSame('https://tiles.example.org/{z}/{x}/{y}.png', $tiles['url']);
            $this->assertSame(17, $tiles['maxzoom']);

            //out of range zoom is refused
            $store([
                TileProviders::PREF_PROVIDER => TileProviders::CUSTOM,
                TileProviders::PREF_URL => 'https://tiles.example.org/{z}/{x}/{y}.png',
                TileProviders::PREF_MAXZOOM => '30',
            ]);
            //core has no range error message
            $this->expectFlashData(['error_detected' => ['- Value for \'pref_maps_tiles_maxzoom\' must be a positive number!']]);
            $this->preferences->load();
            $this->assertSame(17, TileProviders::resolve($this->preferences)['maxzoom']);
        } finally {
            foreach (TileProviders::getSchema() + Precision::getSchema() as $name => $schema) {
                $this->preferences->setValue($name, $schema['default'], $this->login);
            }
        }
    }

    /**
     * Nothing is displayed nor stored while maps are not enabled, settings stay reachable
     */
    public function testDisabled(): void
    {
        $this->preferences->pref_maps_enabled = false;
        $member_one = $this->getMemberOne();
        //public pages visibility is checked first, superadmin passes it
        $this->logSuperAdmin();

        foreach (
            [
                $this->createRequest('maps'),
                $this->createRequest('mapsLocalizeMe'),
                $this->createRequest('mapsLocalizeMember', ['id' => (string)$member_one->id])
            ] as $request
        ) {
            $test_response = $this->app->handle($request);
            $this->assertSame(
                ['Location' => [$this->routeparser->urlFor('slash')]],
                $test_response->getHeaders()
            );
            $this->assertSame(301, $test_response->getStatusCode());
            $this->expectFlashData(['error_detected' => ['Maps are not enabled.']]);
        }

        $test_response = $this->app->handle($this->createRequest('mapsSettings'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertMatchesRegularExpression(
            '@<input[^>]+id="pref_maps_enabled"@',
            (string)$test_response->getBody()
        );
        $this->login->logout();

        $this->logMember($this->dataAdherentOne());
        $test_response = $this->postCoords(id_adh: null);
        $this->assertSame(404, $test_response->getStatusCode());
        $this->assertSame(
            ['res' => false, 'message' => 'Maps are not enabled.'],
            json_decode((string)$test_response->getBody(), associative: true)
        );
        $this->assertNull((new Coordinates($this->zdb, $this->login))->get($member_one->id));
    }

    /**
     * Superadmin has no own localization page
     */
    public function testSuperAdminOwnPage(): void
    {
        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->createRequest('mapsLocalizeMe'));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['Superadmin cannot be localized.']]);
    }

    /**
     * Localization of a member that does not exist
     */
    public function testMissingMember(): void
    {
        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->createRequest('mapsLocalizeMember', ['id' => '999999']));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('slash')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['No member #999999.']]);
        //logged by Adherent on load
        $this->expectLogEntry(Analog::ERROR, 'No member #999999');

        $test_response = $this->postCoords(999999);
        $this->assertSame(404, $test_response->getStatusCode());
        $this->assertSame(
            ['res' => false, 'message' => 'No member #999999.'],
            json_decode((string)$test_response->getBody(), associative: true)
        );
        $this->expectLogEntry(Analog::ERROR, 'No member #999999');
        $this->expectNoLogEntry();
    }

    /**
     * Superadmin changes coordinates of any member, but has none
     */
    public function testSuperAdminCoords(): void
    {
        $member_one = $this->getMemberOne();
        $this->logSuperAdmin();

        $test_response = $this->postCoords($member_one->id);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertNotNull((new Coordinates($this->zdb, $this->login))->get($member_one->id));

        $test_response = $this->postCoords(id_adh: null);
        $this->assertSame(400, $test_response->getStatusCode());
        $this->assertSame(
            ['res' => false, 'message' => 'Superadmin cannot be localized.'],
            json_decode((string)$test_response->getBody(), associative: true)
        );
        $this->expectLogEntry(Analog::INFO, 'SuperAdmin does not live anywhere!');
    }
}
