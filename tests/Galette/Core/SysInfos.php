<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * SysInfos tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SysInfos extends GaletteTestCase
{
    /**
     * Test getRawData
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetRawData(): void
    {
        $this->plugins = $this->getMockBuilder(\Galette\Core\Plugins::class)
            ->onlyMethods(['getModules'])
            ->getMock();

        $this->plugins->method('getModules')->willReturn([
            'test_plugin' => [
                'name' => 'A test plugin',
                'version' => '1.0.0',
                'description' => 'A test plugin description',
                'author' => 'Test Author'
            ]
        ]);

        $sysinfos = new \Galette\Core\SysInfos();
        $_SERVER['HTTP_USER_AGENT'] = 'GaletteTest';
        $rdata = $sysinfos->getRawData($this->zdb, $this->preferences, $this->plugins);
        $this->assertStringContainsString('Galette version:', $rdata);
        $this->assertStringContainsString('PHP loaded modules:', $rdata);
        $this->assertStringContainsString('Plugins:', $rdata);
    }
}
