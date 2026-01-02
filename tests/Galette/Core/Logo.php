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

namespace Galette\Tests\Core;

use PHPUnit\Framework\TestCase;

/**
 * Picture tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Logo extends TestCase
{
    private \Galette\Core\Db $zdb;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        global $zdb;
        $this->zdb = new \Galette\Core\Db();
        $zdb = $this->zdb;
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame($this->zdb->getWarnings(), []);
        }
    }

    /**
     * Test defaults after initialization
     */
    public function testDefaults(): void
    {
        global $zdb;
        $zdb = $this->zdb;
        $expected_paths = [
            realpath(GALETTE_ROOT . 'webroot/themes/default/images/galette.webp'),
            realpath(GALETTE_ROOT . 'webroot/themes/default/images/galette_halloween.webp'),
            realpath(GALETTE_ROOT . 'webroot/themes/default/images/galette_xmas.webp'),
        ];

        $instance = new \Galette\Core\Logo();
        $this->assertNull($instance->getDestDir());
        $this->assertNull($instance->getFileName());
        $this->assertTrue(in_array($instance->getPath(), $expected_paths, true));
        $this->assertSame('image/webp', $instance->getMime());
        $this->assertSame('webp', $instance->getFormat());
        $this->assertFalse($instance->isCustom());
    }
}
