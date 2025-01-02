<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * CheckModules tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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
    public function testAllOK(): void
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
    public function testAllKO(): void
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
    public function testToHtml(): void
    {
        $checks = new \Galette\Core\CheckModules();
        $checks->doCheck();
        $html = $checks->toHtml();
        $this->assertStringNotContainsString('icon-invalid.png', $html);
        $this->assertGreaterThanOrEqual(908, strlen($html));
    }
}
