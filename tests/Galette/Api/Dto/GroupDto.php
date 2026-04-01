<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Tests\Api\Dto;

use Galette\Api\Dto\GroupDto;
use Galette\Entity\Group;
use Galette\Tests\GaletteTestCase;

/**
 * Tests for GroupDto — field mapping and serialization
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GroupDtoTest extends GaletteTestCase
{
    protected int $seed = 20260402020202;

    private Group $group;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->group = new Group();
        $this->group->setName('Test Group ' . $this->seed);
        $this->assertTrue($this->group->store(), 'Group could not be saved in DB');
    }

    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        if ($this->group->getId() !== null) {
            $this->group->remove(true);
        }
        parent::tearDown();
    }

    /**
     * fromGroup() maps all entity fields correctly
     */
    public function testFromGroupMapsFields(): void
    {
        $dto = GroupDto::fromGroup($this->group);

        $this->assertSame($this->group->getId(), $dto->id);
        $this->assertSame($this->group->getName(), $dto->name);
        $this->assertNull($dto->parent_id);
        $this->assertSame($this->group->getMemberCount(), $dto->member_count);
    }

    /**
     * toArray() contains all expected keys with correct values
     */
    public function testToArrayHasAllKeys(): void
    {
        $arr = GroupDto::fromGroup($this->group)->toArray();

        $expectedKeys = ['id', 'name', 'parent_id', 'member_count'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: $key");
        }

        $this->assertSame($this->group->getId(), $arr['id']);
        $this->assertSame($this->group->getName(), $arr['name']);
        $this->assertNull($arr['parent_id']);
    }

    /**
     * Readonly properties are immutable after construction
     */
    public function testDtoIsReadonly(): void
    {
        $dto = GroupDto::fromGroup($this->group);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $dto->id = 0;
    }
}
