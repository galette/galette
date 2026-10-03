<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Util;

use Galette\Util\Filesize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Filesize tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FilesizeTest extends TestCase
{
    /**
     * Sizes in bytes, and how they read
     *
     * @return array<int, array<int, int|string>>
     */
    public static function bytesProvider(): array
    {
        return [
            [0, '0 octets'],
            [1, '1 octets'],
            [1023, '1023 octets'],
            [1024, '1 Ko'],
            [1536, '1.5 Ko'],
            [1024 * 1024 - 1, '1024 Ko'],
            [1024 * 1024, '1 Mo'],
            [2048 * 1024, '2 Mo'],
            [1024 * 1024 * 1024, '1 Go'],
            [5 * 1024 * 1024 * 1024, '5 Go'],
        ];
    }

    /**
     * Test size from bytes
     *
     * @param int    $bytes    Size in bytes
     * @param string $expected Expected text
     */
    #[DataProvider('bytesProvider')]
    public function testFromBytes(int $bytes, string $expected): void
    {
        $this->assertSame($expected, Filesize::fromBytes($bytes));
    }

    /**
     * A limit is stored in kilobytes, and reads in the unit that fits
     */
    public function testFromKilobytes(): void
    {
        $this->assertSame('1 Ko', Filesize::fromKilobytes(1));
        $this->assertSame('42 Ko', Filesize::fromKilobytes(42));
        $this->assertSame('1 Mo', Filesize::fromKilobytes(1024));
        $this->assertSame('2 Mo', Filesize::fromKilobytes(2048));
        $this->assertSame('8 Mo', Filesize::fromKilobytes(8192));
        $this->assertSame('1 Go', Filesize::fromKilobytes(1024 * 1024));
    }
}
