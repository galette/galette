<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Renderers;

use Galette\Exception\MissingAssetException;
use Galette\Tests\BaseGaletteTestCase;
use Slim\Flash\Messages;
use Slim\Views\Twig;

/**
 * HTML error renderer tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Html extends BaseGaletteTestCase
{
    /**
     * Test a missing asset is rendered without Twig.
     *
     * Twig error pages extend public_page.html.twig, which displays the logo;
     * rendering one for a missing logo would throw again, from a place Slim
     * does not guard.
     */
    public function testMissingAssetBypassesTwig(): void
    {
        $view = $this->createMock(Twig::class);
        //Twig must not be involved at all
        $view->expects($this->never())->method('render');

        $storage = [];
        $renderer = new \Galette\Renderers\Html($view, new Messages($storage));
        $html = $renderer(new MissingAssetException('/some/where/galette.webp'), false);

        $this->assertStringContainsString('Galette assets are missing', $html);
        $this->assertStringContainsString('/some/where/galette.webp', $html);
        $this->assertStringContainsString('Assets are not part of the source repository', $html);
        //the page must not pull anything from the missing build
        $this->assertStringNotContainsString('semantic.min.css', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * Test a missing asset is found through the exception chain
     */
    public function testMissingAssetAsPrevious(): void
    {
        $view = $this->createMock(Twig::class);
        $view->expects($this->never())->method('render');

        $storage = [];
        $renderer = new \Galette\Renderers\Html($view, new Messages($storage));
        $exception = new \RuntimeException(
            'Something went wrong',
            0,
            new MissingAssetException('/some/where/default.png')
        );
        $html = $renderer($exception, false);

        $this->assertStringContainsString('/some/where/default.png', $html);
    }
}
