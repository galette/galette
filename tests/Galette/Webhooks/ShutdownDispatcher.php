<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Webhooks;

use Galette\Tests\GaletteTestCase;
use Galette\Webhooks\Dispatcher;
use Galette\Webhooks\DispatchReport;

/**
 * End of request webhooks dispatching tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ShutdownDispatcher extends GaletteTestCase
{
    /**
     * Test nothing is ever scheduled from command line
     */
    public function testDisabledFromCli(): void
    {
        $built = false;
        $shutdown = new \Galette\Webhooks\ShutdownDispatcher(
            $this->preferences,
            function () use (&$built): Dispatcher {
                $built = true;
                return $this->createStub(Dispatcher::class);
            }
        );
        $this->assertFalse($shutdown->isEnabled());
        $shutdown->schedule(fn(): array => [1, 2]);
        $this->assertFalse($built);
    }

    /**
     * Test only deliveries of the request are sent without PHP-FPM
     */
    public function testDispatch(): void
    {
        $this->assertFalse(function_exists('fastcgi_finish_request'));

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())
            ->method('processIds')
            ->with([3, 4])
            ->willReturn(new DispatchReport());
        $dispatcher->expects($this->never())->method('process');

        $shutdown = new \Galette\Webhooks\ShutdownDispatcher($this->preferences, fn(): Dispatcher => $dispatcher);
        $shutdown->dispatch([3, 4]);
    }

    /**
     * Test errors are logged
     */
    public function testDispatchError(): void
    {
        $shutdown = new \Galette\Webhooks\ShutdownDispatcher(
            $this->preferences,
            fn(): Dispatcher => throw new \RuntimeException('No way')
        );
        $shutdown->dispatch([1]);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to send webhooks deliveries | No way');
    }
}
