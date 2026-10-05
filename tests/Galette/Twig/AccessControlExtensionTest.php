<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Twig\test\units;

use Galette\Access\AccessControl;
use Galette\Tests\GaletteTestCase;
use Galette\Twig\AccessControlExtension;

/**
 * AccessControl Twig extension tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AccessControlExtensionTest extends GaletteTestCase
{
    /**
     * Test can() delegates to access control
     */
    public function testCan(): void
    {
        $subject = new \stdClass();
        $access = $this->createMock(AccessControl::class);
        $access->expects($this->exactly(2))
            ->method('can')
            ->willReturnMap([
                ['member:edit', $subject, null, true],
                ['member:delete', null, null, false],
            ]);

        $extension = new AccessControlExtension($access);
        $this->assertTrue($extension->can('member:edit', $subject));
        $this->assertFalse($extension->can('member:delete'));
    }

    /**
     * Test can() is available in templates
     */
    public function testRegistered(): void
    {
        $view = $this->container->get(\Slim\Views\Twig::class);
        $this->assertNotNull($view->getEnvironment()->getFunction('can'));
    }
}
