<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * CheckModules tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CheckModules extends BaseGaletteTestCase
{
    /**
     * Test modules, all should be ok
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
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testAllKO(): void
    {
        $checks = $this->getMockBuilder(\Galette\Core\CheckModules::class)
            ->setConstructorArgs([false])
            ->onlyMethods(['isExtensionLoaded'])
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
