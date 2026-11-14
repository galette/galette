<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Galette\Maps\Coordinates;
use Galette\Maps\Positions as MapsPositions;
use Galette\Tests\GaletteTestCase;

/**
 * Geographic positions of any kind of object tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Positions extends GaletteTestCase
{
    protected int $seed = 20261114101530;

    /** A type of object a plugin could declare */
    private const string OTHER = 'plugin_object';

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->zdb->execute($this->zdb->delete(MapsPositions::TABLE));
        parent::tearDown();
    }

    /**
     * Positions of objects of different types do not mix, even with a same identifier
     */
    public function testTypes(): void
    {
        $positions = new MapsPositions($this->zdb);
        $this->assertNull($positions->get(Coordinates::TYPE, 42));
        $this->assertSame([], $positions->getIds(Coordinates::TYPE));

        $positions->set(
            type: Coordinates::TYPE,
            id: 42,
            latitude: 48.856614,
            longitude: 2.352222
        );
        $positions->set(type: self::OTHER, id: 42, latitude: 45.764043, longitude: 4.835659);
        $positions->set(type: self::OTHER, id: 43, latitude: 43.296482, longitude: 5.369780);

        $this->assertSame(['latitude' => '48.856614', 'longitude' => '2.352222'], $positions->get(Coordinates::TYPE, 42));
        $this->assertSame(['latitude' => '45.764043', 'longitude' => '4.835659'], $positions->get(self::OTHER, 42));
        $this->assertSame([42 => true], $positions->getIds(Coordinates::TYPE));
        $this->assertSame([42 => true, 43 => true], $positions->getIds(self::OTHER));

        //set again: changed, not added
        $positions->set(type: self::OTHER, id: 42, latitude: 50.629250, longitude: 3.057256);
        $this->assertSame(['latitude' => '50.629250', 'longitude' => '3.057256'], $positions->get(self::OTHER, 42));
        $this->assertCount(3, $this->zdb->execute($this->zdb->select(MapsPositions::TABLE))->toArray());

        $positions->remove(self::OTHER, [42, 43]);
        $this->assertSame([], $positions->getIds(self::OTHER));
        $this->assertNotNull($positions->get(Coordinates::TYPE, 42));

        //removing nothing is not an error
        $positions->remove(self::OTHER, 42);
        $positions->remove(self::OTHER, []);
        $positions->remove(Coordinates::TYPE, 42);
        $this->assertNull($positions->get(Coordinates::TYPE, 42));
    }

    /**
     * Removing members removes their positions, not those of other objects
     */
    public function testMembersRemoval(): void
    {
        $member = $this->getMemberOne();
        $positions = new MapsPositions($this->zdb);
        $positions->set(
            type: Coordinates::TYPE,
            id: $member->id,
            latitude: 48.856614,
            longitude: 2.352222
        );
        $positions->set(type: self::OTHER, id: $member->id, latitude: 45.764043, longitude: 4.835659);

        $this->logSuperAdmin();
        $this->assertTrue((new \Galette\Repository\Members())->removeMembers($member->id));
        $this->login->logOut();

        $this->assertNull($positions->get(Coordinates::TYPE, $member->id));
        $this->assertNotNull($positions->get(self::OTHER, $member->id));
    }
}
