<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Picture tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PrintLogo extends TestCase
{
    private \Galette\Core\Db $zdb;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        global $zdb;
        $this->zdb = new \Galette\Core\Db();
        $zdb = $this->zdb;
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
    }

    /**
     * Test defaults after initialization
     *
     * @return void
     */
    public function testDefaults()
    {
        global $zdb;
        $zdb = $this->zdb;
        $logo = new \Galette\Core\PrintLogo();
        $this->assertNull($logo->getDestDir());
        $this->assertNull($logo->getFileName());

        $expected_path = realpath(GALETTE_ROOT . 'webroot/themes/default/images/galette.png');
        $this->assertSame($expected_path, $logo->getPath());

        $this->assertSame('image/png', $logo->getMime());
        $this->assertSame('png', $logo->getFormat());
        $this->assertFalse($logo->isCustom());
    }
}
