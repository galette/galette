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

use Galette\Api\Dto\MemberDto;
use Galette\Tests\GaletteTestCase;

/**
 * Tests for MemberDto — field mapping and serialization
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberDtoTest extends GaletteTestCase
{
    protected int $seed = 20260401010101;

    /**
     * Test that fromAdherent() maps all entity fields correctly
     */
    public function testFromAdherentMapsFields(): void
    {
        $member = $this->getMemberOne();

        $dto = MemberDto::fromAdherent($member);

        $this->assertSame($member->id, $dto->id);
        $this->assertSame($member->login, $dto->login);
        $this->assertSame($member->name, $dto->name);
        $this->assertSame($member->surname, $dto->surname);
        $this->assertSame($member->email, $dto->email);
        $this->assertSame($member->company_name, $dto->company);
        $this->assertSame($member->isActive(), $dto->active);
        $this->assertSame($member->isAdmin(), $dto->admin);
        $this->assertSame($member->isStaff(), $dto->staff);
        $this->assertSame($member->isDueFree(), $dto->due_free);
    }

    /**
     * Test that toArray() contains all expected keys with correct values
     */
    public function testToArrayHasAllKeys(): void
    {
        $member = $this->getMemberOne();
        $arr = MemberDto::fromAdherent($member)->toArray();

        $expectedKeys = [
            'id', 'login', 'name', 'surname', 'company',
            'email', 'phone', 'gsm', 'address', 'zipcode',
            'town', 'country', 'active', 'admin', 'staff',
            'due_free', 'due_date', 'creation_date', 'number',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: $key");
        }

        $this->assertSame($member->id, $arr['id']);
        $this->assertSame($member->email, $arr['email']);
        $this->assertIsBool($arr['active']);
        $this->assertIsBool($arr['admin']);
        $this->assertIsBool($arr['staff']);
    }

    /**
     * Test that readonly properties are immutable after construction
     */
    public function testDtoIsReadonly(): void
    {
        $member = $this->getMemberOne();
        $dto = MemberDto::fromAdherent($member);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $dto->id = 0;
    }
}
