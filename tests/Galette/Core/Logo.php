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

    /**
     * Test a missing default logo is only reported when the logo is used.
     *
     * Logos are built from the dependency container, before any error page can
     * be rendered; failing right away would only produce a blank HTTP 500.
     */
    public function testMissingDefaultLogo(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        //building it must not throw...
        $instance = new class extends \Galette\Core\Logo {
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

        //...using it must
        $this->expectException(\Galette\Exception\MissingAssetException::class);
        $this->expectExceptionMessage('assets may not have been built');
        $instance->getOptimalWidth();
    }

    /**
     * Test a stale resolved path does not fail the constructor.
     *
     * realpath() keeps a cache of its own that outlives the request, and still
     * resolves files removed meanwhile by another process; the picture then
     * holds a path that cannot be read.
     */
    public function testStaleResolvedPath(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        //building it must not throw, even though the path looks resolved...
        $instance = new class extends \Galette\Core\Logo {
            /**
             * Get default picture
             */
            protected function getDefaultPicture(): void
            {
                $this->format = 'webp';
                $this->mime = 'image/webp';
                //bypass setDefaultPath(), as a stale realpath() would
                $this->file_path = '/nonexistent/images/galette.webp';
            }
        };

        //...using it must
        $this->expectException(\Galette\Exception\MissingAssetException::class);
        $instance->getOptimalWidth();
    }
}
