<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;

use function Safe\realpath;

/**
 * Picture tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Logo extends BaseGaletteTestCase
{
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
