<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Logo tests
 *
 * PHP version 5
 *
 * Copyright © 2017-2023 The Galette Team
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
 *
 * @category  IO
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2017-07-08
 */

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Picture tests class
 *
 * @category  Core
 * @name      Logo
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2017-07-08
 */
class Logo extends TestCase
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
            $this->assertSame($this->zdb->getWarnings(), []);
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
        $expected_path = realpath(GALETTE_ROOT . 'webroot/themes/default/images/galette.png');

        $instance = new \Galette\Core\Logo($this->zdb);
        $this->assertNull($instance->getDestDir());
        $this->assertNull($instance->getFileName());
        $this->assertSame($expected_path, $instance->getPath());
        $this->assertSame('image/png', $instance->getMime());
        $this->assertSame('png', $instance->getFormat());
        $this->assertFalse($instance->isCustom());
        $this->assertSame(129, $instance->getOptimalWidth());
        $this->assertSame(60, $instance->getOptimalHeight());
        $this->assertSame(129, $instance->getWidth());
        $this->assertSame(60, $instance->getHeight());
    }
}
