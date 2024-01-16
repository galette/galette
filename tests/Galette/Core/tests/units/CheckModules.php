<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CheckModules tests
 *
 * PHP version 5
 *
 * Copyright © 2016-2023 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2016-11-09
 */

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * CheckModules tests class
 *
 * @category  Core
 * @name      CheckModules
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2016-11-09
 */
class CheckModules extends TestCase
{
    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $zdb = new \Galette\Core\Db();
            $this->assertSame([], $zdb->getWarnings());
        }
        parent::tearDown();
    }

    /**
     * Test modules, all should be ok
     *
     * @return void
     */
    public function testAllOK()
    {
        $checks = new \Galette\Core\CheckModules();
        $this->assertTrue($checks->isValid());
        $this->assertGreaterThanOrEqual(6, count($checks->getGoods()));
        $this->assertLessThanOrEqual(10, count($checks->getGoods()));
        $this->assertSame([], $checks->getMissings());
        $this->assertSame([], $checks->getShoulds());
        $this->assertTrue($checks->isGood('mbstring'));
    }

    /**
     * Test all extensions missing
     *
     * @return void
     */
    public function testAllKO()
    {
        $checks = $this->getMockBuilder(\Galette\Core\CheckModules::class)
            ->setConstructorArgs([false])
            ->onlyMethods(array('isExtensionLoaded'))
            ->getMock();
        $checks->method('isExtensionLoaded')->willReturn(false);

        $checks->doCheck(false);
        $this->assertSame(0, count($checks->getGoods()));
        $this->assertSame(3, count($checks->getShoulds()));
        $this->assertSame(6, count($checks->getMissings()));

        $html = $checks->toHtml();
        $this->assertStringNotContainsString('green check icon', $html);
        $this->assertSame(1221, strlen($html));
    }

    /**
     * Test HTMl output
     *
     * @return void
     */
    public function testToHtml()
    {
        $checks = new \Galette\Core\CheckModules();
        $checks->doCheck();
        $html = $checks->toHtml();
        $this->assertStringNotContainsString('icon-invalid.png', $html);
        $this->assertGreaterThanOrEqual(908, strlen($html));
    }
}
