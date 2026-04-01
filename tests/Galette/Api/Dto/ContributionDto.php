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

use Galette\Api\Dto\ContributionDto;
use Galette\Tests\GaletteTestCase;

/**
 * Tests for ContributionDto — field mapping and serialization
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionDtoTest extends GaletteTestCase
{
    protected int $seed = 20260402010101;

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->getMemberOne();
        $this->createContribution();
    }

    /**
     * fromContribution() maps all entity fields correctly
     */
    public function testFromContributionMapsFields(): void
    {
        $dto = ContributionDto::fromContribution($this->contrib);

        $this->assertSame($this->contrib->id, $dto->id);
        $this->assertSame($this->contrib->member, $dto->id_adh);
        $this->assertSame($this->contrib->amount, $dto->amount);
        $this->assertSame($this->contrib->info, $dto->info);
    }

    /**
     * toArray() contains all expected keys
     */
    public function testToArrayHasAllKeys(): void
    {
        $arr = ContributionDto::fromContribution($this->contrib)->toArray();

        $expectedKeys = [
            'id', 'id_adh', 'id_type', 'amount',
            'date', 'begin_date', 'end_date', 'payment', 'info',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: $key");
        }

        $this->assertSame($this->contrib->id, $arr['id']);
        $this->assertSame($this->contrib->member, $arr['id_adh']);
    }

    /**
     * Readonly properties are immutable after construction
     */
    public function testDtoIsReadonly(): void
    {
        $dto = ContributionDto::fromContribution($this->contrib);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $dto->id = 0;
    }
}
