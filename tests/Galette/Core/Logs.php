<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;

use function Safe\touch;
use function Safe\strtotime;

/**
 * Logs tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Logs extends GaletteTestCase
{
    /**
     * Test cleanup
     */
    public function testCleanup(): void
    {
        //create a fake old log file
        touch(GALETTE_LOGS_PATH . '/my.log', strtotime('-2 months'));
        $this->assertFileExists(GALETTE_LOGS_PATH . '/my.log');

        \Galette\Core\Logs::cleanup();

        $this->assertFileDoesNotExist(GALETTE_LOGS_PATH . '/my.log');
    }
}
