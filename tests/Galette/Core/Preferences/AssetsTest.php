<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core\Preferences;

use Galette\Core\Preferences\Assets;
use Galette\Tests\GaletteTestCase;
use Slim\Flash\Messages;

use function Safe\file_put_contents;

/**
 * Preferences assets tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AssetsTest extends GaletteTestCase
{
    /**
     * Dangerous markup never survives
     */
    public function testCleanHtmlValue(): void
    {
        $assets = new Assets();

        $this->assertSame('<p>Hello</p>', $assets->cleanHtmlValue('<p>Hello</p>'));
        $this->assertStringNotContainsString('<script', $assets->cleanHtmlValue('<script>alert(1)</script>'));
        $this->assertStringNotContainsString(
            'javascript:',
            $assets->cleanHtmlValue('<a href="javascript:alert(1)">x</a>')
        );
        //a link to an allowed scheme is kept
        $this->assertStringContainsString('https://galette.eu', $assets->cleanHtmlValue('<a href="https://galette.eu">x</a>'));
    }

    /**
     * A colour change makes the generated stylesheet stale
     */
    public function testIsCssImpacted(): void
    {
        $assets = new Assets();
        $current = ['pref_enable_custom_colors' => true, 'pref_cc_primary' => '#ffb619'];

        $this->assertFalse(
            $assets->isCssImpacted(submitted: $current, current: $current),
            'nothing changed'
        );
        $this->assertTrue(
            $assets->isCssImpacted(
                submitted: ['pref_enable_custom_colors' => true, 'pref_cc_primary' => '#000000'],
                current: $current
            ),
            'a colour changed'
        );
        $this->assertTrue(
            $assets->isCssImpacted(
                submitted: ['pref_cc_primary' => '#ffb619'],
                current: $current
            ),
            'custom colours were turned off, so the value is missing'
        );
    }

    /**
     * The stylesheet is dropped once, and says so
     */
    public function testResetDarkCss(): void
    {
        $assets = new Assets();
        $storage = [];
        $flash = new Messages($storage);
        $cssfile = GALETTE_CACHE_DIR . '/dark.css';

        //nothing to drop
        $this->assertFalse($assets->resetDarkCss(flash: $flash));

        file_put_contents($cssfile, '/* generated */');
        $this->assertTrue($assets->resetDarkCss(flash: $flash));
        $this->assertFileDoesNotExist($cssfile);

        //and not a second time
        $this->assertFalse($assets->resetDarkCss(flash: $flash));
    }
}
