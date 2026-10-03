<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;

/**
 * Print logo tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PrintLogo extends BaseGaletteTestCase
{
    /**
     * Test defaults after initialization
     */
    public function testDefaults(): void
    {
        global $zdb;
        $zdb = $this->zdb;
        $logo = new \Galette\Core\PrintLogo();
        $this->assertNull($logo->getDestDir());
        $this->assertNull($logo->getFileName());

        $this->assertSame(
            $logo->getPath(),
            GALETTE_CACHE_DIR . '/galette_printlogo_converted.png'
        );

        $this->assertSame('image/png', $logo->getMime());
        $this->assertSame('png', $logo->getFormat());
        $this->assertFalse($logo->isCustom());
    }

    /**
     * Test a missing logo is only reported when the print logo is used.
     *
     * Print logos build upon Logo, whose path cannot be asked for while
     * constructing them without reintroducing a failure from the container.
     */
    public function testMissingDefaultLogo(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        //building it must not throw...
        $logo = new class extends \Galette\Core\PrintLogo {
            /**
             * Get logo
             */
            protected function getLogo(): \Galette\Core\Logo
            {
                return new class extends \Galette\Core\Logo {
                    /**
                     * Get default picture
                     */
                    protected function getDefaultPicture(): void
                    {
                        $this->format = 'webp';
                        $this->mime = 'image/webp';
                        $this->setDefaultPath('/nonexistent/images/galette.webp');
                    }
                };
            }
        };

        //...using it must
        $this->expectException(\Galette\Exception\MissingAssetException::class);
        $this->expectExceptionMessage('assets may not have been built');
        $logo->getPath();
    }
}
