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

use Galette\GaletteTestCase;

/**
 * SysInfos tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SysInfos extends GaletteTestCase
{
    /**
     * Test getRawData
     *
     * @return void
     */
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
        $this->assertIsString($rdata);
        $this->assertStringContainsString('Galette version:', $rdata);
        $this->assertStringContainsString('PHP loaded modules:', $rdata);
        $this->assertStringContainsString('Plugins:', $rdata);
    }
}
